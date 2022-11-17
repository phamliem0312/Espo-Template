<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM VoIP Integration
 * Extension Agreement ("License") which can be viewed at
 * https://www.espocrm.com/voip-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2021 Letrium Ltd.
 * 
 * License ID: e36042ded1ed7ba87a149ac5079bd238
 ***********************************************************************************/

namespace Espo\Modules\Voip\Providers\Twilio;

use Espo\Modules\Voip\Entities\VoipMessage;
use Espo\Modules\Voip\Entities\VoipRouter as VoipRouterEntity;

class MessageListener extends \Espo\Modules\Voip\Bases\MessageListener
{
    public function handle(array $messageData)
    {
        $state = isset($messageData['MessageStatus']) ? $messageData['MessageStatus'] : $messageData['SmsStatus'];
        $state = strtolower($state);

        $entityManager = $this->getEntityManager();
        $voipRepository = $this->getVoipMessageRepository();

        $connector = $this->getConnector();

        $voipMessage = $voipRepository->createMessage($messageData['MessageSid'], null, $connector);

        if ( !in_array($state, $voipMessage->getStatusList()) ) {
            return;
        }

        $isSave = false;

        switch ($state) {
            case 'received':

                $voipRouter = $this->getVoipRouter($messageData['To'], $connector);

                $voipMessage->set(array(
                    'type' => $this->isSms($messageData) ? VoipMessage::SMS : VoipMessage::MMS,
                    'direction' => 'incoming',
                    'status' => $state,
                    'body' => $messageData['Body'],
                    'externalId' => $messageData['MessageSid'],
                    'from' => $messageData['From'],
                    'to' => $messageData['To'],
                    'dateSent' => date('Y-m-d H:i:s'),
                    'connector' => $connector,
                    'voipRouterId' => isset($voipRouter) ? $voipRouter->get('id') : null,
                ));

                if (!$this->isSms($messageData)) {
                    $voipMessage->set('mediaUrls', $this->normalizeMediaUrls($messageData));
                }

                $entityManager->saveEntity($voipMessage); //need to pre-populate "entities" field

                //find and assign a user
                $assignedUserId = $this->findAssignedUser($voipMessage, $messageData);
                if (!empty($assignedUserId)) {
                    $voipMessage->set('assignedUserId', $assignedUserId);
                } else {
                    $GLOBALS['log']->error('Twilio Message: Error finding assignedUser, message data: ' . print_r($messageData, true));
                }

                $userIdList = $voipRouter->getUserListByRuleType($voipMessage->get('type'));
                if ($userIdList) {
                    foreach ($userIdList as $userId) {
                        $voipMessage->addLinkMultipleId('users', $userId);
                    }
                }

                $isSave = true;
                break;

            default:
                $voipMessage->set('status', $state);
                $isSave = true;
                break;
        }

        if ($isSave && isset($voipMessage)) {
            $entityManager->saveEntity($voipMessage);
        }

        return $voipMessage;
    }

    protected function isSms(array $messageData)
    {
        if (isset($messageData['NumMedia']) && $messageData['NumMedia'] > 0) {
            return false;
        }

        return true;
    }

    protected function findAssignedUser(VoipMessage $voipMessage, array $messageData)
    {
        $connectorData = $this->getConnectorData();

        $preferedUserList = array();
        if ($connectorData->preferAssignedUser) {
            $preferedUserList = $this->getContainer()->get('voipHelper')->getPreferedUserList($voipMessage);
        }

        $connector = $this->getConnector();

        $voipRouterRepository = $this->getEntityManager()->getRepository('VoipRouter');
        $voipRouter = $voipRouterRepository->getByName($messageData['To'], $connector);

        $ruleType = $this->isSms($messageData) ? VoipRouterEntity::SMS : VoipRouterEntity::MMS;

        return $voipRouterRepository->getMessageAssignedUser($voipRouter, $ruleType, array(), $preferedUserList);
    }

    /**
     * Normalize MediaUrls for VoipMessage
     * @see  https://www.twilio.com/docs/api/twiml/sms/twilio_request#synchronous
     *
     * @param  array  $messageData
     *
     * @return array|null
     */
    protected function normalizeMediaUrls(array $messageData)
    {
        if (!isset($messageData['NumMedia'])) {
            return null;
        }

        $mediaUrls = array();
        for ($i=0; $i < $messageData['NumMedia']; $i++) {
            $mediaUrlName = 'MediaUrl' . $i;
            if (!empty($messageData[$mediaUrlName])) {
                $mediaUrls[] = $messageData[$mediaUrlName];
            }
        }

        return $mediaUrls;
    }

    protected function getVoipRouter($name, $connector = null)
    {
        if (!$connector) {
            $connector = $this->getConnector();
        }

        return $this->getEntityManager()->getRepository('VoipRouter')->getByName($name, $connector);
    }
}
