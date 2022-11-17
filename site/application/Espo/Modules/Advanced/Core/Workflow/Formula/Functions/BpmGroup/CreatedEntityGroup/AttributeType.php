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

namespace Espo\Modules\Advanced\Core\Workflow\Formula\Functions\BpmGroup\CreatedEntityGroup;

use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;

class AttributeType extends \Espo\Core\Formula\Functions\AttributeType
{
    protected $dependencyList = [
        'entityManager',
    ];

    public function process(\StdClass $item)
    {
        if (!property_exists($item, 'value')) throw new Error();


        if (count($item->value) < 2) throw new Error();

        $aliasId = $this->evaluate($item->value[0]);
        $attribute = $this->evaluate($item->value[1]);

        if (!is_string($aliasId) || !is_string($attribute))
            throw new Error("Formula bpm\createdEntity\\attribute: Bad argument");

        $variables = $this->getVariables();

        if (!$variables || !isset($variables->__createdEntitiesData))
            throw new Error("Formula bpm\createdEntity\\attribute: Can't be used out of BPM process");

        if (!isset($variables->__createdEntitiesData->$aliasId))
            throw new Error("Formula bpm\createdEntity\\attribute: Unknown aliasId");

        $entityType = $variables->__createdEntitiesData->$aliasId->entityType;
        $entityId = $variables->__createdEntitiesData->$aliasId->entityId;

        $entity = $this->getInjection('entityManager')->getEntity($entityType, $entityId);

        return $this->attributeFetcher->fetch($entity, $attribute);
    }
}
