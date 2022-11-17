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

use Espo\Core\Container;
use Espo\Core\Exceptions\Error;
use Espo\Modules\Voip\Bases\Manager;

abstract class Base
{
    private $container;

    private $connectorManager;

    public function __construct(Container $container = null)
    {
        if ($container) {
            $this->container = $container;
        }
    }

    public function setContainer(Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        if (!$this->container) {
            throw new Error('Undefined "container".');
        }

        return $this->container;
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }

    protected function getConnector()
    {
        return $this->getConnectorManager()->getConnector();
    }

    public function setConnectorManager(Manager $connectorManager)
    {
        $this->connectorManager = $connectorManager;
    }

    protected function getConnectorManager()
    {
        if (!$this->connectorManager) {
            throw new Error('Undefined "connectorManager".');
        }

        return $this->connectorManager;
    }

    protected function getConnectorData()
    {
        return $this->getConnectorManager()->getConnectorData();
    }

    /**
     * Set Entity value if current is not set
     *
     * @param  \Espo\ORM\Entity $entity
     * @param  string $fieldName
     * @param  mixed $value
     *
     * @return void
     */
    protected function setIfNotIsset($entity, $fieldName, $value)
    {
        if ($entity instanceof \Espo\ORM\Entity) {
            $currentValue = $entity->get($fieldName);
            if (!isset($currentValue)) {
                $entity->set($fieldName, $value);
            }
        }
    }
}
