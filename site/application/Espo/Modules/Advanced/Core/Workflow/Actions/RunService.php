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
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Error;
use Espo\Core\Utils\Json;

class RunService extends Base
{
    /**
     * Main run method
     *
     * @param  Entity $entity
     * @param  array $actionData
     * @return string
     */
    protected function run(Entity $entity, $actionData)
    {
        $serviceFactory = $this->getServiceFactory();

        if (empty($actionData->methodName)) {
            throw new Error();
        }

        $name = $actionData->methodName;

        $target = 'targetEntity';
        if (!empty($actionData->target)) {
            $target = $actionData->target;
        }

        if ($target == 'targetEntity') {
            $targetEntity = $entity;
        } else if (strpos($target, 'created:') === 0) {
            $targetEntity = $this->getCreatedEntity($target);
        } else if (strpos($target, 'link:') === 0) {
            $link = substr($target, 5);
            $type = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'type']);
            if (empty($type)) return;
            $idField = $link . 'Id';

            if ($type == 'belongsTo') {
                if (!$entity->get($idField)) return;
                $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'links', $link, 'entity']);
                if (empty($foreignEntityType)) return;
                $targetEntity = $this->getEntityManager()->getEntity($foreignEntityType, $entity->get($idField));
            } else {
                return;
            }
        }

        if (!$targetEntity) return;

        $serviceName = $this->getMetadata()->get(['entityDefs', 'Workflow', 'serviceActions', $targetEntity->getEntityType(), $name, 'serviceName']);
        $methodName = $this->getMetadata()->get(['entityDefs', 'Workflow', 'serviceActions', $targetEntity->getEntityType(), $name, 'methodName']);

        if (!$serviceName || !$methodName) {
            $methodName = $name;
            $serviceName = $targetEntity->getEntityType();
        }


        if (!$serviceFactory->checkExists($serviceName)) {
            throw new Error();
        }

        $service = $serviceFactory->create($serviceName);


        if (!method_exists($service, $methodName)) {
            throw new Error();
        }

        $data = null;
        if (!empty($actionData->additionalParameters)) {
            $data = Json::decode($actionData->additionalParameters);
        }

        $variables = null;
        $originalVariables = null;

        if ($this->hasVariables()) {
            $variables = $this->getVariables();

            $originalVariables = clone $variables;
        }

        $service->$methodName($this->getWorkflowId(), $targetEntity, $data, $this->bpmnProcess, $variables);

        if ($variables && $variables != $originalVariables) {
            $this->updateVariables($variables);
        }

        return true;
    }
}