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

use \Espo\Core\Exceptions\Error;

class EventStartConditionalEventSubProcess extends EventIntermediateConditionalCatch
{
    protected $pendingStatus = 'Standby';

    protected function getConditionsTarget()
    {
        return $this->getSpecificTarget($this->getFlowNode()->getDataItemValue('subProcessTarget'));
    }

    protected function processNextElement($nextElementId = null, $divergentFlowNodeId = false, $dontSetProcessed = false)
    {
        return parent::processNextElement($this->getFlowNode()->getDataItemValue('subProcessElementId'));
    }

    public function process()
    {
        $target = $this->getConditionsTarget();

        if (!$target) {
            $this->fail();
            return;
        }

        $result = $this->getConditionManager()->check(
            $target,
            $this->getAttributeValue('conditionsAll'),
            $this->getAttributeValue('conditionsAny'),
            $this->getAttributeValue('conditionsFormula'),
            $this->getVariablesForFormula()
        );

        if ($result) {
            $subProcessIsInterrupting = $this->getFlowNode()->getDataItemValue('subProcessIsInterrupting');

            if (!$subProcessIsInterrupting)
                $this->createOppositeNode();

            if ($subProcessIsInterrupting)
                $this->getManager()->interruptProcessByEventSubProcess($this->getProcess(), $this->getFlowNode());

            $this->processNextElement();

            return;
        }

        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => $this->pendingStatus,
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
    }

    public function proceedPending()
    {
        $result = $this->getConditionManager()->check(
            $this->getTarget(),
            $this->getAttributeValue('conditionsAll'),
            $this->getAttributeValue('conditionsAny'),
            $this->getAttributeValue('conditionsFormula'),
            $this->getVariablesForFormula()
        );

        if ($this->getFlowNode()->getDataItemValue('isOpposite')) {
            if (!$result) {
                $this->setProcessed();
                $this->createOppositeNode(true);
            }
            return;
        }

        if ($result) {
            $subProcessIsInterrupting = $this->getFlowNode()->getDataItemValue('subProcessIsInterrupting');

            if (!$subProcessIsInterrupting)
                $this->createOppositeNode();

            if ($subProcessIsInterrupting)
                $this->getManager()->interruptProcessByEventSubProcess($this->getProcess(), $this->getFlowNode());

            $this->processNextElement();
        }
    }

    protected function createOppositeNode($isNegative = false)
    {
        $data = $this->getFlowNode()->get('data') ?? (object) [];
        $data = clone $data;
        $data->isOpposite = !$isNegative;

        $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode');
        $flowNode->set([
            'status' => 'Standby',
            'elementType' => $this->getFlowNode()->get('elementType'),
            'elementData' => $this->getFlowNode()->get('elementData'),
            'data' => $data,
            'flowchartId' => $this->getProcess()->get('flowchartId'),
            'processId' => $this->getProcess()->id,
            'targetType' => $this->getFlowNode()->get('targetType'),
            'targetId' => $this->getFlowNode()->get('targetId'),
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
    }
}
