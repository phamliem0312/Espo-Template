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
use \Espo\ORM\Entity;

use Espo\Modules\Advanced\Core\Bpmn\Utils\Helper;

class SubProcess extends CallActivity
{
    public function process()
    {
        $target = $this->getNewTargetEntity();

        if (!$target) {
            $GLOBALS['log']->info("BPM Sub-Process: Could not get target for sub-process.");
            $this->fail();
            return;
        }

        $flowNode = $this->getFlowNode();

        $variables = $this->getProcess()->get('variables');
        if (!$variables) $variables = (object) [];
        $variables = clone $variables;

        $this->refreshProcess();

        $parentFlowchartData = $this->getProcess()->get('flowchartData') ?? (object) [];

        $createdEntitiesData = $this->getProcess()->get('createdEntitiesData') ?? (object) [];
        $createdEntitiesData = clone $createdEntitiesData;

        $eData = Helper::getElementsDataFromFlowchartData((object) [
            'list' => $this->getAttributeValue('dataList') ?? [],
        ]);

        $flowchart = $this->getEntityManager()->getEntity('BpmnFlowchart');
        $flowchart->set([
            'targetType' => $target->getEntityType(),
            'data' => (object) [
                'createdEntitiesData' => $parentFlowchartData->createdEntitiesData ?? (object) [],
                'list' => $this->getAttributeValue('dataList') ?? [],
            ],
            'elementsDataHash' => $eData['elementsDataHash'],
            'hasNoneStartEvent' => count($eData['eventStartIdList']) > 0,
            'eventStartIdList'=> $eData['eventStartIdList'],
            'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
            'assignedUserId' => $this->getProcess()->get('assignedUserId'),
            'name' => $this->getAttributeValue('title') ?? 'Sub-Process',
        ]);

        $subProcess = $this->getEntityManager()->createEntity('BpmnProcess', [
            'status' => 'Created',
            'targetId' => $target->id,
            'targetType' => $target->getEntityType(),
            'parentProcessId' => $this->getProcess()->id,
            'parentProcessFlowNodeId' => $flowNode->id,
            'assignedUserId' => $this->getProcess()->get('assignedUserId'),
            'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
            'variables' => $variables,
            'createdEntitiesData' => $createdEntitiesData,
            'startElementId' => $this->getSubProcessStartElementId(),
        ], ['skipCreatedBy' => true, 'skipModifiedBy' => true, 'skipStartProcessFlow' => true]);

        $flowNode->set([
            'status' => 'In Process',
        ]);
        $flowNode->setDataItemValue('subProcessId', $subProcess->id);

        $this->getEntityManager()->saveEntity($flowNode);

        try {
            $this->getManager()->startCreatedProcess($subProcess, $flowchart);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("BPM Sub-Process: Starting sub-process failure. " . $e->getMessage());
            $this->fail();
            return;
        }
    }

    protected function getSubProcessStartElementId()
    {
        return null;
    }
}
