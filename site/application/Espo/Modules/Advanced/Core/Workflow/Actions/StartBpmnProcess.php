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

namespace Espo\Modules\Advanced\Core\Workflow\Actions;

use Espo\Core\Exceptions\Error;

use Espo\ORM\Entity;

use Espo\Modules\Advanced\Core\Bpmn\BpmnManager;

class StartBpmnProcess extends Base
{
    protected function run(Entity $entity, $actionData)
    {
        if (!empty($actionData->target)) {
            $target = $actionData->target;
            $targetEntity = $this->getTargetEntityFromTargetItem($entity, $target);

            if (!$targetEntity) {
                $GLOBALS['log']->notice('Workflow StartBpmnProcess: Empty target.');

                return;
            }
        }
        else {
            $targetEntity = $this->getEntityManager()->getEntity($entity->getEntityType(), $entity->id);
        }

        if (empty($actionData->flowchartId) || empty($actionData->elementId)) {
            throw new Error('StartBpmnProcess: Empty action data.');
        }

        $bpmnManager = new BpmnManager($this->getContainer());

        $flowchart = $this->getEntityManager()->getEntity('BpmnFlowchart', $actionData->flowchartId);

        if (!$flowchart) {
            throw new Error('StartBpmnProcess: Could not find flowchart ' . $actionData->flowchartId . '.');
        }

        if ($flowchart->get('targetType') !== $targetEntity->getEntityType()) {
            throw new Error("Workflow StartBpmnProcess: Target entity type doesn't match flowchart target type.");
        }

        $bpmnManager->startProcess($targetEntity, $flowchart, $actionData->elementId, null, $this->getWorkflowId());

        return true;
    }
}
