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

class EventStartSignalEventSubProcess extends Event
{
    protected function processNextElement($nextElementId = null, $divergentFlowNodeId = false, $dontSetProcessed = false)
    {
        return parent::processNextElement($this->getFlowNode()->getDataItemValue('subProcessElementId'));
    }

    public function process()
    {
        $signal = $this->getSignal();

        if (!$signal) {
            $this->fail();
            $GLOBALS['log']->warning("BPM: No signal for sub-process start event");
            return;
        }

        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Standby',
        ]);
        $this->getEntityManager()->saveEntity($flowNode);

        $this->getSignalManager()->subscribe($signal, $flowNode->id);
    }

    public function proceedPending()
    {
        $subProcessIsInterrupting = $this->getFlowNode()->getDataItemValue('subProcessIsInterrupting');

        if (!$subProcessIsInterrupting)
            $this->createCopy();

        if ($subProcessIsInterrupting)
            $this->getManager()->interruptProcessByEventSubProcess($this->getProcess(), $this->getFlowNode());

        $this->processNextElement();
    }

    protected function createCopy()
    {
        $data = $this->getFlowNode()->get('data') ?? (object) [];
        $data = clone $data;

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

        $this->getSignalManager()->subscribe($this->getSignal(), $flowNode->id);
    }

    protected function getSignal() : ?string
    {
        $subProcessStartData = $this->getFlowNode()->getDataItemValue('subProcessStartData') ?? (object) [];
        $signal = $subProcessStartData->signal ?? null;

        if ($signal) {
            $target = $this->getTarget();
            foreach ($target->getAttributeList() as $a) {
                if (!$target->has($a) || !$target->get($a)) continue;
                $value = $target->get($a);
                if (is_string($value)) {
                    $signal = str_replace('{$'.$a.'}', $value, $signal);
                }
            }

            $variables = $this->getVariables() ?? (object) [];
            foreach (get_object_vars($variables) as $key => $value) {
                if ($value && is_string($value)) {
                    $signal = str_replace('{$$'.$key.'}', $value, $signal);
                }
            }
        }

        return $signal;
    }

    protected function getSignalManager()
    {
        return $this->getContainer()->get('signalManager');
    }
}
