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
use Espo\Modules\Advanced\Core\Workflow\Utils;

use Espo\ORM\Entity;

use Espo\Core\Container;

abstract class Base
{
    private $container;

    private $entityManager;

    private $workflowId;

    protected $entity;

    protected $action;

    protected $createdEntitiesData = null;

    protected $createdEntitiesDataIsChanged = false;

    protected $variables = null;

    protected $preparedVariables = null;

    protected $bpmnProcess = null;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->entityManager = $container->get('entityManager');
    }

    protected function getContainer()
    {
        return $this->container;
    }

    public function isCreatedEntitiesDataChanged()
    {
        return $this->createdEntitiesDataIsChanged;
    }

    public function getCreatedEntitiesData()
    {
        return $this->createdEntitiesData;
    }

    public function setWorkflowId($workflowId)
    {
        $this->workflowId = $workflowId;
    }

    protected function getWorkflowId()
    {
        return $this->workflowId;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    protected function getServiceFactory()
    {
        return $this->container->get('serviceFactory');
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    protected function getConfig()
    {
        return $this->container->get('config');
    }

    protected function getFormulaManager()
    {
        return $this->container->get('formulaManager');
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    protected function getEntity()
    {
        return $this->entity;
    }

    protected function getActionData()
    {
        return $this->action;
    }

    protected function getHelper()
    {
        return $this->container->get('workflowHelper');
    }

    protected function getEntityHelper()
    {
        return $this->getHelper()->getEntityHelper();
    }

    protected function clearSystemVariables(object $variables)
    {
        unset($variables->__targetEntity);
        unset($variables->__processEntity);
        unset($variables->__createdEntitiesData);
    }

    /**
     * Return variables. Can be changed after action is processed.
     */
    public function getVariablesBack() : object
    {
        $variables = clone $this->variables;

        $this->clearSystemVariables($variables);

        return $variables;
    }

    /**
     * Get variables for usage within an action.
     */
    public function getVariables() : object
    {
        $variables = $this->getFormulaVariables();

        $variables = clone $variables;

        $this->clearSystemVariables($variables);

        return $variables;
    }

    protected function hasVariables() : bool
    {
        return !!$this->variables;
    }

    protected function updateVariables(object $variables)
    {
        if (!$this->hasVariables()) {
            return;
        }

        $variables = clone $variables;

        $this->clearSystemVariables($variables);

        foreach (get_object_vars($variables) as $k => $v) {
            $this->variables->$k = $v;
        }
    }

    protected function getFormulaVariables()
    {
        if (!$this->preparedVariables) {
            $o = (object) [];

            $o->__targetEntity = $this->getEntity();

            if ($this->bpmnProcess) {
                $o->__processEntity = $this->bpmnProcess;
            }

            if ($this->createdEntitiesData) {
                $o->__createdEntitiesData = $this->createdEntitiesData;
            }

            if ($this->variables) {
                foreach ($this->variables as $k => $v) {
                    $o->$k = $v;
                }
            }

            $this->preparedVariables = $o;
        }
        return $this->preparedVariables;
    }

    public function process($entity, $action, $createdEntitiesData = null, $variables = null, $bpmnProcess = null)
    {
        $this->entity = $entity;
        $this->action = $action;
        $this->createdEntitiesData = $createdEntitiesData;
        $this->variables = $variables;
        $this->bpmnProcess = $bpmnProcess;

        if (!property_exists($action, 'cid')) {
            $action->cid = 0;
        }

        $GLOBALS['log']->debug(
            'Workflow\Actions: Start ['.$action->type.'] with cid ['.$action->cid.'] for entity ['.
            $entity->getEntityType().', '.$entity->id.'].'
        );

        $result = $this->run($entity, $action);

        $GLOBALS['log']->debug(
            'Workflow\Actions: End ['.$action->type.'] with cid ['.$action->cid.'] for entity ['.
            $entity->getEntityType().', '.$entity->id.'].'
        );

        if (!$result) {
            $GLOBALS['log']->debug(
                'Workflow['.$this->getWorkflowId().']: Action failed [' . $action->type . '] with cid [' . $action->cid . '].'
            );
        }
    }

    /**
     * Get execute time defined in workflow
     *
     * @return string
     */
    protected function getExecuteTime($data)
    {
        $executeTime = date('Y-m-d H:i:s');

        if (!property_exists($data, 'execution')) {
            return $executeTime;
        }

        $execution = $data->execution;

        switch ($execution->type) {
            case 'immediately':
                return $executeTime;

                break;

            case 'later':
                if (!empty($execution->field)) {
                   $executeTime =  Utils::getFieldValue($this->getEntity(), $execution->field);
                }

                if (!empty($execution->shiftDays)) {
                    $shiftUnit = 'days';

                    if (!empty($execution->shiftUnit)) {
                        $shiftUnit = $execution->shiftUnit;
                    }

                    if (!in_array($shiftUnit, ['hours', 'minutes', 'days', 'months'])) {
                        $shiftUnit = 'days';
                    }

                    $executeTime = Utils::shiftDays($execution->shiftDays, $executeTime, 'datetime', $shiftUnit);
                }
                break;

            default:
                throw new Error('Workflow['.$this->getWorkflowId().']: Unknown execution type [' . $execution->type . ']');

                break;
        }

        return $executeTime;
    }

    protected function getCreatedEntity($target)
    {
        if (strpos($target, 'created:') === 0) {
            $alias = substr($target, 8);
        } else {
            $alias = $target;
        }

        if (!$this->createdEntitiesData) return null;

        if (!property_exists($this->createdEntitiesData, $alias)) {
            return null;
        }

        if (empty($this->createdEntitiesData->$alias->entityId) || empty($this->createdEntitiesData->$alias->entityType)) {
            return null;
        }

        $entityType = $this->createdEntitiesData->$alias->entityType;
        $entityId = $this->createdEntitiesData->$alias->entityId;

        $targetEntity = $this->getEntityManager()->getEntity($entityType, $entityId);

        return $targetEntity;
    }

    protected function getTargetEntityFromTargetItem($entity, $target)
    {
        $targetEntity = null;

        if (!$target || $target == 'targetEntity') {
            return $this->getEntityManager()->getEntity($entity->getEntityType(), $entity->id);
        }

        if (strpos($target, 'created:') === 0) {
            return $this->getCreatedEntity($target);
        }

        if (strpos($target, 'link:') === 0) {
            $link = substr($target, 5);

            $linkList = explode('.', $link);

            $entityType = $entity->getEntityType();

            $pointerEntity = $entity;

            $notFound = false;

            foreach ($linkList as $link) {
                $type = $this->getMetadata()
                    ->get(['entityDefs', $pointerEntity->getEntityType(), 'links', $link, 'type']);

                if (empty($type)) {
                    $notFound = true;

                    break;
                }

                $pointerEntity = $pointerEntity->get($link);

                if (!$pointerEntity || !($pointerEntity instanceof Entity)) {
                    $notFound = true;

                    break;
                }
            }

            if ($notFound) {
                return null;
            }

            return $pointerEntity;
        }

        if ($target == 'currentUser') {
            return $this->getUser();
        }

        return null;
    }

    abstract protected function run(Entity $entity, $actionData);
}