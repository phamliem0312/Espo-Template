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

use Espo\Core\Container;
use Espo\Core\Exceptions\{
    Error,
    BadRequest,
};

class VoipManagerNew
{
    protected $container;

    protected $data = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function getConnectorManager($connector)
    {
        if (empty($connector)) {
            throw new BadRequest('VoipManager: Incorrect connector name.');
        }

        if (!isset($this->data[$connector])) {
            $providerName = $this->getProviderName($connector);

            $class = '\\Espo\\Custom\\Modules\\Voip\\Providers\\' . $providerName . '\\Manager';

            if (!class_exists($class)) {
                foreach ($this->container->get('metadata')->getModuleList() as $moduleName) {
                    $class = '\\Espo\\Modules\\' . $moduleName . '\\Providers\\' . $providerName . '\\Manager';
                    if (class_exists($class)) {
                        break;
                    }
                }

                if (!class_exists($class)) {
                    throw new Error('VoipManager: Class ' . $class . ' does not extist.');
                }
            }

            $this->data[$connector] = new $class($this->container, $connector);
        }

        return $this->data[$connector];
    }

    public function getProviderName($connector)
    {
        $parentConnector = $this->container->get('metadata')->get('integrations.' . ucfirst($connector) . '.parent');
        if (!empty($parentConnector)) {
            return ucfirst($parentConnector);
        }

        return ucfirst($connector);
    }
}
