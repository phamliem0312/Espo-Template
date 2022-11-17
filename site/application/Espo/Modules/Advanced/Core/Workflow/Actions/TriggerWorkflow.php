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

use Espo\ORM\Entity;

class TriggerWorkflow extends Base
{
    protected function run(Entity $entity, $actionData)
    {
        if (empty($actionData->workflowId)) {
            return;
        }

        $targetEntity = $entity;

        if (!empty($actionData->target)) {
            $target = $actionData->target;

            $targetEntity = $this->getTargetEntityFromTargetItem($entity, $target);
        }

        if ($targetEntity) {
            $this->triggerAnothWorkflow($targetEntity, $actionData);
        }

        return true;
    }

    protected function triggerAnothWorkflow(Entity $entity, $actionData)
    {
        $jobData = [
            'workflowId' => $this->getWorkflowId(),
            'entityId' => $entity->get('id'),
            'entityType' => $entity->getEntityType(),
            'nextWorkflowId' => $actionData->workflowId,
            'values' => $entity->getValues(),
        ];

        $workflow = $this->getEntityManager()->getEntity('Workflow', $actionData->workflowId);

        if (!$workflow) {
            return;
        }

        if ($entity->getEntityType() !== $workflow->get('entityType')) {
            return;
        }

        $executeTime = null;

        if (property_exists($actionData, 'execution') && property_exists($actionData->execution, 'type')) {
            $executeType = $actionData->execution->type;
        }

        if (isset($executeType) && $executeType != 'immediately') {
            $executeTime = $this->getExecuteTime($actionData);
        }

        if ($executeTime) {
            $job = $this->getEntityManager()->getEntity('Job');

            $job->set([
                'serviceName' => 'Workflow',
                'method' => 'jobTriggerWorkflow',
                'methodName' => 'jobTriggerWorkflow',
                'data' => $jobData,
                'executeTime' => $executeTime,
            ]);

            $this->getEntityManager()->saveEntity($job);

            return;
        }

        $service = $this->getServiceFactory()->create('Workflow');

        $service->triggerWorkflow($entity, $actionData->workflowId);
    }
}
