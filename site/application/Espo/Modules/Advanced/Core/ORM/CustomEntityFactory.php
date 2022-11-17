<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Core\ORM;

use Espo\Core\{
    InjectableFactory,
    Utils\Config,
    ORM\Entity,
};

use Espo\ORM\{
    EntityManager,
};

/**
 * Create entities with custom defs. Need for supporting foreign fields like `linkName.attribute`.
 */
class CustomEntityFactory
{
    private $injectableFactory;

    private $entityManager;

    private $implementationMethodName;

    public function __construct(InjectableFactory $injectableFactory, EntityManager $entityManager, Config $config)
    {
        $this->injectableFactory = $injectableFactory;
        $this->entityManager = $entityManager;

        $version = $config->get('version');

        $this->implementationMethodName = 'createImplementationOld';

        if ($version === '@@version' || $version === 'dev' || version_compare($version, '6.0.0') >= 0) {
            $this->implementationMethodName = 'createImplementation';
        }
    }

    public function create(string $entityType, array $fieldDefs): Entity
    {
        $methodName = $this->implementationMethodName;

        return $this->$methodName($entityType, $fieldDefs);
    }

    private function createImplementationOld(string $entityType, array $fieldDefs) : Entity
    {
        $entity = $this->entityManager->getEntityFactory()->create($entityType);

        $entity->fields = $fieldDefs;

        return $entity;
    }

    private function createImplementation(string $entityType, array $fieldDefs) : Entity
    {
        $seed = $this->entityManager->getEntityFactory()->create($entityType);

        $className = get_class($seed);

        $defs = $this->entityManager->getMetadata()->get($entityType);

        if (array_key_exists('attributes', $defs)) {
            $defs['attributes'] = array_merge($defs['attributes'], $fieldDefs);
        }
        else {
            $defs['fields'] = array_merge($defs['fields'], $fieldDefs);
        }

        $entity = $this->injectableFactory->createWith($className, [
            'entityType' => $entityType,
            'defs' => $defs,
            'valueAccessorFactory' => null,
        ]);

        return $entity;
    }
}
