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

use Espo\ORM\Entity;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Espo\Core\Exceptions\Error;
use Espo\Modules\Voip\Core\Utils\PhoneNumber;

class Manager extends \Espo\Modules\Voip\Bases\Manager
{
    private $messageListerner;

    protected $webhookUrl = '/api/v1/Voip/webhook/{CONNECTOR}/{ACCESS_KEY}';

    protected $twilioAppName = 'EspoCRM.{CONNECTOR}';

    public function createTwimlBuilder()
    {
        return new TwimlBuilder($this->getApiClient(), $this);
    }

    protected function getMessageListener()
    {
        if (!isset($this->messageListerner)) {
            $this->messageListerner = new MessageListener($this->getContainer());
        }

        return $this->messageListerner;
    }

    protected function normalizeOptions(array $options, $connector = null)
    {
        return [
            'accountSid' => $options['twilioAccountSid'],
            'authToken' => $options['twilioAuthToken'],
            'callbackUrl' => $this->getCallbackUrl($connector),
            'applicationSid' => $options['twilioApplicationSid'] ?? null,
        ];
    }

    /**
     * Get callback URL of EspoCRM
     *
     * @param  string $connector
     *
     * @return string
     */
    public function getCallbackUrl($connector = null)
    {
        $connector = !empty($connector) ? $connector : $this->getConnector();

        $integrationEntity = $this->loadIntegrationEntity($connector);
        if ($integrationEntity) {
            $data = (array) $integrationEntity->get('data');
            $webhookUrl = $this->getContainer()->get('config')->get('siteUrl') . $this->webhookUrl;
            $webhookUrl = str_replace('{CONNECTOR}', $integrationEntity->get('id'), $webhookUrl);
            $webhookUrl = str_replace('{ACCESS_KEY}', $data['accessKey'], $webhookUrl);

            return $webhookUrl;
        }
    }

    /**
     * Handle an event
     *
     * @param  array  $eventData
     *
     * @return \Espo\Modules\Voip\Entities\VoipEvent
     */
    public function handleEvent(array $eventData = null)
    {
        $eventListener = $this->getEventListener();
        $eventListener->setConnectorManager($this);
        return $eventListener->handle($eventData);
    }

    /**
     * Handle a message
     *
     * @param  array  $messageData
     *
     * @return \Espo\Modules\Voip\Entities\VoipMessage
     */
    public function handleMessage(array $messageData = null)
    {
        $messageListerner = $this->getMessageListener();
        $messageListerner->setConnectorManager($this);
        return $messageListerner->handle($messageData);
    }

    /*
    ??????
    public function isSms()
    {

    }*/

    public function testCurrentConnection()
    {
        return $this->getApiClient()->testConnection();
    }

    public function testConnection(array $data)
    {
        $apiClient = $this->createApiClient([
            'accountSid' => $data['twilioAccountSid'],
            'authToken' => $data['twilioAuthToken'],
        ]);

        return $apiClient->testConnection();
    }

    public function dial(array $data)
    {
        $connectorData = $this->getConnectorData();
        $data['userPhoneNumber'] = $this->formatPhoneNumber($data['userPhoneNumber'], PhoneNumber::DIAL_FORMAT);

        return $this->getApiClient()->makeCall($data['callerId'], $data['toPhoneNumber'], $data['userPhoneNumber'], [
            'record' => $connectorData->record ? 'true' : 'false',
            'timeout' => $connectorData->dialTimeout,
        ]);
    }

    /**
     * Send a message
     *
     * @param  string     $fromNumber
     * @param  string     $toNumber
     * @param  string     $text
     * @param  array|null $options
     *
     * @return string - external ID
     */
    public function sendMessage($fromNumber, $toNumber, $text = null, array $options = null)
    {
        return $this->getApiClient()->sendMessage($fromNumber, $toNumber, $text, $options);
    }

    /**
     * Phone number: get phone number list
     *
     * @return array
     */
    public function getPhoneNumberList()
    {
        return $this->getApiClient()->getPhoneNumberList();
    }

    /**
     * Get a phone number by $sid
     *
     * @return array
     */
    public function getPhoneNumber($sid)
    {
        return $this->getApiClient()->getPhoneNumber($sid);
    }

    /**
     * Phone number: update phone number details
     *
     * @param  string $sid
     * @param  array  $data
     *
     * @return bool
     */
    public function updatePhoneNumber($sid, array $data)
    {
        $client = $this->getApiClient();

        return $client->updatePhoneNumber($sid, $data);
    }

    public function getApplication($appSid)
    {
        try {
            return $this->getApiClient()->getApplication($appSid);
        } catch (\Throwable $e) {
            $GLOBALS['log']->debug('Twilio Application ['. $appSid .'] is not found, details: ' . $e->getMessage());
        }
    }

    public function getApplicationByName($appName)
    {
        try {
            return $this->getApiClient()->findApplication($appName);
        } catch (\Throwable $e) {
            $GLOBALS['log']->debug('Twilio Application ['. $appName .'] is not found, details: ' . $e->getMessage());
        }
    }

    public function createApplication($appName, array $data)
    {
        return $this->getApiClient()->createApplication($appName, $data);
    }

    public function updateApplication($appId, array $data)
    {
        return $this->getApiClient()->updateApplication($appId, $data);
    }

    public function deleteApplication($appId)
    {
        return $this->getApiClient()->deleteApplication($appId);
    }

    public function getSipDomainlist()
    {
        return $this->getApiClient()->getSipDomainlist();
    }

    public function getSipDomainsByParams(array $data)
    {
        $apiClient = $this->createApiClient([
            'accountSid' => $data['twilioAccountSid'],
            'authToken' => $data['twilioAuthToken'],
        ]);

        return $apiClient->getSipDomainlist();
    }

    public function getSipDomain($sid)
    {
        return $this->getApiClient()->getSipDomain($sid);
    }

    public function updateSipDomain($sid, array $data = [])
    {
        return $this->getApiClient()->updateSipDomain($sid, $data);
    }

    public function normalizeTwilioAppName($connector = null)
    {
        $connector = !empty($connector) ? $connector : $this->getConnector();

        return str_replace('{CONNECTOR}', $connector, $this->twilioAppName);
    }

    public function activateTwilioApplication(Entity $entity)
    {
        $data = $entity->get('data');
        $connector = $entity->get('id');
        $twilioAppName = $this->normalizeTwilioAppName($connector);

        if (isset($data->twilioApplicationSid)) {
            $application = $this->getApplication($data->twilioApplicationSid);
        }

        if (!isset($application)) {
            $application = $this->getApplicationByName($twilioAppName);
            $data->twilioApplicationSid = null;
        }

        $callbackUrl = $this->getCallbackUrl();
        $applicationParams = array(
            'friendlyName' => $twilioAppName,
            'voiceUrl' => $callbackUrl . '?type=voice',
            'voiceFallbackUrl' => $callbackUrl . '?type=voiceFallback',
            'voiceMethod' => 'POST',
            'statusCallback' => $callbackUrl . '?type=voiceStatus',
            'statusCallbackMethod' => 'POST',
            'smsUrl' => $callbackUrl . '?type=message',
            'smsFallbackUrl' => $callbackUrl . '?type=messageFallback',
            'smsMethod' => 'POST',
            'messageStatusCallback' => $callbackUrl . '?type=messageStatus',
        );

        if (!isset($application)) { //not found
            $application = $this->createApplication($twilioAppName, $applicationParams);
        } else {
            $application = $this->updateApplication($application['sid'], $applicationParams);
        }

        if (!isset($data->twilioApplicationSid)) {
            $entityManager = $this->getContainer()->get('entityManager');
            $integration = $entityManager->getEntity('Integration', $connector);

            $data->twilioApplicationSid = $application['sid'];
            $data->messageSupport = true;
            $integration->set('data', $data);
            $entity->set('data', $data);
            $integration->skipTwilioManageApplicationsHook = true;

            $entityManager->saveEntity($integration);
        }
    }

    public function activateSipDomain($sipDomainSid)
    {
        $sipDomain = $this->getSipDomain($sipDomainSid);
        if (empty($sipDomain['sid'])) {
            throw new Error('SIP domain ' . $sipDomainSid . 'is not found. Please remove it from the list and try again.');
        }

        $callbackUrl = $this->getCallbackUrl();
        $postData = array(
            'voiceMethod' => 'POST',
            'voiceUrl' => $callbackUrl . '?type=sip',
            'voiceStatusCallbackMethod' => 'POST',
            'voiceStatusCallbackUrl' => $callbackUrl . '?type=sipVoiceStatus',
            'voiceFallbackMethod' => 'POST',
            'voiceFallbackUrl' => $callbackUrl . '?type=sipVoiceFallback',
        );

        $save = false;
        foreach ($postData as $name => $value) {
            if (!isset($sipDomain[$name]) || $sipDomain[$name] != $value) {
                $save = true;
                break;
            }
        }

        if ($save) {
            $result = $this->updateSipDomain($sipDomainSid, $postData);
            if (empty($result['sid'])) {
                throw new Error('Error saving the SIP domain ' . $sipDomainSid . '.');
            }
        }

        return true;
    }
}
