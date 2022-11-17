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

abstract class Activity extends Base
{
    protected $pendingBoundaryTypeList = [
        'eventIntermediateConditionalBoundary',
        'eventIntermediateTimerBoundary',
        'eventIntermediateSignalBoundary',
        'eventIntermediateMessageBoundary',
    ];

    public function beforeProcess()
    {
        $boundaryFlowNodeList = [];

        $attachedElementIdList = $this->getProcess()->getAttachedToFlowNodeElementIdList($this->getFlowNode());

        foreach ($attachedElementIdList as $id) {
            $item = $this->getProcess()->getElementDataById($id);

            if (!in_array($item->type,  $this->pendingBoundaryTypeList)) {
                continue;
            }

            $boundaryFlowNode = $this->getManager()->prepareFlow(
                $this->getTarget(),
                $this->getProcess(),
                $id,
                $this->getFlowNode()->id,
                $this->getFlowNode()->get('elementType')
            );

            if ($boundaryFlowNode) {
                $boundaryFlowNodeList[] = $boundaryFlowNode;
            }
        }

        foreach ($boundaryFlowNodeList as $boundaryFlowNode) {
            $this->getManager()->processPreparedFlowNode($this->getTarget(), $boundaryFlowNode, $this->getProcess());
        }

        $this->refreshFlowNode();
        $this->refreshTarget();
    }

    public function isProcessable()
    {
        return $this->getFlowNode()->get('status') === 'Created';
    }

    protected function setFailed()
    {
        $this->rejectPendingBoundaryFlowNodes();

        $errorCode = $this->getFlowNode()->getDataItemValue('errorCode');

        $boundaryErrorFlowNode = $this->getManager()
            ->prepareBoundaryErrorFlowNode($this->getFlowNode(), $this->getProcess(), $errorCode);

        parent::setFailed();

        if ($boundaryErrorFlowNode) {
            $this->getManager()
                ->processPreparedFlowNode($this->getTarget(), $boundaryErrorFlowNode, $this->getProcess());
        }
    }

    protected function setFailedWithCode(string $errorCode): void
    {
        $this->rejectPendingBoundaryFlowNodes();

        $boundaryErrorFlowNode = $this->getManager()
            ->prepareBoundaryErrorFlowNode($this->getFlowNode(), $this->getProcess(), $errorCode);

        parent::setFailed();

        if ($boundaryErrorFlowNode) {
            $this->getManager()
                ->processPreparedFlowNode($this->getTarget(), $boundaryErrorFlowNode, $this->getProcess());
        }
    }

    protected function getPendingBoundaryFlowNodeList()
    {
        return $this->getEntityManager()
            ->getRepository('BpmnFlowNode')
            ->where([
                'elementType' => $this->pendingBoundaryTypeList,
                'processId' => $this->getProcess()->id,
                'status' => ['Created', 'Pending'],
                'previousFlowNodeId' => $this->getFlowNode()->id,
            ])
            ->find();
    }

    protected function rejectPendingBoundaryFlowNodes()
    {
        $boundaryNodeList = $this->getPendingBoundaryFlowNodeList();

        foreach ($boundaryNodeList as $boudaryNode) {
            $boudaryNode->set('status', 'Rejected');

            $this->getEntityManager()->saveEntity($boudaryNode);
        }
    }

    protected function setRejected()
    {
        $this->rejectPendingBoundaryFlowNodes();

        parent::setRejected();
    }

    protected function setProcessed()
    {
        $this->rejectPendingBoundaryFlowNodes();

        parent::setProcessed();
    }

    protected function setInterrupted()
    {
        $this->rejectPendingBoundaryFlowNodes();

        parent::setInterrupted();
    }
}
