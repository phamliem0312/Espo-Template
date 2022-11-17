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

namespace Espo\Modules\Voip\Bases;

use Espo\Core\Exceptions\{
    Error,
    Forbidden,
};

use Espo\ORM\Entity;

abstract class Manager implements IManager
{
    private $container;

    protected $connector;

    private $integrationEntity;

    private $phoneNumberHelper;

    private $cidManager;

    protected $apiClient;

    protected $eventListener;

    protected $webhookHandler;

    protected $options = [];

    protected $user = null;

    public function __construct(\Espo\Core\Container $container, $connector)
    {
        $this->container = $container;
        $this->connector = $connector;

        $this->init();
    }

    public function createManagerForConnector($connector)
    {
        if ($connector == $this->getConnector()) {
            return $this;
        }

        return $this->getContainer()->get('voipManager')->getConnectorManager($connector);
    }

    protected function init()
    {
    }

    protected function getPhoneNumberHelper()
    {
        if (!isset($this->phoneNumberHelper)) {
            $this->phoneNumberHelper = new \Espo\Modules\Voip\Core\Helpers\PhoneNumber($this->container);
        }

        return $this->phoneNumberHelper;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    public function getConnector()
    {
        return $this->connector;
    }

    protected function isEnabled()
    {
        return $this->getIntegrationEntity()->get('enabled');
    }

    public function setUser(Entity $user)
    {
        $this->user = $user;
    }

    protected function getUser()
    {
        if ($this->user) {
            return $this->user;
        }

        return $this->getContainer()->get('user');
    }

    protected function getIntegrationEntity()
    {
        if (!isset($this->integrationEntity)) {
            $this->integrationEntity = $this->loadIntegrationEntity($this->connector);
        }

        return $this->integrationEntity;
    }

    protected function loadIntegrationEntity($connector = null)
    {
        return $this->getContainer()->get('entityManager')->getEntity('Integration', strtolower($connector));
    }

    public function getProviderName($connector = null)
    {
        if (!$connector) {
            $connector = $this->getConnector();
        }

        $parentConnector = $this->getContainer()->get('metadata')->get('integrations.' . ucfirst($connector) . '.parent');
        if (!empty($parentConnector)) {
            return ucfirst($parentConnector);
        }

        return ucfirst($connector);
    }

    protected function createApiClient(array $normalizedOptions)
    {
        $class = $this->getClassName('apiClient');
        return new $class($normalizedOptions);
    }

    protected function createEventListener()
    {
        $class = $this->getClassName('eventListener');
        return new $class();
    }

    protected function createWebhookHandler()
    {
        $class = $this->getClassName('webhookHandler');
        return new $class();
    }

    protected function createCidManager()
    {
        $class = $this->getClassName('cidManager');
        return new $class();
    }

    protected function getApiClient()
    {
        if (!$this->isEnabled()) {
            throw new Error('The "'. $this->getConnector() .'" connector is disabled.');
        }

        if (!isset($this->apiClient)) {
            $this->apiClient = $this->createApiClient($this->getOptions());
        }

        return $this->apiClient;
    }

    protected function getEventListener()
    {
        if (!isset($this->eventListener)) {
            $this->eventListener = $this->createEventListener();
            $this->eventListener->setContainer($this->getContainer());
            $this->eventListener->setConnectorManager($this);
        }

        return $this->eventListener;
    }

    protected function getWebhookHandler()
    {
        if (!isset($this->webhookHandler)) {
            $this->webhookHandler = $this->createWebhookHandler();
            $this->webhookHandler->setContainer($this->getContainer());
            $this->webhookHandler->setConnectorManager($this);
        }

        return $this->webhookHandler;
    }

    protected function getCidManager()
    {
        if (!isset($this->cidManager)) {
            $this->cidManager = $this->createCidManager();
            $this->cidManager->setContainer($this->getContainer());
            $this->cidManager->setConnectorManager($this);
        }

        return $this->cidManager;
    }

    private function getClassName($className)
    {
        $providerName = $this->getProviderName();

        $class = '\\Espo\\Custom\\Modules\\Voip\\Providers\\' . $providerName . '\\' . ucfirst($className);

        if (!class_exists($class)) {
            foreach ($this->getContainer()->get('metadata')->getModuleList() as $moduleName) {
                $class = '\\Espo\\Modules\\' . $moduleName . '\\Providers\\' . $providerName . '\\' . ucfirst($className);
                if (class_exists($class)) break;
            }
        }

        if (!class_exists($class)) {
            throw new Error('Class "' . $class . '" is not found.');
        }

        return $class;
    }

    public function startEventListener()
    {
        throw new Error('This action is not available for this connector.');
    }

    public function startServiceEventListener()
    {
        throw new Error('This action is not available for this connector.');
    }

    public function sendMessage($fromNumber, $toNumber, $text = null, array $options = null)
    {
        throw new Error('This action is not available for this connector.');
    }

    public function handleEvent(array $eventData = null)
    {
        throw new Error('This action is not available for this connector.');
    }

    public function handleMessage(array $messageData = null)
    {
        throw new Error('This action is not available for this connector.');
    }

    public function runWebhook(array $data, $request)
    {
        return $this->getWebhookHandler()->run($data, $request);
    }

    public function getCidName($phoneNumber, array $params = [])
    {
        return $this->getCidManager()->getCallerName($phoneNumber, $params);
    }

    protected function getOptions($connector = null)
    {
        $connector = !empty($connector) ? $connector : $this->getConnector();

        if (!isset($this->options['connections'][$connector])) {
            $integrationEntity = $this->loadIntegrationEntity($connector);

            $data = (array) $integrationEntity->get('data');
            $this->options['connections'][$connector] = $this->normalizeOptions($data, $connector);
        }

        return $this->options['connections'][$connector];
    }

    protected function normalizeOptions(array $options, $connector = null)
    {
        return $options;
    }

    public function getConnectorData()
    {
        return $this->getIntegrationEntity()->get('data');
    }

    public function actionDial(array $data)
    {
        $callerId = $data['callerId'] ?? null;
        $toPhoneNumber = $data['toPhoneNumber'] ?? null;

        if (!$callerId) {
            throw new Error('Internal user extension cannot be empty.');
        }

        if (!$toPhoneNumber) {
            throw new Error('Phone number cannot be empty.');
        }

        return $this->dial($data);
    }

    public function checkAccessKey($accessKey)
    {
        $connectorData = $this->getConnectorData();

        if ($accessKey && !empty($connectorData->accessKey) && $accessKey === $connectorData->accessKey) {
            return true;
        }

        return false;
    }

    public function actionRunWebhook(array $data, $request)
    {
        $GLOBALS['log']->debug('Voip Normalized Data: ' . var_export($data, true));

        return $this->runWebhook($data, $request);
    }

    /**
     * Do phone number replacement
     *
     * @param  string $phoneNumber
     * @param  string $connector
     *
     * @return string
     */
    public function doPhoneNumberReplacement($phoneNumber, $connector = null)
    {
        if (!isset($connector)) {
            $connector = $this->getConnector();
        }

        return $this->getPhoneNumberHelper()->doPhoneNumberReplacement($connector, $phoneNumber);
    }

    /**
     * Format phone number
     *
     * @param  string $phoneNumber
     * @param  string $format
     *
     * @return string
     */
    public function formatPhoneNumber($phoneNumber, $format, $originalPhoneNumber = null)
    {
        $connector = $this->getConnector();

        return $this->getPhoneNumberHelper()->formatPhoneNumber($connector, $phoneNumber, $format, $originalPhoneNumber);
    }

    /**
     * Check if a phone number is valid
     *
     * @param  string  $phoneNumber
     *
     * @return boolean
     */
    public function isValidPhoneNumber($phoneNumber)
    {
        $connector = $this->getConnector();
        return $this->getPhoneNumberHelper()->isValidPhoneNumber($connector, $phoneNumber);
    }

    public function isSipPhoneNumber($phoneNumber)
    {
        return $this->getPhoneNumberHelper()->isSipPhoneNumber($phoneNumber);
    }

    public function getPhoneNumberType($phoneNumber)
    {
        $connector = $this->getConnector();
        return $this->getPhoneNumberHelper()->getPhoneNumberType($connector, $phoneNumber);
    }
}
