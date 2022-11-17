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

namespace Espo\Modules\Voip\Providers\Starface\Api;

require_once('application/Espo/Modules/Voip/Providers/Starface/Api/StarfaceApiFork/vendor/autoload.php');

use Espo\Core\Utils\Json;
use Espo\Core\Exceptions\Error;

class Starface
{
    protected $options;

    private $client;

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
            $this->client = new \Starface\StarFace($options['user'], $this->getAuthToken(), $options['baseUrl'], $options['callback']);
        }

        return $this->client;
    }

    public function getAuthToken()
    {
        $options = $this->getOptions();

        if (version_compare($options['starfaceVersion'], '6.4.2') >= 0) {
            $shaPassword = strtolower(hash('sha512', $options['password']));
            return $options['user'] . ':' . strtolower( hash('sha512', $options['user'] . '*' . $shaPassword) );
        }

        return md5($options['user'] . '*' . $options['password']);
    }

    public function login()
    {
        $client = $this->getClient();
        $loginResult = $client->login();

        $service = $client->getServiceApi();
        $isSubscribed = $service->subscribeEvent('ucp.v22.events.call');
        if ($isSubscribed) {
            $GLOBALS['log']->info('Starface: User['.$this->options['user'].'] subscribed to events ucp.v22.events.call.');
        } else {
            $GLOBALS['log']->error('Starface: User['.$this->options['user'].'] has NOT been subscribed to events ucp.v22.events.call.');
        }

        $loginResult &= $isSubscribed;

        return (bool) $loginResult;
    }

    public function logout()
    {
        $client = $this->getClient();
        return $client->logout();
    }

    public function keepAlive($autoLogin = true)
    {
        $client = $this->getClient();

        $GLOBALS['log']->info('Starface: KeepAlive user online ['.$this->options['user'].'].');
        $isOnline = $client->keepAlive();
        if (!$isOnline && $autoLogin) {
            $GLOBALS['log']->info('Starface: User was offline. Auth user ['.$this->options['user'].'].');
            $isOnline = $this->login();
        }

        return $isOnline;
    }

    public function placeCallWithPhone($destinationNumber, $phoneId = null, $callId = null)
    {
        $this->keepAlive();
        $client = $this->getClient();
        $callApi = $client->getCallApi();

        return $callApi->placeCall($destinationNumber, $phoneId, $callId);
    }

    public function hangupCall($callId)
    {
        $this->keepAlive();
        $client = $this->getClient();
        $callApi = $client->getCallApi();

        return $callApi->hangupCall($callId);
    }

    public function receiveCallState()
    {
        $this->keepAlive();
        $client = $this->getClient();
        $callApi = $client->getCallApi();

        return $callApi->receiveCallState();
    }

    public function getAvailablePhoneNumbers()
    {
        //$this->keepAlive();
        $client = $this->getClient();
        $phoneApi = $client->getPhoneApi();

        $phoneNumbers = $phoneApi->getPhoneIds();
        if (!is_array($phoneNumbers)) {
            $phoneNumbers = array();
        }

        foreach ($phoneApi->getAvailableDisplayNumbers() as $numberDetails) {
            if (!empty($numberDetails['number'])) {
                $phoneNumbers[] = $numberDetails['number'];
            }
        }

        return $phoneNumbers;
    }
}
