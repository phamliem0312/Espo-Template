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

use Espo\Modules\Voip\Core\Utils\PhoneNumber;
use Espo\Core\Exceptions\{
    Forbidden,
    BadRequest,
};

class WebhookHandler extends \Espo\Modules\Voip\Bases\WebhookHandler
{
    public function run(array $data, $request)
    {
        $connector = $this->getConnector();
        $connectorManager = $this->getConnectorManager();
        $connectorData = $this->getConnectorData();
        $voipHelper = $this->getContainer()->get('voipHelper');

        $type = $request->getQueryParam('type');
        $postData = $data;

        if (empty($type)) {
            throw new BadRequest();
        }

        $twiml = $connectorManager->createTwimlBuilder();
        $voipRouterRepository = $this->getEntityManager()->getRepository('VoipRouter');

        switch ($type) {
            case 'dial': //outgoing call
                $phoneNumber = $_GET['phoneNumber'] ?? null;
                $callerId = $_GET['callerId'] ?? null;

                $voipRouter = $voipRouterRepository->getByName($callerId, $connector);
                if ($voipRouter) {
                    $postData['espoVoipRouterId'] = $voipRouter->get('id');
                }

                $postData['espoToPhoneNumber'] = $phoneNumber;
                $connectorManager->handleEvent($postData);

                $twiml->dial($callerId, $phoneNumber, array(
                    'timeout' => $connectorData->dialTimeout,
                    'record' => $connectorData->record,
                ), null, false, [
                    'routerId' => isset($voipRouter) ? $voipRouter->get('id') : null,
                ]);
                $this->printTwiml($twiml);
                break;

            case 'voice': //incoming call
                /* external call: PhoneNumber to PhoneNumber */
                $voipRouter = $voipRouterRepository->getByName($postData['To'], $connector);
                if ($voipRouter) {
                    $postData['espoVoipRouterId'] = $voipRouter->get('id');
                }

                $voipEvent = $connectorManager->handleEvent($postData);

                if (isset($_GET['isQueue']) && isset($postData['DialCallStatus']) && $postData['DialCallStatus'] == 'completed') {
                    $twiml->hangup();
                    $this->printTwiml($twiml);
                    return;
                }

                if (empty($voipRouter)) {
                    $this->twimlSay($twiml, 'phoneIsNotConfigured', 'VoipRouter');
                    $this->printTwiml($twiml);
                    return;
                }

                if (!isset($_GET['isQueue'])) { //init external incoming call
                    if ($voipRouter->get('greetingAtCallStart')) {
                        if ($voipRouter->get('greetingFileId')) {
                            $fileUrl = 'type=greeting&routerId=' . $voipRouter->get('id');
                            $twiml->play($fileUrl);
                        } else if ($voipRouter->get('greetingText')) {
                            $twiml->say($voipRouter->get('greetingText'));
                        }
                    }
                }

                $preferedUsers = array();
                if ($connectorData->preferAssignedUser) {
                    $preferedUsers = $this->getContainer()->get('voipHelper')->getPreferedUserList($voipEvent);
                }

                $ignoredUserList = $_GET['ignoredUsers'] ?? [];
                $nextUserData = $voipRouterRepository->getQueueNextUser($voipRouter, $preferedUsers, $ignoredUserList);

                if (empty($nextUserData)) {

                    if ($voipRouter->get('voicemail')) {
                        if ($voipRouter->get('voicemailGreetingFileId')) {
                            $fileUrl = 'type=voicemailGreeting&routerId=' . $voipRouter->get('id');
                            $twiml->play($fileUrl);
                        } else {
                            $this->twimlSay($twiml, 'noAvailableAgent', 'VoipRouter', $voipRouter->get('voicemailGreetingText'));
                        }

                        $twiml->record([
                            'action' => 'type=voicemail',
                            'timeout' => 10,
                        ]);
                        $this->printTwiml($twiml);
                        return;
                    }

                    if ($voipRouter->get('farewell')) {
                        if ($voipRouter->get('farewellFileId')) {
                            $fileUrl = 'type=farewell&routerId=' . $voipRouter->get('id');
                            $twiml->play($fileUrl);
                        } else if ($voipRouter->get('farewellText')) {
                            $twiml->say($voipRouter->get('farewellText'));
                        }
                    }

                    $twiml->hangup();
                    $this->printTwiml($twiml);
                    return;
                }

                //add a user to ignoredUsers
                $ignoredUserList[] = $nextUserData['id'];

                $isSipCall = $connectorManager->isSipPhoneNumber($nextUserData['phoneNumber']);
                $twiml->dial($postData['From'], $nextUserData['phoneNumber'], [
                    'timeout' => $connectorData->agentRingingTimeout,
                    'record' => $connectorData->record,
                ], 'type=voice&isQueue=1&'. http_build_query(['ignoredUsers' => $ignoredUserList]), $isSipCall, [
                    'routerId' => $voipRouter->get('id'),
                ]);
                $this->printTwiml($twiml);
                break;

            case 'sip':
                /* external call: SIP to PhoneNumber */
                if ($this->isHandleSipCall($postData['From'], $postData['To'])) {
                    $outgoingPhoneNumber = null;
                    $sipUserExt = $this->normalizeSipPhoneNumber($postData['From'], PhoneNumber::SEARCH_FORMAT);
                    $sipUserId = $voipHelper->findUser($sipUserExt, $connector);
                    if ($sipUserId) {
                        $sipUser = $this->getEntityManager()->getEntity('User', $sipUserId);
                        if ($sipUser) {
                            $outgoingPhoneNumber = $sipUser->get('voipUser');
                            $voipRouter = $voipRouterRepository->getByName($outgoingPhoneNumber, $connector);
                        }
                    }

                    if (empty($voipRouter)) {
                        $this->twimlSay($twiml, 'forbiddenExternalCalls', 'VoipRouter');
                        $this->printTwiml($twiml);
                        return;
                    }

                    /* sip number */
                    $postData['From'] = $this->normalizeSipPhoneNumber($postData['From']);

                    /* phone number, not a sip number */
                    $postData['To'] = $this->normalizeSipPhoneNumber($postData['To'], PhoneNumber::SIP_USER_FORMAT);

                    $postData['espoVoipRouterId'] = $voipRouter->get('id');
                    $voipEvent = $connectorManager->handleEvent($postData);

                    $twiml->dial($outgoingPhoneNumber, $postData['To'], [
                        'timeout' => $connectorData->dialTimeout,
                        'record' => $connectorData->record,
                    ], null, false, [
                        'espoFromPhoneNumber' => $postData['From'],
                        'espoToPhoneNumber' => $postData['To'],
                        'routerId' => $voipRouter->get('id'),
                    ]);
                    $this->printTwiml($twiml);
                    break;
                }

                /* internal call: SIP to SIP */
                $twiml->dial(null, $postData['To'], [
                    'timeout' => $connectorData->dialTimeout,
                    'record' => $connectorData->record,
                ], null, true);
                $this->printTwiml($twiml);
                break;

            case 'sipVoiceStatus':
                if ($this->isHandleSipCall($postData['From'], $postData['To'])) {
                    $postData['From'] = $this->normalizeSipPhoneNumber($postData['From']);
                    $postData['To'] = $this->normalizeSipPhoneNumber($postData['To']);
                    $postData['espoVoipRouterId'] = $_GET['routerId'] ?? null;

                    $connectorManager->handleEvent($postData);
                }
                break;

            case 'voiceStatus':
                $postData['espoFromPhoneNumber'] = $_GET['espoFromPhoneNumber'] ?? null;
                $postData['espoToPhoneNumber'] = $_GET['espoToPhoneNumber'] ?? null;
                $postData['espoVoipRouterId'] = $_GET['routerId'] ?? null;

                $connectorManager->handleEvent($postData);
                break;

            case 'voicemail':
                $twiml->hangup();

                $voipEvent = $connectorManager->handleEvent($postData);

                //create a call for a voicemail
                $this->proceedVoicemail($postData, $voipEvent);

                $this->printTwiml($twiml);
                break;

            case 'message':
            case 'messageStatus':
                $connectorManager->handleMessage($postData);
                break;

            case 'greeting':
            case 'farewell':
            case 'voicemailGreeting':
                if (empty($_GET['routerId'])) {
                    throw new NotFound();
                }

                $this->proceedGreeting($_GET['routerId'], $type);
                break;

            case 'voiceFallback':
            case 'messageFallback':
            case 'sipVoiceFallback':
                $errorCode = isset($postData['ErrorCode']) ? $postData['ErrorCode'] : '';
                $errorUrl = isset($postData['ErrorUrl']) ? $postData['ErrorUrl'] : '';

                $GLOBALS['log']->error('Twilio Error ['. $type .']: Error code: ' . $errorCode . ', Error URL: ' . $errorUrl . ' Post Data: ' . print_r($postData, true));

                if ($type == 'voiceFallback') {
                    $this->twimlSay($twiml, 'applicationError', 'VoipRouter');
                    $this->printTwiml($twiml);
                }
                break;

            default:
                throw new BadRequest('This type ['.$type.'] cannot be found.');
                break;
        }
    }

    protected function normalizeSipPhoneNumber($phoneNumber, $type = null)
    {
        if (!$type) {
            $type = PhoneNumber::SIP_FORMAT;
        }

        return $this->getConnectorManager()->formatPhoneNumber($phoneNumber, $type);
    }

    protected function isHandleSipCall($fromNumber, $toNumber)
    {
        $connectorManager = $this->getConnectorManager();

        $phoneNumberTypeFrom = $connectorManager->getPhoneNumberType($fromNumber);
        $phoneNumberTypeTo = $connectorManager->getPhoneNumberType($toNumber);

        if ($phoneNumberTypeFrom == 'sip' && $phoneNumberTypeTo == 'phone') {
            return true;
        }

        if ($phoneNumberTypeFrom == 'phone' && $phoneNumberTypeTo == 'sip') {
            return true;
        }

        /* Do not handle calls: sip to sip */
        return false;
    }

    protected function twimlSay($twiml, $label, $category = 'Global', $text = null)
    {
        if (empty($text)) {
            $text = $this->getContainer()->get('language')->translate($label, 'labels', $category);
        }

        return $twiml->say($text);
    }

    protected function proceedVoicemail(array $postData, $voipEvent)
    {
        $entityManager = $this->getEntityManager();
        $voipEventHelper = $this->getContainer()->get('voipEventHelper');

        $voipRouter = $entityManager->getRepository('VoipRouter')->getByName($postData['To'], $this->connector);
        if (!isset($voipRouter)) {
            $GLOBALS['log']->error('Twilio voicemail: VoipRouter is not found for VoipEvent['.$voipEvent->get('id').'].');
            return false;
        }

        $preferedUsers = $this->getContainer()->get('voipHelper')->getPreferedUserList($voipEvent);
        $assignedUserId = empty($preferedUsers) ? $voipRouter->get('callAssignToId') : $preferedUsers[0];

        $callEntity = $entityManager->getEntity('Call');
        $callEntity->set(array(
            'name' => $voipEventHelper->getCallTitle($voipEvent, 'voicemailNames'),
            'isVoicemail' => true,
            'assignedUserId' => $assignedUserId,
            'teamsIds' => array($voipRouter->get('teamId')),
        ));

        $callId = $entityManager->saveEntity($callEntity);

        $voipEvent->set(array(
            'callId' => $callId,
            'processed' => true,
        ));
        $entityManager->saveEntity($voipEvent);

        $voipEventHelper->addCallFromVoipEvent(array(
            'eventId' => $voipEvent->get('id'),
        ));

        $voipEventHelper->addCallReminders($callEntity, $voipRouter->get('voicemailNotifications'));
    }

    protected function proceedGreeting($voipRouterId, $type)
    {
        $voipRouter = $this->getEntityManager()->getEntity('VoipRouter', $voipRouterId);
        if (!isset($voipRouter)) {
            throw new NotFound();
        }

        switch ($type) {
            case 'greeting':
                $fileId = $voipRouter->get('greetingFileId');
                break;

            case 'voicemailGreeting':
                $fileId = $voipRouter->get('voicemailGreetingFileId');
                break;

            case 'farewell':
                $fileId = $voipRouter->get('farewellFileId');
                break;
        }

        if (!isset($fileId)) {
            throw new NotFound();
        }

        $attachment = $this->getEntityManager()->getEntity('Attachment', $fileId);
        $fileName = $this->getEntityManager()->getRepository('Attachment')->getFilePath($attachment);
        if (!file_exists($fileName)) {
            throw new NotFound();
        }

        if ($attachment->get('type')) {
            header('Content-Type: ' . $attachment->get('type'));
        }

        header('Pragma: public');
        header('Content-Length: ' . filesize($fileName));
        ob_clean();
        flush();
        readfile($fileName);
        exit;
    }

    protected function printTwiml($twiml)
    {
        $GLOBALS['log']->debug('Twilio Twiml response: ' . $twiml);

        header("Content-type: text/xml; charset=utf-8");
        print $twiml;
        exit;
    }
}
