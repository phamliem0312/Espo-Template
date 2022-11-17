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

use PAMI\Message\Event\EventMessage;
use PAMI\Listener\IEventListener;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Util;

class ApiClient
{
    private $pamiClient;

    private $amiOptionList = array(
        'host',
        'scheme',
        'port',
        'username',
        'secret',
        'connect_timeout',
        'read_timeout',
    );

    protected $options;

    protected $isConnected = false;

    protected $lastActionId = null;

    /**
     * List of required fields for __construct method of action class
     * Be sure with the right field order
     *
     * @var array
     */
    protected $actionMap = array(
        'originate' => array(
            'channel',
        ),
        'hangup' => array(
            'channel',
        ),
    );

    public function __construct(array $options)
    {
        $this->options = $options;
    }

    protected function __desctuct()
    {
        $this->disconnect();
    }

    /**
     * Get AMI Client
     *
     * @return \PAMI\Client\Impl\ClientImpl
     */
    public function getPamiClient()
    {
        if (!isset($this->pamiClient)) {
            $this->pamiClient = new \Espo\Modules\Voip\Providers\Asterisk\Ami\PAMI\Client\Impl\ClientImpl($this->getAmiOptions());
            //$this->pamiClient = new \PAMI\Client\Impl\ClientImpl($this->getAmiOptions());
        }

        return $this->pamiClient;
    }

    /**
     * Get and normalize AMI options
     *
     * @return array
     */
    protected function getAmiOptions()
    {
        $amiOptions = array();
        foreach ($this->amiOptionList as $name) {
            $cName = Util::toCamelCase($name);
            if (isset($this->options[$cName])) {
                $amiOptions[$name] = $this->options[$cName];
            }
        }

        if (!strstr($amiOptions['scheme'], '://')) {
            $amiOptions['scheme'] .= '://';
        }

        return $amiOptions;
    }

    /**
     * Connect to Asterisk server
     *
     * @return void
     */
    public function connect()
    {
        if (!$this->isConnected) {
            try {
                $res = $this->getPamiClient()->open();
                $this->isConnected = true;
            } catch (\Exception $e) {
                throw new Error($e->getMessage());
            }
        }
    }

    /**
     * Disconnect from Asterisk server
     *
     * @return void
     */
    public function disconnect()
    {
        if ($this->isConnected) {
            $this->getPamiClient()->close();
            $this->isConnected = false;
        }
    }

    /**
     * Test Connection
     *
     * @param  array  $options
     *
     * @return bool | Exception
     */
    public function testConnection()
    {
        $this->connect();
        $this->isConnected = false;
        $this->pamiClient = null;

        return true; //throw Exception when false
    }

    /**
     * Get AMI options
     *
     * @return array
     */
    protected function getOptions($name = null)
    {
        if (isset($name) && isset($this->options[$name])) {
            return $this->options[$name];
        }

        return $this->options;
    }

    /**
     * Send a command to Asterisk server
     *
     * @param  string $actionName
     * @param  array $data
     * @return bool
     */
    protected function sendAction($actionName, $data, $isIgnoreException = false)
    {
        if (!$this->isConnected) {
            $this->connect();
        }

        $actionClass = '\PAMI\Message\Action\\' . ucfirst($actionName) . 'Action';
        if (class_exists($actionClass)) {
            $constructParams = $this->prepareParams($actionName, $data);
            $actionObj = $this->createObj($actionClass, $constructParams);
            foreach ($data as $name => $value) {
                $method = 'set' . ucfirst($name);
                if (method_exists($actionObj, $method)) {
                    $actionObj->$method($value);
                }
            }
        }

        try {
            $this->lastActionId = $actionObj->getActionID();
            $response = $this->getPamiClient()->send($actionObj);
        } catch (\Exception $e) {
            if ($isIgnoreException) {
                return true;
            }

            throw new Error($e->getMessage());
        }

        if (isset($response)) {
            if ($response->getKey('response') == 'Error') {
                throw new Error($response->getKey('message'));
            }

            if (method_exists($response, 'isSuccess')) {
                return (bool) $response->isSuccess();
            }
        }

        return false;
    }

    public function getLastActionId()
    {
        return $this->lastActionId;
    }

    public function dial(array $data)
    {
        if ($this->sendAction('originate', $data, true)) {
            return $this->getLastActionId();
        }
    }

    public function hangup($data)
    {

    }

    public function bridge($data)
    {

    }

    public function join($data)
    {

    }

    private function createObj($actionClass, array $constructParams = array())
    {
        if (!class_exists($actionClass)) {
            throw new Error('AMI: Class ['.$actionClass.'] does not exist.');
        }

        switch (count($constructParams)) {
            case 0:
                $obj = new $actionClass();
                break;

            case 1:
                $obj = new $actionClass($constructParams[0]);
                break;

            case 2:
                $obj = new $actionClass($constructParams[0], $constructParams[1]);
                break;

            case 3:
                $obj = new $actionClass($constructParams[0], $constructParams[1], $constructParams[2]);
                break;

            case 4:
                $obj = new $actionClass($constructParams[0], $constructParams[1], $constructParams[2], $constructParams[3]);
                break;

            case 5:
                $obj = new $actionClass($constructParams[0], $constructParams[1], $constructParams[2], $constructParams[3], $constructParams[4]);
                break;

            default:
                throw new Error('AMI: Cannot create an object ['.$actionClass.'] of PAMI action.');
                break;
        }

        return $obj;
    }

    private function prepareParams($actionName, &$data)
    {
        $actionName = strtolower($actionName);

        $data = $this->loadDefaultParams($data);

        $params = array();
        if (isset($this->actionMap[$actionName])) {
            foreach ($this->actionMap[$actionName] as $fieldName) {
                if (isset($data[$fieldName])) {
                    $params[] = $data[$fieldName];
                    unset($data[$fieldName]);
                }
            }
        }

        return $params;
    }

    private function loadDefaultParams(array $data)
    {
        $options = $this->getOptions();

        $defaults = array(
            'channel' => str_replace('###', $options['user']['voipUser'], $options['channel']),
            'context' => !empty($options['user']['voipContext']) ? $options['user']['voipContext'] : $options['context'],
        );

        foreach ($defaults as $name => $value) {
            if (!isset($data[$name])) {
                $data[$name] = $value;
            }
        }

        return $data;
    }
}
