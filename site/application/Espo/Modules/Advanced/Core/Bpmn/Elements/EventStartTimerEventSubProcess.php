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

class EventStartTimerEventSubProcess extends EventIntermediateTimerCatch
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

    public function proceedPending()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set('status', 'In Process');
        $this->getEntityManager()->saveEntity($flowNode);

        $subProcessIsInterrupting = $this->getFlowNode()->getDataItemValue('subProcessIsInterrupting');

        if (!$subProcessIsInterrupting) {
            $standbyFlowNode = $this->getManager()->prepareStandbyFlow(
                $this->getTarget(), $this->getProcess(), $this->getFlowNode()->getDataItemValue('subProcessElementId')
            );
            if ($standbyFlowNode) {
                $this->getManager()->processPreparedFlowNode($this->getTarget(), $standbyFlowNode, $this->getProcess());
            }
        }

        if ($subProcessIsInterrupting)
            $this->getManager()->interruptProcessByEventSubProcess($this->getProcess(), $this->getFlowNode());

        $this->processNextElement();
    }
}
