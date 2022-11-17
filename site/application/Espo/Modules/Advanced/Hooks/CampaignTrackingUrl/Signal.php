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

namespace Espo\Modules\Advanced\Hooks\CampaignTrackingUrl;

use Espo\ORM\Entity;

class Signal extends \Espo\Core\Hooks\Base
{
    public static $order = 100;

    protected function init()
    {
        $this->addDependency('signalManager');
        $this->addDependency('entityManager');
    }

    public function afterClick(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        $signalManager = $this->getInjection('signalManager');
        $em = $this->getInjection('entityManager');

        $uid = $hookData['uid'] ?? null;

        if ($uid) {
            $signalManager->trigger(
                implode('.', ['clickUniqueUrl', $uid])
            );
        }

        $targetType = $hookData['targetType'] ?? null;
        $targetId = $hookData['targetId'] ?? null;

        if (!$targetType || !$targetId) {
            return;
        }

        $target = $em->getEntity($targetType, $targetId);

        if (!$target) {
            return;
        }

        $signalManager->trigger(implode('.', ['@clickUrl', $entity->id]), $target);
        $signalManager->trigger(implode('.', ['@clickUrl']), $target);

        $signalManager->trigger(implode('.', ['clickUrl', $targetType, $targetId, $entity->id]));
        $signalManager->trigger(implode('.', ['clickUrl', $targetType, $targetId]));
    }
}
