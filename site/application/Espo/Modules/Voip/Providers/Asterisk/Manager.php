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

namespace Espo\Modules\Voip\Providers\Asterisk;

use Espo\Core\Utils\Json;
use Espo\Core\Api\Request;
use Espo\Core\Exceptions\Error;
use PAMI\Message\Event\EventMessage;

class Manager extends \Espo\Modules\Voip\Bases\Manager
{
    private $amiClient;

    protected $eventListener;

    protected $defaultConnector = 'Asterisk';

    protected $defaultAsteriskVersion = '13';

    protected function getEventListener()
    {
        if (!isset($this->eventListener)) {
            //getConnectorData
            $connectorData = $this->getConnectorData();
            $asteriskVersion = !empty($connectorData->asteriskVersion) ? $connectorData->asteriskVersion : $this->defaultAsteriskVersion;

            $className = '\\Espo\\Custom\\Modules\\Voip\\Providers\\Asterisk\\EventListeners\\Asterisk' . $asteriskVersion;
            if (!class_exists($className)) {
                $className = '\\Espo\\Modules\\Voip\\Providers\\Asterisk\\EventListeners\\Asterisk' . $asteriskVersion;
                if (!class_exists($className)) {
                    throw new Error('Event listener for Asterisk server ['.$asteriskVersion.'] is not found.');
                }
            }

            $this->eventListener = new $className($this->getContainer());
        }

        return $this->eventListener;
    }

    protected function createEventListener()
    {
    }

    protected function normalizeOptions(array $options, $connector = null)
    {
        $user = $this->getContainer()->get('user');
        if (isset($user)) {
            $options['user'] = [
                'id' => $user->get('id'),
            ];

            foreach ($user->toArray() as $name => $value) {
                if (stristr($name, 'voip')) {
                   $options['user'][$name] =  $value;
                }
            }
        }

        return $options;
    }

    /**
     * Start event listener
     *
     * @return void
     */
    public function startEventListener()
    {
        $amiClient = $this->getApiClient();
        $pamiClient = $amiClient->getPamiClient();

        $listener = $this->getEventListener();
        $listener->setConnectorManager($this);
        $pamiClient->registerEventListener(array($listener, 'handle'));

        $amiClient->connect();

        // Main loop
        $time = time();
        while((time() - $time) < 60) {
            $pamiClient->process();
            usleep(20000);
        }

        $amiClient->disconnect();
    }

    public function startServiceEventListener($initialization = true)
    {
        $amiClient = $this->getApiClient();
        $pamiClient = $amiClient->getPamiClient();

        if ($initialization) {
            $listener = $this->getEventListener();
            $listener->setConnectorManager($this);
            $pamiClient->registerEventListener(array($listener, 'handle'));
        }

        $isConnected = false;
        try {
            $amiClient->connect();
            $isConnected = true;
        } catch (\Exception $e) {
            $GLOBALS['log']->warning('Asterisk EventListener service: Failed connecting to Asterisk server, details: '.$e->getMessage());
        }

        if ($isConnected) {
            // Main loop
            while (!connection_aborted()) {
                try {
                    $pamiClient->process();
                } catch (\Exception $e) {
                    $amiClient->disconnect();
                    $isConnected = false;
                    break;
                }

                usleep(20000);
            }
        }

        if (!$isConnected) {
            sleep(30);
            $this->startServiceEventListener(false);
        }
    }

    public function testConnection(array $data)
    {
        return $this->createApiClient($data)->testConnection();
    }

    public function dial(array $data)
    {
        return $this->getApiClient()->dial([
            'priority' => '1',
            'extension' => $data['toPhoneNumber'],
            'callerId' => $data['callerId'],
        ]);
    }
}
