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

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;

class TaskUser extends Activity
{
    public function process()
    {
        $flowNode = $this->getFlowNode();

        $flowNode->set([
            'status' => 'In Process'
        ]);

        $this->getEntityManager()->saveEntity($flowNode);

        $targetString = $this->getAttributeValue('target');
        $target = $this->getSpecificTarget($targetString);

        if (!$target) {
            $GLOBALS['log']->info("BPM TaskUser: Could not get target.");

            $this->fail();

            return;
        }

        $userTask = $this->getEntityManager()->getEntity('BpmnUserTask');

        $userTask->set([
            'flowNodeId' => $this->getFlowNode()->id,
            'actionType' => $this->getAttributeValue('actionType'),
            'processId' => $this->getProcess()->id,
            'targetId' => $target->id,
            'targetType' => $target->getEntityType(),
            'description' => $this->getAttributeValue('description'),
            'instructions' => $this->getInstructionsText(),
            'createdById' => 'system',
        ]);

        $name = $this->getTaskName();

        if (empty($name)) {
            $name = $this->getLanguage()
                ->translateOption($this->getAttributeValue('actionType'), 'actionType', 'BpmnUserTask');
        }

        $teamIdList = $this->getProcess()->getLinkMultipleIdList('teams');

        $assignmentType = $this->getAttributeValue('assignmentType');
        $targetTeamId = $this->getAttributeValue('targetTeamId');
        $targetTeamId = $this->getAttributeValue('targetTeamId');

        $targetUserPosition = $this->getAttributeValue('targetUserPosition');
        if (!$targetUserPosition) {
            $targetUserPosition = null;
        }

        if ($targetTeamId && !in_array($targetTeamId, $teamIdList)) {
            $teamIdList[] = $targetTeamId;
        }

        $assignmentAttributes = null;

        if (strpos($assignmentType, 'rule:') === 0) {
            $assignmentRule = substr($assignmentType, 5);
            $ruleImpl = $this->getAssignmentRuleImplementation($assignmentRule);

            $selectParams = null;

            if ($assignmentRule === 'Least-Busy') {
                $selectParams = [
                    'whereClause' => [['isResolved' => false]]
                ];
            }

            $assignmentAttributes = $ruleImpl->getAssignmentAttributes(
                $userTask, $targetTeamId, $targetUserPosition, null, $selectParams
            );

        } else if (strpos($assignmentType, 'link:') === 0) {
            $link = substr($assignmentType, 5);
            $e = $this->getTarget();

            if (strpos($link, '.') !== false) {
                list($firstLink, $link) = explode('.', $link);
                $e = $e->get($firstLink);
            }

            if ($e instanceof Entity) {
                $field = $link . 'Id';
                $userId = $e->get($field);
                if ($userId) {
                    $assignmentAttributes['assignedUserId'] = $userId;
                }
            }
        }
        else if ($assignmentType === 'processAssignedUser') {
            $userId = $this->getProcess()->get('assignedUserId');

            if ($userId) {
                $assignmentAttributes['assignedUserId'] = $userId;
            }
        }
        else if ($assignmentType === 'specifiedUser') {
            $userId = $this->getAttributeValue('targetUserId');

            if ($userId) {
                $assignmentAttributes['assignedUserId'] = $userId;
            }
        }

        if ($assignmentAttributes) {
            $userTask->set($assignmentAttributes);
        }

        $userTask->set([
            'name' => $name,
            'teamsIds' => $teamIdList
        ]);

        $this->getEntityManager()->saveEntity($userTask, ['skipCreatedBy' => true]);

        $flowNode->setDataItemValue('userTaskId', $userTask->id);

        $this->getEntityManager()->saveEntity($flowNode);

        $createdEntitiesData = $this->getCreatedEntitiesData();

        $alias = $this->getFlowNode()->get('elementId');

        $createdEntitiesData->$alias = (object) [
            'entityId' => $userTask->id,
            'entityType' => $userTask->getEntityType()
        ];

        $this->getProcess()->set('createdEntitiesData', $createdEntitiesData);

        $this->getEntityManager()->saveEntity($this->getProcess(), ['silent' => true]);
    }

    protected function getAssignmentRuleImplementation($assignmentRule)
    {
        $className = 'Espo\\Custom\\Business\\Workflow\\AssignmentRules\\' .
            str_replace('-', '', $assignmentRule);

        if (!class_exists($className)) {
            $className = 'Espo\\Modules\\Advanced\\Business\\Workflow\\AssignmentRules\\' .
                str_replace('-', '', $assignmentRule);

            if (!class_exists($className)) {
                throw new Error('Process TaskUser, Class ' . $className . ' not found.');
            }
        }

        $selectManager = $this->getContainer()->get('selectManagerFactory')->create('BpmnUserTask');
        $reportService = $this->getContainer()->get('serviceFactory')->create('Report');

        return new $className(
            $this->getEntityManager(),
            $selectManager,
            $reportService,
            'BpmnUserTask',
            $this->getElementId(),
            null,
            $this->getFlowNode()->get('flowchartId'),
        );
    }

    public function complete()
    {
        $this->processNextElement();
    }

    protected function getLanguage()
    {
        return $this->getContainer()->get('defaultLanguage');
    }

    protected function setInterrupted()
    {
        $this->cancelUserTask();

        parent::setInterrupted();
    }

    public function cleanupInterrupted()
    {
        parent::cleanupInterrupted();

        $this->cancelUserTask();
    }

    protected function cancelUserTask()
    {
        $userTaskId = $this->getFlowNode()->getDataItemValue('userTaskId');

        if ($userTaskId) {
            $userTask = $this->getEntityManager()->getEntity('BpmnUserTask', $userTaskId);

            if ($userTask && !$userTask->get('isResolved')) {
                $userTask->set([
                    'isCanceled' => true,
                ]);

                $this->getEntityManager()->saveEntity($userTask);
            }
        }
    }

    protected function getTaskName(): ?string
    {
        $text = $this->getAttributeValue('name');

        if ($text) {
            $target = $this->getTarget();
            foreach ($target->getAttributeList() as $a) {
                if (!$target->has($a) || !$target->get($a)) {
                    continue;
                }

                $value = $target->get($a);

                if (is_string($value)) {
                    $text = str_replace('{$'.$a.'}', $value, $text);
                }
            }

            $variables = $this->getVariables() ?? (object) [];

            foreach (get_object_vars($variables) as $key => $value) {
                if ($value && is_string($value)) {
                    $text = str_replace('{$$'.$key.'}', $value, $text);
                }
            }
        }

        return $text;
    }

    protected function getInstructionsText(): ?string
    {
        $text = $this->getAttributeValue('instructions');

        if ($text) {
            $target = $this->getTarget();
            foreach ($target->getAttributeList() as $a) {
                if (!$target->has($a) || !$target->get($a)) {
                    continue;
                }

                $value = $target->get($a);

                if (is_string($value)) {
                    $text = str_replace('{$'.$a.'}', $value, $text);
                }
            }

            $variables = $this->getVariables() ?? (object) [];

            foreach (get_object_vars($variables) as $key => $value) {
                if ($value && is_string($value)) {
                    $text = str_replace('{$$'.$key.'}', $value, $text);
                }
            }
        }

        return $text;
    }
}
