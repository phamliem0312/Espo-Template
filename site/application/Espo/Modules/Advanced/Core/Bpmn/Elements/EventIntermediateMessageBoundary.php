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

class EventIntermediateMessageBoundary extends EventIntermediateMessageCatch
{

    protected function proceedPendingFinal()
    {
        if ($this->getAttributeValue('cancelActivity')) {
            $this->getManager()->cancelActivityByBoundaryEvent($this->getFlowNode());
        } else {
            $this->createCopy();
        }

        $this->processNextElement();
    }

    protected function createCopy()
    {
        $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode');
        $flowNode->set([
            'status' => 'Pending',
            'elementId' => $this->getFlowNode()->get('elementId'),
            'elementType' => $this->getFlowNode()->get('elementType'),
            'elementData' => $this->getFlowNode()->get('elementData'),
            'data' => (object) [],
            'flowchartId' => $this->getProcess()->get('flowchartId'),
            'processId' => $this->getProcess()->id,
            'previousFlowNodeElementType' => $this->getFlowNode()->get('previousFlowNodeElementType'),
            'previousFlowNodeId' => $this->getFlowNode()->get('previousFlowNodeId'),
            'divergentFlowNodeId' => $this->getFlowNode()->get('divergentFlowNodeId'),
            'targetType' => $this->getFlowNode()->get('targetType'),
            'targetId' => $this->getFlowNode()->get('targetId'),
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
    }
}
