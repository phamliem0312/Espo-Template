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

namespace Espo\Modules\Advanced\Hooks\TargetList;

class Signal extends \Espo\Core\Hooks\Base
{
    public static $order = 100;

    protected function init()
    {
        $this->addDependency('signalManager');
        $this->addDependency('entityManager');
    }

    public function afterOptOut(\Espo\ORM\Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) return;
        if (!empty($options['skipSignal'])) return;
        if (!empty($options['silent'])) return;

        $signalManager = $this->getInjection('signalManager');
        $em = $this->getInjection('entityManager');

        $targetType = $hookData['targetType'];
        $targetId = $hookData['targetId'];
        $foreignId = $foreign->id;

        $target = $em->getEntity($targetType, $targetId);

        if (!$target) return;

        $signalManager->trigger(implode('.', ['@optOut', $entity->id]), $target);

        $signalManager->trigger(implode('.', ['optOut', $target->getEntityType(), $target->id, $entity->id]));
    }

    public function afterCancelOptOut(\Espo\ORM\Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) return;
        if (!empty($options['skipSignal'])) return;
        if (!empty($options['silent'])) return;

        $signalManager = $this->getInjection('signalManager');
        $em = $this->getInjection('entityManager');

        $targetType = $hookData['targetType'];
        $targetId = $hookData['targetId'];
        $foreignId = $foreign->id;

        $target = $em->getEntity($targetType, $targetId);

        if (!$target) return;

        $signalManager->trigger(implode('.', ['@cancelOptOut', $entity->id]), $target);

        $signalManager->trigger(implode('.', ['cancelOptOut', $target->getEntityType(), $target->id, $entity->id]));
    }
}
