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

class TwimlBuilder
{
    private $apiClient;

    private $connectorManager;

    private $response;

    public function __construct($apiClient, $connectorManager)
    {
        $this->apiClient = $apiClient;
        $this->connectorManager = $connectorManager;
    }

    protected function getApiClient()
    {
        return $this->apiClient;
    }

    protected function getConnectorManager()
    {
        return $this->connectorManager;
    }

    protected function getResponse()
    {
        if (!isset($this->response)) {
            $this->response = $this->getApiClient()->getTwimlVoice();
        }

        return $this->response;
    }

    public function toXml()
    {
        return $this->getResponse()->asXML();
    }

    public function __toString()
    {
        return $this->toXml();
    }

    protected function normalizePhoneNumber($phoneNumber)
    {
        if (empty($phoneNumber)) {
            return $phoneNumber;
        }

        $phoneNumber = trim($phoneNumber);
        return $this->getConnectorManager()->formatPhoneNumber($phoneNumber, PhoneNumber::DIAL_FORMAT);
    }

    protected function normalizeUrl($url)
    {
        $callbackUrl = $this->getConnectorManager()->getCallbackUrl();

        $url = trim($url);
        preg_replace('/^&/', '', $url);
        preg_replace('/^\?/', '', $url);

        return $callbackUrl . '?' . $url;
    }

    /**
     * Dial in twiml format
     * @see https://www.twilio.com/docs/voice/twiml/dial
     * @param  string     $fromNumber
     * @param  string     $toNumber
     * @param  array|null $options
     * @param  string     $action
     * @return \Espo\Modules\Voip\Providers\Twilio\TwimlBuilder
     */
    public function dial($fromNumber, $toNumber, array $options = null, $action = null, $useSip = false, array $statusParams = [])
    {
        $response = $this->getResponse();

        //format phone number
        $callerId = $this->normalizePhoneNumber($fromNumber);
        $phoneNumber = $this->normalizePhoneNumber($toNumber);

        $dialData = [];

        if (!empty($callerId)) {
            $dialData['callerId'] = $callerId;
        }

        if (!empty($options)) {
            $dialData = array_merge($dialData, $options);
        }

        if ($action) {
            $dialData['action'] = $this->normalizeUrl($action);
        }

        $dial = $response->dial(NULL, $dialData);

        if ($useSip) {
            $statusParams['type'] = 'sipVoiceStatus';

            $dial->sip($phoneNumber, array(
                'statusCallbackEvent' => 'ringing answered completed',
                'statusCallback' => $this->normalizeUrl(http_build_query($statusParams)),
            ));
        } else {
            $statusParams['type'] = 'voiceStatus';

            $dial->number($phoneNumber, array(
                'statusCallbackEvent' => 'ringing answered completed',
                'statusCallback' => $this->normalizeUrl(http_build_query($statusParams)),
            ));
        }

        return $this;
    }

    /**
     * Hangup a call
     * @see https://www.twilio.com/docs/voice/twiml/hangup
     * @return \Espo\Modules\Voip\Providers\Twilio\TwimlBuilder
     */
    public function hangup()
    {
        $response = $this->getResponse();
        $response->hangup();

        return $this;
    }

    /**
     * Text to speech
     * @see https://www.twilio.com/docs/voice/twiml/say
     * @param  string $text
     * @param  array  $params
     * @return \Espo\Modules\Voip\Providers\Twilio\TwimlBuilder
     */
    public function say($text, array $params = [])
    {
        $response = $this->getResponse();
        $response->say($text, $params);

        return $this;
    }

    /**
     * Play a media
     * @see https://www.twilio.com/docs/voice/twiml/play
     * @param  string $fileUrl
     * @param  array  $params
     * @return \Espo\Modules\Voip\Providers\Twilio\TwimlBuilder
     */
    public function play($fileUrl, array $params = [])
    {
        $response = $this->getResponse();

        $normalizedFileUrl = $this->normalizeUrl($fileUrl);
        $response->play($normalizedFileUrl, $params);

        return $this;
    }

    /**
     * Record a call
     * @see https://www.twilio.com/docs/voice/twiml/record
     * @param  array  $params
     * @return \Espo\Modules\Voip\Providers\Twilio\TwimlBuilder
     */
    public function record(array $params = [])
    {
        $response = $this->getResponse();

        if (isset($params['action'])) {
            $params['action'] = $this->normalizeUrl($params['action']);
        }

        $response->record($params);

        return $this;
    }
}
