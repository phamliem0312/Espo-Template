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

namespace Espo\Modules\Voip\Core;

abstract class BaseManager
{
    protected $container;

    public function __construct(\Espo\Core\Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    /**
     * Get class name of the connector
     *
     * @param  string $connector
     *
     * @return string
     */
    public function getProviderName($connector)
    {
        $parentConnector = $this->getMetadata()->get('integrations.' . ucfirst($connector) . '.parent');
        if (!empty($parentConnector)) {
            return ucfirst($parentConnector);
        }

        return ucfirst($connector);
    }
}
