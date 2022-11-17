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

namespace Espo\Modules\Voip\Jobs;

use \Espo\Core\Exceptions\Error;

class TwilioSyncPhoneNumbers extends \Espo\Core\Jobs\Base
{
    private $twilioManagers = array();

    public function run()
    {
        $entityManager = $this->getEntityManager();
        $twilioManagers = $this->getTwilioManagers();

        $voipRouterEntity = $entityManager->getEntity('VoipRouter');
        $voipRouterRepository = $entityManager->getRepository('VoipRouter');

        foreach ($twilioManagers as $connectorId => $twilioManager) {

            $connectorData = $twilioManager->getConnectorData();
            if (empty($connectorData->twilioApplicationSid)) {
                throw new Error('TwilioSyncPhoneNumbers: Field twilioApplicationSid is empty for connector['.$connectorId.'].');
            }

            $twilioApplicationSid = $connectorData->twilioApplicationSid;
            $voipRouterNeedToBeInactive = $this->getActiveVoipRoutersByConnector($connectorId);
            $phoneNumberList = $twilioManager->getPhoneNumberList();

            foreach ($phoneNumberList as $sid => $phoneData) {
                $isSave = false;

                $voipRouter = $voipRouterRepository->getByExternalId($phoneData['sid']);
                if (!isset($voipRouter)) { //phone number is a new, creating
                    $voipRouter = clone $voipRouterEntity;
                    $voipRouter->set(array(
                        'name' => $phoneData['phoneNumber'],
                        'status' => 'Inactive',
                        'externalId' => $phoneData['sid'],
                        'connector' => $connectorId,
                        'voicemailNotifications' => $voipRouter->getAttributeParam('voicemailNotifications', 'default'),
                    ));
                    $isSave = true;
                }

                if ($voipRouter->get('status') == 'Active' && ($phoneData['voiceApplicationSid'] != $twilioApplicationSid || $phoneData['smsApplicationSid'] != $twilioApplicationSid)) {
                    $phoneData = $twilioManager->updatePhoneNumber($phoneData['sid'], array(
                        'voiceApplicationSid' => $twilioApplicationSid,
                        'smsApplicationSid' => $twilioApplicationSid,
                    ));
                }

                //set modified values
                $modifiedFields = array(
                    'voice' => $phoneData['capabilities']['voice'],
                    'sms' => $phoneData['capabilities']['sms'],
                    'mms' => $phoneData['capabilities']['mms'],
                    //'data' => $phoneData,
                );

                foreach ($modifiedFields as $fieldName => $fieldValue) {
                    if ($voipRouter->get($fieldName) != $fieldValue) {
                        $voipRouter->set($fieldName, $fieldValue);
                        $isSave = true;
                    }
                }
                //END: set modified values

                if ($isSave) {
                    $entityManager->saveEntity($voipRouter);
                }

                if (isset($voipRouterNeedToBeInactive[$sid])) {
                    unset($voipRouterNeedToBeInactive[$sid]);
                }
            }

            if (!empty($voipRouterNeedToBeInactive)) {
                $voipRouterRepository->markInactive($voipRouterNeedToBeInactive);
            }
        }
    }

    /**
     * Get Twilio Managers
     *
     * @return array
     */
    protected function getTwilioManagers()
    {
        if (empty($this->twilioManagers)) {
            $voipManager = $this->getContainer()->get('voipManager');

            $twilioConnectorList = $this->getContainer()->get('voipConnectionManager')->getActiveListByProviderName('Twilio');

            $this->twilioManagers = array();
            foreach ($twilioConnectorList as $connectorId => $connectorName) {
                $this->twilioManagers[$connectorId] = $voipManager->getConnectorManager($connectorId);
            }
        }

        return $this->twilioManagers;
    }

    /**
     * Get Active VoipRouter by connector in format array(Sid => espoId)
     *
     * @param  string $connectorId
     *
     * @return array
     */
    protected function getActiveVoipRoutersByConnector($connectorId)
    {
        $activeVoipRouterList = $this->getEntityManager()->getRepository('VoipRouter')->getListByConnector($connectorId);

        $list = array();
        foreach ($activeVoipRouterList as $id => $values) {
            if (isset($values['externalId'])) {
                $list[ $values['externalId'] ] = $values['id'];
            }
        }

        return $list;
    }

}
