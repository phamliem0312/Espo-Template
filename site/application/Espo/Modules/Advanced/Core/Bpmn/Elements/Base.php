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

namespace Espo\Modules\Advanced\Core\Bpmn\Elements;

use Espo\Modules\Advanced\Core\Bpmn\BpmnManager;

use \Espo\Modules\Advanced\Entities\BpmnProcess;
use \Espo\Modules\Advanced\Entities\BpmnFlowNode;
use \Espo\ORM\Entity;

use \Espo\Core\Exceptions\Error;

abstract class Base
{
    protected $container;
    protected $process;
    protected $flowNode;
    protected $target;
    protected $manager;

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    protected function getProcess()
    {
        return $this->process;
    }

    protected function getFlowNode()
    {
        return $this->flowNode;
    }

    protected function getTarget()
    {
        return $this->target;
    }

    protected function getManager()
    {
        return $this->manager;
    }

    public function __construct(\Espo\Core\Container $container, BpmnManager $manager, Entity $target, BpmnFlowNode $flowNode, BpmnProcess $process)
    {
        $this->container = $container;
        $this->manager = $manager;
        $this->target = $target;
        $this->flowNode = $flowNode;
        $this->process = $process;
    }

    protected function refresh()
    {
        $this->refreshFlowNode();
        $this->refreshProcess();
        $this->refreshTarget();
    }

    protected function refreshFlowNode()
    {
        $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $this->flowNode->id);
        if ($flowNode) {
            $this->flowNode->set($flowNode->getValueMap());
            $this->flowNode->setAsFetched();
        }
    }

    protected function refreshProcess()
    {
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $this->process->id);
        if ($process) {
            $this->process->set($process->getValueMap());
            $this->process->setAsFetched();
        }
    }

    protected function refreshTarget()
    {
        $target = $this->getEntityManager()->getEntity($this->target->getEntityType(), $this->target->id);
        if ($target) {
            $this->target->set($target->getValueMap());
            $this->target->setAsFetched();
        }
    }

    public function isProcessable()
    {
        return true;
    }

    public function beforeProcess()
    {
    }

    abstract public function process();

    public function afterProcess()
    {
    }

    public function beforeProceedPending()
    {
    }

    public function proceedPending()
    {
        throw new Error("BPM Flow: Can't proceed element ". $flowNode->get('elementType') . " " . $flowNode->get('elementId') . " in flowchart " . $flowNode->get('flowchartId') . ".");
    }

    public function afterProceedPending()
    {
    }

    protected function getElementId()
    {
        $flowNode = $this->getFlowNode();
        $elementId = $flowNode->get('elementId');
        if (!$elementId) throw new Error("BPM Flow: No id for element " . $flowNode->get('elementType') . " in flowchart " . $flowNode->get('flowchartId') . ".");
        return $elementId;
    }

    protected function hasNextElementId()
    {
        $flowNode = $this->getFlowNode();

        $item = $flowNode->get('elementData');
        $nextElementIdList = $item->nextElementIdList;
        if (!count($nextElementIdList)) {
            return false;
        }
        return true;
    }

    protected function getNextElementId()
    {
        $flowNode = $this->getFlowNode();
        if (!$this->hasNextElementId()) {
            return null;
        }
        $item = $flowNode->get('elementData');
        $nextElementIdList = $item->nextElementIdList;
        return $nextElementIdList[0];
    }

    protected function getAttributeValue($name)
    {
        $item = $this->getFlowNode()->get('elementData');
        if (!property_exists($item, $name)) {
            return null;
        }
        return $item->$name;
    }

    protected function getVariables()
    {
        return $this->getProcess()->get('variables');
    }

    protected function getVariablesForFormula()
    {
        $variables = $this->getProcess()->get('variables') ?? (object) [];
        $variables = clone $variables;

        $variables->__createdEntitiesData = $this->getCreatedEntitiesData();
        $variables->__processEntity = $this->getProcess();

        return $variables;
    }

    protected function sanitizeVariables($variables)
    {
        unset($variables->__createdEntitiesData);
        unset($variables->__processEntity);
    }

    protected function setProcessed()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Processed',
            'processedAt' => date('Y-m-d H:i:s')
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
    }

    protected function setInterrupted()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Interrupted',
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
        $this->endProcessFlow();
    }

    protected function setFailed()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Failed',
            'processedAt' => date('Y-m-d H:i:s'),
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
        $this->endProcessFlow();
    }

    protected function setRejected()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Rejected',
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
        $this->endProcessFlow();
    }

    public function fail()
    {
        $this->setFailed();
    }

    public function interrupt()
    {
        $this->setInterrupted();
    }

    public function cleanupInterrupted()
    {
    }

    public function complete()
    {
        throw new Error("Can't complete " . $this->getFlowNode()->get('elementType') . ".");
    }

    protected function prepareNextFlowNode($nextElementId = null, $divergentFlowNodeId = false)
    {
        $flowNode = $this->getFlowNode();
        if (!$nextElementId) {
            if (!$this->hasNextElementId()) {
                $this->endProcessFlow();
                return;
            }
            $nextElementId = $this->getNextElementId();
        }
        if ($divergentFlowNodeId === false) {
            $divergentFlowNodeId = $flowNode->get('divergentFlowNodeId');
        }
        return $this->getManager()->prepareFlow($this->getTarget(), $this->getProcess(), $nextElementId, $flowNode->id, $flowNode->get('elementType'), $divergentFlowNodeId);
    }

    protected function processNextElement($nextElementId = null, $divergentFlowNodeId = false, $dontSetProcessed = false)
    {
        $nextFlowNode = $this->prepareNextFlowNode($nextElementId, $divergentFlowNodeId);
        if (!$dontSetProcessed) {
            $this->setProcessed();
        }
        if ($nextFlowNode) {
            $this->getManager()->processPreparedFlowNode($this->getTarget(), $nextFlowNode, $this->getProcess());
        }
        return $nextFlowNode;
    }

    protected function processPreparedNextFlowNode(BpmnFlowNode $flowNode)
    {
        $this->getManager()->processPreparedFlowNode($this->getTarget(), $flowNode, $this->getProcess());
    }

    protected function endProcessFlow()
    {
        $this->getManager()->endProcessFlow($this->getFlowNode(), $this->getProcess());
    }

    protected function getCreatedEntitiesData()
    {
        $createdEntitiesData = $this->getProcess()->get('createdEntitiesData');
        if (!$createdEntitiesData) {
            $createdEntitiesData = (object) [];
        }
        return $createdEntitiesData;
    }

    protected function getCreatedEntity($target)
    {
        $createdEntitiesData = $this->getCreatedEntitiesData();

        if (strpos($target, 'created:') === 0) {
            $alias = substr($target, 8);
        } else {
            $alias = $target;
        }

        if (!$createdEntitiesData) return null;

        if (!property_exists($createdEntitiesData, $alias)) {
            return null;
        }

        if (empty($createdEntitiesData->$alias->entityId) || empty($createdEntitiesData->$alias->entityType)) {
            return null;
        }

        $entityType = $createdEntitiesData->$alias->entityType;
        $entityId = $createdEntitiesData->$alias->entityId;

        $targetEntity = $this->getEntityManager()->getEntity($entityType, $entityId);

        return $targetEntity;
    }

    protected function getSpecificTarget($target)
    {
        $entity = $this->getTarget();

        if (!$target || $target == 'targetEntity') {
            return $entity;

        } else if (strpos($target, 'created:') === 0) {
            return $this->getCreatedEntity($target);

        } else if (strpos($target, 'link:') === 0) {
            $link = substr($target, 5);

            $linkList = explode('.', $link);

            $entityType = $entity->getEntityType();

            $pointerEntity = $entity;

            $notFound = false;

            foreach ($linkList as $link) {
                $type = $this->getMetadata()->get(['entityDefs', $pointerEntity->getEntityType(), 'links', $link, 'type']);
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

            if (!$notFound) {
                return $pointerEntity;
            }
        }

        return null;
    }
}
