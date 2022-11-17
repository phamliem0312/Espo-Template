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

namespace Espo\Modules\Advanced\Core\Workflow\Formula\Functions\BpmGroup;

use Espo\Core\Exceptions\Error;

use StdClass;

class BroadcastSignalType extends \Espo\Core\Formula\Functions\Base
{
    protected function init()
    {
        $this->addDependency('signalManager');
        $this->addDependency('entityManager');
    }

    public function process(StdClass $item)
    {
        $args = $this->fetchArguments($item);

        $signal = $args[0] ?? null;

        $entityType = $args[1] ?? null;
        $id = $args[2] ?? null;

        if (!$signal) {
            throw new Error("Formula: bpm\\broadcastSignal: No signal name.");
        }

        if (!is_string($signal)) {
            throw new Error("Formula: bpm\\broadcastSignal: Bad signal name.");
        }

        $entity = null;

        if ($entityType && $id) {
            $entityManager = $this->getInjection('entityManager');

            $entity = $entityManager->getEntity($entityType, $id);

            if (!$entity) {
                throw new Error("Formula: bpm\\broadcastSignal: The entity does not exist.");
            }
        }

        $signalManager = $this->getInjection('signalManager');

        $signalManager->trigger($signal, $entity);
    }
}