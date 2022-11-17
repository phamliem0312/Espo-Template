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

namespace Espo\Modules\Advanced\Hooks\Common;

use Espo\ORM\Entity;

class UpdateDeferredFlowNodes extends \Espo\Core\Hooks\Base
{
    const LIMIT = 10;

    public function afterSave(Entity $entity, array $options = [])
    {
        // To skip if updated from a BPM process.
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['workflowId'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        $entityType = $entity->getEntityType();

        if (!$this->getMetadata()->get(['scopes', $entityType, 'object'])) {
            return;
        }

        $nodeList = $this->getEntityManager()
            ->getRepository('BpmnFlowNode')
            ->where([
                'targetId' => $entity->id,
                'targetType' => $entityType,
                'status' => ['Pending', 'Standby'],
                'isDeferred' => true,
            ])
            ->limit(0, self::LIMIT)
            ->find();

        foreach ($nodeList as $node) {
            $node->set('isDeferred', false);

            $this->getEntityManager()->saveEntity($node, [
                'silent' => true,
                'skipAll' => true,
            ]);
        }
    }
}
