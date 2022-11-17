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

use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;

class ApiClient
{
    protected $options;

    private $client;

    private $twimlVoice;

    private $twimlMessaging;

    protected $maxResultSize = 1000;

    protected $fieldMaps = array(
        'phoneNumber' => array(
            'sid',
            'accountSid',
            'friendlyName',
            'phoneNumber',
            'voiceUrl',
            'voiceMethod',
            'voiceFallbackUrl',
            'voiceFallbackMethod',
            'voiceCallerIdLookup',
            'dateCreated',
            'dateUpdated',
            'smsUrl',
            'smsMethod',
            'smsFallbackUrl',
            'smsFallbackMethod',
            'addressRequirements',
            'capabilities',
            'statusCallback',
            'statusCallbackMethod',
            'voiceApplicationSid',
            'smsApplicationSid',
            'trunkSid',
        ),
        'application' => array(
            'sid',
            'accountSid',
            'friendlyName',
            'dateCreated',
            'dateUpdated',
            'voiceUrl',
            'voiceFallbackUrl',
            'voiceMethod',
            'statusCallback',
            'statusCallbackMethod',
            'smsUrl',
            'smsFallbackUrl',
            'smsMethod',
            'messageStatusCallback',
            'voiceCallerIdLookup',
        ),
        'sipDomain' => array(
            'sid',
            'accountSid',
            'domainName',
            'friendlyName',
            'dateCreated',
            'dateUpdated',
            'voiceMethod',
            'voiceUrl',
            'voiceStatusCallbackMethod',
            'voiceStatusCallbackUrl',
            'voiceFallbackMethod',
            'voiceFallbackUrl',
            'sipRegistration',
        ),
    );

    public function __construct($options)
    {
        $this->options = $options;
    }

    protected function getOptions()
    {
        return $this->options;
    }

    public function getClient()
    {
        if (!isset($this->client)) {
            $options = $this->getOptions();
            $this->client = new \Twilio\Rest\Client($options['accountSid'], $options['authToken']);
        }

        return $this->client;
    }

    public function getTwimlVoice()
    {
        if (!isset($this->twimlVoice)) {
            $this->twimlVoice = new \Twilio\TwiML\VoiceResponse();
        }

        return $this->twimlVoice;
    }

    public function getTwimlMessaging()
    {
        if (!isset($this->twimlMessaging)) {
            $this->twimlMessaging = new \Twilio\TwiML\MessagingResponse();
        }

        return $this->twimlMessaging;
    }

    /**
     * Normalize responce data from Twilio Object to array
     *
     * @param  object $returdData
     * @param  array  $fieldMap
     *
     * @return array
     */
    protected function normalizeResponse($response, array $fieldMap)
    {
        if (is_array($response)) {
            $list = array();
            foreach ($response as $responseItem) {
                $list[$responseItem->sid] = $this->normalizeResponse($responseItem, $fieldMap);
            }
            return $list;
        }

        $data = new \stdClass();
        foreach ($fieldMap as $twilioFieldName) {
            try {
                $data->$twilioFieldName = $response->$twilioFieldName;
            } catch (\Exception $e) {
                $data->$twilioFieldName = null;
            }
        }

        return Util::objectToArray($data);
    }

    public function testConnection()
    {
        $client = $this->getClient();

        try {
            $account = $client->account->fetch();
            $status = $account->status;
        } catch (\Exception $e) {
            $error = $e->getMessage();
            if (strstr($error, 'The requested resource')) {
                $error = null;
            }
            throw new Error($error);
        }

        if (isset($status) && $status == 'active') {
            return true;
        }

        throw new Error();
    }

    /**
     * Get phone number list
     *
     * @return array
     */
    public function getPhoneNumberList()
    {
        $client = $this->getClient();
        $phoneNumbers = $client->incomingPhoneNumbers->read([], null, $this->maxResultSize);

        return $this->normalizeResponse($phoneNumbers, $this->fieldMaps['phoneNumber']);
    }

    public function getPhoneNumber($sid)
    {
        $client = $this->getClient();
        $phoneNumber = $client->incomingPhoneNumbers($sid)->fetch();

        return $this->normalizeResponse($phoneNumber, $this->fieldMaps['phoneNumber']);
    }


    public function updatePhoneNumber($sid, array $data)
    {
        $client = $this->getClient();
        $phoneNumber = $client->incomingPhoneNumbers($sid);

        if ($phoneNumber) {
            $phoneNumber->update($data);
            return $this->normalizeResponse($phoneNumber, $this->fieldMaps['phoneNumber']);
        }
    }

    /**
     * Make a call
     * @see https://www.twilio.com/docs/api/rest/call
     * @see https://www.twilio.com/docs/api/rest/making-calls
     * @see https://www.twilio.com/docs/api/twiml/dial
     *
     * @param  string $fromNumber - a valid Twilio number
     * @param  string $toNumber - Call to this number
     * @param  string $userNumber - User phone number
     *
     * @return string
     */
    public function makeCall($fromNumber, $toNumber, $userNumber, array $params = null)
    {
        $client = $this->getClient();
        $options = $this->getOptions();

        $dialParams = array(
            'method' => 'POST',
            'url' => $options['callbackUrl'] . '?type=dial&callerId=' . urlencode($fromNumber) . '&phoneNumber=' . urlencode($toNumber),
            'fallbackUrl' => $options['callbackUrl'] . '?type=voiceFallback',
            'fallbackMethod' => 'POST',
            'statusCallback' => $options['callbackUrl'] . '?type=voiceStatus',
            'statusCallbackMethod' => 'POST',
            'statusCallbackEvent' => array('ringing', 'answered', 'completed'),
        );

        if (!empty($params)) {
            $dialParams = array_merge($dialParams, $params);
        }

        $call = $client->calls->create($userNumber, $fromNumber, $dialParams);

        try {
            return $call->sid;
        } catch (\Exception $e) {}
    }

    /**
     * Send message (SMS/MMS)
     * @see  https://www.twilio.com/docs/api/rest/message
     * @see  https://www.twilio.com/docs/api/rest/sending-messages
     * @see  https://www.twilio.com/docs/api/twiml/sms/twilio_request
     *
     * @param  string $fromNumber - From a valid Twilio number
     * @param  string $toNumber - Text this number
     * @param  string $text
     * @param  array  $params
     *
     * @return string - message id
     */
    public function sendMessage($fromNumber, $toNumber, $text = null, array $params = null)
    {
        $client = $this->getClient();
        $options = $this->getOptions();

        $messageOptions = array(
            'from' => $fromNumber,
        );

        if (isset($options['applicationSid'])) {
            $messageOptions['applicationSid'] = $options['applicationSid'];
        }

        if (isset($text)) {
            $messageOptions['body'] = $text;
        }

        if (!empty($params['mediaUrls'])) {
            $mediaUrls = $params['mediaUrls'];
            unset($params['mediaUrls']);

            if (count($mediaUrls) == 1) {
                $params['mediaUrl'] = $mediaUrls[0];
            } else {
                $params['mediaUrl'] = $mediaUrls;
            }
        }

        if (!empty($params) && is_array($params)) {
            $messageOptions = array_merge($messageOptions, $params);
        }

        $message = $client->messages->create($toNumber, $messageOptions);

        return $message->sid;
    }

    /**
     * Find Application by name
     *
     * @param  string $appName
     *
     * @return array | null
     */
    public function findApplication($appName)
    {
        $client = $this->getClient();

        $applicationList = $client->applications->read([
          'friendlyName' => $appName
        ], 1);

        foreach($applicationList as $_application) {
            $application = $_application;
        }

        if (isset($application)) {
            return $this->normalizeResponse($application, $this->fieldMaps['application']);
        }
    }

    /**
     * Get Applicaiton by Sid
     *
     * @param  string $appSid
     *
     * @return array
     */
    public function getApplication($appSid)
    {
        $client = $this->getClient();
        $application = $client->applications($appSid)->fetch();

        $data = $this->normalizeResponse($application, $this->fieldMaps['application']);
        if (isset($data['dateCreated'])) {
            return $data;
        }
    }

    /**
     * Create application
     *
     * @param  string $appName
     * @param  array  $data
     *
     * @return array
     */
    public function createApplication($appName, array $data)
    {
        $client = $this->getClient();
        $application = $client->applications->create($appName, $data);

        return $this->normalizeResponse($application, $this->fieldMaps['application']);
    }

    /**
     * Update Application configuration
     *
     * @param  string $appId
     * @param  array  $data
     *
     * @return array
     */
    public function updateApplication($appId, array $data)
    {
        $client = $this->getClient();
        $application = $client->applications($appId)->update($data);

        return $this->normalizeResponse($application, $this->fieldMaps['application']);
    }

    /**
     * Delete Application
     *
     * @param  string $appId
     *
     * @return bool
     */
    public function deleteApplication($appId)
    {
        $this->getClient()->applications($appId)->delete();

        return true;
    }

    /**
     * Get the list of available SIP domains
     * @return array
     */
    public function getSipDomainlist()
    {
        $client = $this->getClient();
        $domains = $client->sip->domains->read();

        $normalizedDoaminList= [];
        foreach ($domains as $record) {
            $normalizedDoaminList[] = $this->normalizeResponse($record, $this->fieldMaps['sipDomain']);
        }

        return $normalizedDoaminList;
    }

    public function getSipDomain($sid)
    {
        $client = $this->getClient();
        $domain = $client->sip->domains($sid)->fetch();

        return $this->normalizeResponse($domain, $this->fieldMaps['sipDomain']);
    }

    public function updateSipDomain($sid, array $data = [])
    {
        $client = $this->getClient();
        $domain = $client->sip->domains($sid)->update($data);

        return $this->normalizeResponse($domain, $this->fieldMaps['sipDomain']);
    }
}
