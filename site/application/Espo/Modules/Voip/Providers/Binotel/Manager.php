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

namespace Espo\Modules\Voip\Providers\Binotel;

use Espo\Core\Exceptions\Error;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtils;

class Manager extends \Espo\Modules\Voip\Bases\Manager
{
    protected $defaultApiVersion = '4';

    protected function createApiClient($normalizedOptions)
    {
        $apiVersion = !empty($normalizedOptions->apiVersion) ? $normalizedOptions->apiVersion : $this->defaultApiVersion;

        $className = '\\Espo\\Modules\\Voip\\Providers\\Binotel\\Api\\Binotel' . $apiVersion;
        if (!class_exists($className)) {
            throw new Error('VoIP [Binotel]: API client is not found.');
        }

        return new $className($normalizedOptions);
    }

    protected function createEventListener()
    {
        return new EventListener($this->getContainer());
    }

    protected function normalizeOptions(array $options, $connector = null)
    {
        return [
            'key' => $options['binotelKey'],
            'secret' => $options['binotelSecret'],
            'useSsl' => (bool) $options['binotelUseSsl'],
            'apiVersion' => $options['binotelApiVersion'] ?? $this->defaultApiVersion,
        ];
    }

    /**
     * Handle event
     *
     * @param  array  $eventData
     *
     * @return void
     */
    public function handleEvent(array $eventData = null)
    {
        $eventListener = $this->getEventListener();
        $eventListener->setConnectorManager($this);
        $eventListener->handle($eventData);
    }

    public function testConnection(array $data)
    {
        //todo: implement it
    }

    public function dial(array $data)
    {
        return $this->getApiClient()->callsExtToPhone($data['callerId'], $data['toPhoneNumber']);
    }
}
