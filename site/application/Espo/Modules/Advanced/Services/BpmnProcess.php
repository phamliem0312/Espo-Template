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

namespace Espo\Modules\Advanced\Services;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\Error;

use Espo\ORM\Entity;

class BpmnProcess extends \Espo\Services\Record
{
    protected function init()
    {
        parent::init();

        $this->addDependency('container');
    }

    protected $readOnlyAttributeList = [
        'flowchartData',
        'createdEntitiesData',
        'flowchartElementsDataHash',
        'status',
        'variables',
        'endedAt',
        'parentProcessId',
        'workflowId',
    ];

    protected $forceSelectAllAttributes = true;

    protected $linkParams = [
        'flowNodes' => [
            'skipAcl' => true,
        ],
    ];

    public function beforeUpdateEntity(Entity $entity, $data)
    {
        $entity->clear('flowchartId');
        $entity->clear('targetId');
        $entity->clear('targetType');
        $entity->clear('startElementId');
    }

    public function stopProcess($id)
    {
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $id);

        if (!$process) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checK($process, 'edit')) {
            throw new Forbidden();
        }

        if (!in_array($process->get('status'), ['Started', 'Paused'])) {
            throw new Error("BpmnProcess: Can't stop not started process.");
        }

        $process->set('status', 'Stopped');
        $this->getEntityManager()->saveEntity($process);
    }

    public function startFlowFromElement(string $processId, string $elementId)
    {
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $processId);

        if (!$process) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($process, 'edit')) {
            throw new Forbidden();
        }

        if ($process->get('status') !== 'Started') {
            throw new Error("BPM: Can't start flow for not started process.");
        }

        $target = $this->getEntityManager()->getEntity($process->get('targetType'), $process->get('targetId'));

        if (!$target) {
            throw new Error("BPM: No target for process to start flow node.");
        }

        $manager = new \Espo\Modules\Advanced\Core\Bpmn\BpmnManager($this->getInjection('container'));

        $manager->processFlow($target, $process, $elementId);
    }

    public function rejectFlowNode(string $flowNodeId)
    {
        $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $flowNodeId);

        if (!$flowNode) {
            throw new NotFound();
        }

        $processId = $flowNode->get('processId');
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $processId);

        if (!$process) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($process, 'edit')) {
            throw new Forbidden();
        }

        $status = $flowNode->get('status');

        if (in_array($status, ['Processed', 'Interrupted', 'Rejected', 'Failed'])) {
            throw new Forbidden();
        }

        $manager = new \Espo\Modules\Advanced\Core\Bpmn\BpmnManager($this->getInjection('container'));

        if ($flowNode->get('status') === 'In Process') {
            $flowNode->set('status', 'Interrupted');

            $this->getEntityManager()->saveEntity($flowNode);

            if (in_array($flowNode->get('elementType'), ['subProcess', 'eventSubProcess', 'callActivity'])) {
                $subProcess = $this->getEntityManager()
                    ->getRepository('BpmnProcess')
                    ->where([
                        'parentProcessFlowNodeId' => $flowNode->id,
                    ])
                    ->findOne();

                if ($subProcess) {
                    $manager->interruptProcess($subProcess);
                }
            }
        }
        else {
            $flowNode->set('status', 'Rejected');
            $this->getEntityManager()->saveEntity($flowNode);
        }
    }

    public function cleanup($id)
    {
        $flowNodeList = $this->getEntityManager()
            ->getRepository('BpmnFlowNode')
            ->where([
                'processId' => $id,
            ])
            ->find();

        foreach ($flowNodeList as $flowNode) {
            $this->getEntityManager()->removeEntity($flowNode);

            $this->getEntityManager()->getRepository('BpmnFlowNode')->deleteFromDb($flowNode->id);
        }
    }

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);

        $flowchartData = $entity->get('flowchartData') ?? (object) [];

        $list = $flowchartData->list ?? [];

        $flowNodeList = $this->getEntityManager()
            ->getRepository('BpmnFlowNode')
            ->where([
                'processId' => $entity->id,
            ])
            ->order('number', true)
            ->limit(0, 400)
            ->find();

        foreach ($list as $item) {
            $this->loadOutlineData($item, $flowNodeList);
        }

        $entity->set('flowchartData', $flowchartData);
    }

    protected function loadOutlineData($item, $flowNodeList)
    {
        $type = $item->type ?? null;
        $id = $item->id ?? null;

        if (!$type || !$id) {
            return;
        }

        if ($type === 'flow') {
            return;
        }

        if ($type === 'eventSubProcess' || $type === 'subProcess') {
            $list = $item->dataList ?? [];

            foreach ($flowNodeList as $flowNode) {
                $status = $flowNode->get('status');

                if ($flowNode->get('elementId') == $id) {
                    $subProcessId = $flowNode->getDataItemValue('subProcessId');

                    $spFlowNodeList = $this->getEntityManager()
                        ->getRepository('BpmnFlowNode')
                        ->where([
                            'processId' => $subProcessId,
                        ])
                        ->order('number', true)
                        ->limit(0, 400)
                        ->find();

                    foreach ($list as $spItem) {
                        $this->loadOutlineData($spItem, $spFlowNodeList);
                    }

                    break;
                }
            }
        }

        foreach ($flowNodeList as $flowNode) {
            $status = $flowNode->get('status');

            if ($flowNode->get('elementId') == $id) {
                if ($status === 'Processed') {
                    $item->outline = 3;
                    return;
                }
            }
        }

        foreach ($flowNodeList as $flowNode) {
            $status = $flowNode->get('status');
            if ($flowNode->get('elementId') == $id) {
                if ($status === 'In Process') {
                    $item->outline = 1;

                    return;
                }
            }
        }

        foreach ($flowNodeList as $flowNode) {
            $status = $flowNode->get('status');

            if ($flowNode->get('elementId') == $id) {
                if ($status == 'Pending') {
                    $item->outline = 2;

                    return;
                }
            }
        }

        foreach ($flowNodeList as $flowNode) {
            $status = $flowNode->get('status');

            if ($flowNode->get('elementId') == $id) {
                if ($status === 'Failed' || $status === 'Interrupted') {
                    $item->outline = 4;

                    return;
                }
            }
        }
    }
}
