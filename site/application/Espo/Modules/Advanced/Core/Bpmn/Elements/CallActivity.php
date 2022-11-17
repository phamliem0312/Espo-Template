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

class CallActivity extends Activity
{
    public function process()
    {
        $callableType = $this->getAttributeValue('callableType');

        if (!$callableType) {
            $this->fail();
            return;
        }

        $methodName = 'process' . $callableType;
        if (!method_exists($this, $methodName)) {
            $this->fail();
            return;
        }

        $this->$methodName();
    }

    protected function processProcess()
    {
        $target = $this->getNewTargetEntity();

        if (!$target) {
            $GLOBALS['log']->info("BPM Call Activity: Could not get target for sub-process.");
            $this->fail();
            return;
        }

        $flowchartId = $this->getAttributeValue('flowchartId');

        $flowNode = $this->getFlowNode();

        $variables = $this->getProcess()->get('variables');
        if (!$variables) $variables = (object) [];
        $variables = clone $variables;

        $subProcess = $this->getEntityManager()->createEntity('BpmnProcess', [
            'status' => 'Created',
            'flowchartId' => $flowchartId,
            'targetId' => $target->id,
            'targetType' => $target->getEntityType(),
            'parentProcessId' => $this->getProcess()->id,
            'parentProcessFlowNodeId' => $flowNode->id,
            'assignedUserId' => $this->getProcess()->get('assignedUserId'),
            'teamsIds' => $this->getProcess()->getLinkMultipleIdList('teams'),
            'variables' => $variables,
        ], ['skipCreatedBy' => true, 'skipModifiedBy' => true, 'skipStartProcessFlow' => true]);

        $flowNode->set([
            'status' => 'In Process',
        ]);
        $flowNode->setDataItemValue('subProcessId', $subProcess->id);

        $this->getEntityManager()->saveEntity($flowNode);

        try {
            $this->getManager()->startCreatedProcess($subProcess);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error("BPM Call Activity: Starting sub-process failure. " . $e->getMessage());
            $this->fail();
            return;
        }
    }

    public function complete()
    {
        $subProcessId = $this->getFlowNode()->getDataItemValue('subProcessId');

        if ($subProcessId) {
            $subProcess = $this->getEntityManager()->getEntity('BpmnProcess', $subProcessId);
            if ($subProcess) {
                $spCreatedEntitiesData = $subProcess->get('createdEntitiesData') ?? (object) [];
                $createdEntitiesData = $this->getCreatedEntitiesData();

                $spVariables = $subProcess->get('variables') ?? (object) [];
                $variables = $this->getVariables() ?? (object) [];

                $isUpdated = false;
                foreach (get_object_vars($spCreatedEntitiesData) as $key => $value) {
                    if (!isset($createdEntitiesData->$key)) {
                        $createdEntitiesData->$key = $value;
                        $isUpdated = true;
                    }
                }

                $variableList = $this->getAttributeValue('returnVariableList') ?? [];

                foreach ($variableList as $variable) {
                    if (!$variable) continue;
                    if ($variable[0] === '$') {
                        $variable = substr($variable, 1);
                    }
                    $variables->$variable = $spVariables->$variable ?? null;
                }

                if ($isUpdated) {
                    $this->refreshProcess();

                    $this->getProcess()->set('createdEntitiesData', $createdEntitiesData);
                    $this->getProcess()->set('variables', $variables);
                    $this->getEntityManager()->saveEntity($this->getProcess());
                }
            }
        }

        $this->processNextElement();
    }

    protected function getNewTargetEntity()
    {
        $target = $this->getAttributeValue('target');

        return $this->getSpecificTarget($target);
    }
}
