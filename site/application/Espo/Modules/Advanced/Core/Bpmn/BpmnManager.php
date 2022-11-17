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

namespace Espo\Modules\Advanced\Core\Bpmn;

use Espo\Core\Exceptions\Error;
use Espo\Modules\Advanced\Entities\BpmnFlowchart;
use Espo\Modules\Advanced\Entities\BpmnProcess;
use Espo\Modules\Advanced\Entities\BpmnFlowNode;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;

use Espo\Core\Container;
use Espo\Core\Utils\Config;

use DateTime;

use Throwable;

class BpmnManager
{
    protected $container;

    private $conditionalElementTypeList = [
        'eventStartConditional',
        'eventStartConditionalEventSubProcess',
        'eventIntermediateConditionalBoundary',
        'eventIntermediateConditionalCatch',
    ];

    const PROCEED_PENDING_MAX_SIZE = 20000;

    const PENDING_DEFER_PERIOD = '6 hours';

    const PENDING_DEFER_INTERVAL_PERIOD = '10 minutes';

    protected function getEntityManager(): EntityManager
    {
        return $this->container->get('entityManager');
    }

    protected function getConfig(): Config
    {
        return $this->container->get('config');
    }

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    public function startCreatedProcess(BpmnProcess $process, ?BpmnFlowchart $flowchart = null)
    {
        if ($process->get('status') !== 'Created') {
            throw new Error("BPM: Could not start process with status " . $process->get('status') . ".");
        }

        if (!$flowchart) {
            $flowchartId = $process->get('flowchartId');

            if (!$flowchartId) {
                throw new Error("BPM: Could not start process w/o flowchartId specified.");
            }
        }

        $startElementId = $process->get('startElementId');

        $targetId = $process->get('targetId');
        $targetType = $process->get('targetType');

        if (!$targetId || !$targetType) {
            throw new Error("BPM: Could not start process w/o targetId or targetType.");
        }

        if (!$flowchart) {
            $flowchart = $this->getEntityManager()->getEntity('BpmnFlowchart', $flowchartId);
        }

        $target = $this->getEntityManager()->getEntity($targetType, $targetId);

        if (!$flowchart) {
            throw new Error("BPM: Could not find flowchart.");
        }
        if (!$target) {
            throw new Error("BPM: Could not find flowchart.");
        }

        $this->startProcess($target, $flowchart, $startElementId, $process);
    }

    public function startProcess(
        Entity $target,
        BpmnFlowchart $flowchart,
        ?string $startElementId = null,
        ?BpmnProcess $process = null,
        ?string $workflowId = null
    ) {
        $GLOBALS['log']->debug("BPM: startProcess, flowchart {$flowchart->id}, target {$target->id}");

        $elementsDataHash = $flowchart->get('elementsDataHash');

        if ($startElementId) {
            $this->checkFlowchartItemPropriety($elementsDataHash, $startElementId);

            $startItem = $elementsDataHash->$startElementId;

            if (!in_array($startItem->type, [
                'eventStartConditional',
                'eventStartTimer',
                'eventStart',
                'eventStartError',
                'eventStartEscalation',
                'eventStartSignal',
            ])) {
                throw new Error("BPM: startProcess, Bad start event type.");
            }
        }

        $isSubProcess = false;

        if ($process && $process->isSubProcess()) {
            $isSubProcess = true;
        }

        if (!$isSubProcess) {
            $whereClause = [
                'targetId' => $target->id,
                'targetType' => $flowchart->get('targetType'),
                'status' => ['Started', 'Paused'],
                'flowchartId' => $flowchart->id
            ];

            $existingProcess = $this->getEntityManager()
                ->getRepository('BpmnProcess')
                ->where($whereClause)
                ->findOne();

            if ($existingProcess) {
                throw new Error(
                    "Process for flowchart " . $flowchart->id . " can't be run because process is already running."
                );
            }
        }

        $variables = (object) [];
        $createdEntitiesData = (object) [];

        if ($process) {
            if ($process->get('variables')) {
                $variables = $process->get('variables');
            }
            if ($process->get('createdEntitiesData')) {
                $createdEntitiesData = $process->get('createdEntitiesData');
            }
        }

        if (!$process) {
            $process = $this->getEntityManager()->getEntity('BpmnProcess');

            $process->set([
                'name' => $flowchart->get('name'),
                'assignedUserId' => $flowchart->get('assignedUserId'),
                'teamsIds' => $flowchart->getLinkMultipleIdList('teams'),
                'createdById' => 'system',
                'workflowId' => $workflowId,
            ]);
        }

        $process->set([
            'name' => $flowchart->get('name'),
            'flowchartId' => $flowchart->id,
            'targetId' => $target->id,
            'targetType' => $flowchart->get('targetType'),
            'flowchartData' => $flowchart->get('data'),
            'flowchartElementsDataHash' => $elementsDataHash,
            'assignedUserId' => $flowchart->get('assignedUserId'),
            'teamsIds' => $flowchart->getLinkMultipleIdList('teams'),
            'status' => 'Started',
            'createdEntitiesData' => $createdEntitiesData,
            'startElementId' => $startElementId,
            'variables' => $variables,
        ]);

        $this->getEntityManager()->saveEntity($process, [
            'skipCreatedBy' => true,
            'skipModifiedBy' => true,
            'skipStartProcessFlow' => true,
        ]);

        if ($startElementId) {
            $flowNode = $this->prepareFlow($target, $process, $startElementId);

            if ($flowNode) {
                $this->prepareEventSubProcesses($target, $process);
                $this->processPreparedFlowNode($target, $flowNode, $process);
            }
        } else {
            $startElementIdList = $this->getProcessElementWithoutIncomingFlowIdList($process);

            $flowNodeList = [];

            foreach ($startElementIdList as $elementId) {
                $flowNode = $this->prepareFlow($target, $process, $elementId);
                $flowNodeList[] = $flowNode;
            }

            if (!count($flowNodeList)) {
                $this->endProcess($process);
            } else {
                $this->prepareEventSubProcesses($target, $process);
            }

            foreach ($flowNodeList as $flowNode) {
                $this->processPreparedFlowNode($target, $flowNode, $process);
            }
        }
    }

    protected function prepareEventSubProcesses(Entity $target, BpmnProcess $process)
    {
        $standByflowNodeList = [];

        foreach ($this->getProcessElementEventSubProcessIdList($process) as $id) {
            $flowNode = $this->prepareStandbyFlow($target, $process, $id);

            if ($flowNode) {
                $standByflowNodeList[] = $flowNode;
            }
        }

        foreach ($standByflowNodeList as $flowNode) {
            $this->processPreparedFlowNode($target, $flowNode, $process);
        }
    }

    protected function getProcessElementEventSubProcessIdList(BpmnProcess $process)
    {
        $resultElementIdList = [];

        $elementIdList = $process->getElementIdList();

        foreach ($elementIdList as $id) {
            $item = $process->getElementDataById($id);

            if (empty($item->type)) {
                continue;
            }

            if ($item->type === 'eventSubProcess') {
                $resultElementIdList[] = $id;
            }
        }

        return $resultElementIdList;
    }

    protected function getProcessElementWithoutIncomingFlowIdList(BpmnProcess $process)
    {
        $resultElementIdList = [];

        $elementIdList = $process->getElementIdList();

        foreach ($elementIdList as $id) {
            $item = $process->getElementDataById($id);

            if (empty($item->type)) {
                continue;
            }

            if (
                $item->type !== 'eventStart' &&
                (
                    in_array($item->type, ['flow', 'eventIntermediateLinkCatch']) ||
                    strpos($item->type, 'eventStart') === 0
                )
            ) {
                continue;
            }

            if (substr($item->type, -15) === 'EventSubProcess') {
                continue;
            }

            if ($item->type === 'eventSubProcess') {
                continue;
            }

            if (!empty($item->previousElementIdList)) {
                continue;
            }

            if (!empty($item->isForCompensation)) {
                continue;
            }

            $resultElementIdList[] = $id;
        }

        return $resultElementIdList;
    }

    public function prepareStandbyFlow(Entity $target, BpmnProcess $process, $elementId)
    {
        $GLOBALS['log']->debug("BPM: prepareStandbyFlow, process {$process->id}, element {$elementId}");

        if ($process->get('status') !== 'Started') {
            $GLOBALS['log']->info(
                "BPM: Process status ".$process->id ." is not 'Started' but ".
                $process->get('status').", hence can't create standby flow."
            );

            return null;
        }

        $item = $process->getElementDataById($elementId);
        $eventStartData = $item->eventStartData ?? (object) [];
        $startEventType = $eventStartData->type ?? null;

        if (!$startEventType) {
            return null;
        }

        if (!$eventStartData->id) {
            return null;
        }

        if (in_array($startEventType, ['eventStartError', 'eventStartEscalation'])) {
            return null;
        }

        $elementType = $startEventType . 'EventSubProcess';

        $flowNode = $this->getEntityManager()->createEntity('BpmnFlowNode', [
            'status' => 'Created',
            'elementType' => $elementType,
            'elementData' => $eventStartData,
            'flowchartId' => $process->get('flowchartId'),
            'processId' => $process->id,
            'targetType' => $target->getEntityType(),
            'targetId' => $target->id,
            'data' => (object) [
                'subProcessElementId' => $elementId,
                'subProcessTarget' => $item->target ?? null,
                'subProcessIsInterrupting' => $eventStartData->isInterrupting ?? false,
                'subProcessTitle' => $eventStartData->title ?? null,
                'subProcessStartData' => $eventStartData,
            ],
        ]);

        return $flowNode;
    }

    protected function checkFlowchartItemPropriety(\StdClass $elementsDataHash, $elementId)
    {
        if (!$elementId) {
            throw new Error('No start event element.');
        }

        if (!is_object($elementsDataHash)) {
            throw new Error();
        }

        if (!isset($elementsDataHash->$elementId) || !is_object($elementsDataHash->$elementId)) {
            throw new Error('Not existing start event element id.');
        }

        $item = $elementsDataHash->$elementId;

        if (!isset($item->type)) {
            throw new Error('Bad start event element.');
        }
    }

    public function prepareFlow(
        Entity $target,
        BpmnProcess $process,
        $elementId,
        $previousFlowNodeId = null,
        $previousFlowNodeElementType = null,
        $divergentFlowNodeId = null,
        $allowEndedProcess = false
    ) {
        $GLOBALS['log']->debug("BPM: prepareFlow, process {$process->id}, element {$elementId}");

        if (!$allowEndedProcess && $process->get('status') !== 'Started') {
            $GLOBALS['log']->info(
                "BPM: Process status ".$process->id ." is not 'Started' but ".
                $process->get('status').", hence can't be processed."
            );

            return null;
        }

        $elementsDataHash = $process->get('flowchartElementsDataHash');

        $this->checkFlowchartItemPropriety($elementsDataHash, $elementId);

        if ($target->getEntityType() !== $process->get('targetType') || $target->id !== $process->get('targetId')) {
            throw new Error();
        }

        $item = $elementsDataHash->$elementId;

        $elementType = $item->type;

        $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode');

        $flowNode->set([
            'status' => 'Created',
            'elementId' => $elementId,
            'elementType' => $elementType,
            'elementData' => $item,
            'flowchartId' => $process->get('flowchartId'),
            'processId' => $process->id,
            'previousFlowNodeElementType' => $previousFlowNodeElementType,
            'previousFlowNodeId' => $previousFlowNodeId,
            'divergentFlowNodeId' => $divergentFlowNodeId,
            'targetType' => $target->getEntityType(),
            'targetId' => $target->id,
        ]);

        $this->getEntityManager()->saveEntity($flowNode);

        return $flowNode;
    }

    public function processPreparedFlowNode(Entity $target, BpmnFlowNode $flowNode, BpmnProcess $process)
    {
        $impl = $this->getFlowNodeImplementation($target, $flowNode, $process);

        $impl->beforeProcess();

        if (!$impl->isProcessable()) {
            $GLOBALS['log']->info("BPM: Can't process not processable node ".$flowNode->id.".");
            return;
        }

        $impl->process();

        $impl->afterProcess();
    }

    public function processFlow(
        Entity $target,
        BpmnProcess $process,
        $elementId,
        $previousFlowNodeId = null,
        $previousFlowNodeElementType = null,
        $divergentFlowNodeId = null
    ) {
        $flowNode = $this->prepareFlow(
            $target,
            $process,
            $elementId,
            $previousFlowNodeId,
            $previousFlowNodeElementType,
            $divergentFlowNodeId
        );

        if ($flowNode) {
            $this->processPreparedFlowNode($target, $flowNode, $process);
        }

        return $flowNode;
    }

    public function processPendingFlows()
    {
        $limit = $this->getConfig()->get('bpmnProceedPendingMaxSize', self::PROCEED_PENDING_MAX_SIZE);

        $GLOBALS['log']->debug("BPM: processPendingFlows");

        $repository = $this->getEntityManager()
            ->getRepository('BpmnFlowNode');

        if (method_exists($repository, 'sth')) {
            $repository->sth();
        }

        $flowNodeList = $repository
            ->where([
                'OR' => [
                    [
                        'status' => 'Pending',
                        'elementType' => 'eventIntermediateTimerCatch',
                        'proceedAt<=' => date('Y-m-d H:i:s'),
                    ],
                    [
                        'status' => 'Pending',
                        'elementType' => 'eventIntermediateConditionalCatch',
                    ],
                    [
                        'status' => 'Pending',
                        'elementType' => 'eventIntermediateMessageCatch',
                    ],
                    [
                        'status' => 'Pending',
                        'elementType' => 'eventIntermediateConditionalBoundary',
                    ],
                    [
                        'status' => 'Pending',
                        'elementType' => 'eventIntermediateTimerBoundary',
                        'proceedAt<=' => date('Y-m-d H:i:s'),
                    ],
                    [
                        'status' => 'Pending',
                        'elementType' => 'eventIntermediateMessageBoundary',
                    ],
                    [
                        'status' => 'Pending',
                        'elementType' => 'taskSendMessage',
                    ],
                    [
                        'status' => 'Standby',
                        'elementType' => 'eventStartTimerEventSubProcess',
                        'proceedAt<=' => date('Y-m-d H:i:s'),
                    ],
                    [
                        'status' => 'Standby',
                        'elementType' => 'eventStartConditionalEventSubProcess',
                    ],
                ],
                'isLocked' => false,
            ])
            ->select([
                'id',
                'elementType',
                'isLocked',
                'deferredAt',
                'isDeferred',
                'createdAt',
            ])
            ->order('number', false)
            ->limit(0, $limit)
            ->find();

        foreach ($flowNodeList as $flowNode) {
            try {
                $toProcess = $this->checkPendingFlow($flowNode);
            }
            catch (Throwable $e) {
                $GLOBALS['log']->error($e->getMessage());

                // @todo Destroy item.

                continue;
            }

            if (!$toProcess) {
                continue;
            }

            try {
                $this->controlDeferPendingFlow($flowNode);
            }
            catch (Throwable $e) {
                $GLOBALS['log']->error($e->getMessage());

                // @todo Destroy item.

                continue;
            }

            try {

                $this->proceedPendingFlow($flowNode);
            }
            catch (Throwable $e) {
                $GLOBALS['log']->error($e->getMessage());

                // @todo Destroy item.
            }
        }

        $this->cleanupSignalListeners();

        $this->processTriggeredSignals();
    }

    /**
     * Preventing checking conditional nodes too often.
     *
     * @return bool False if flow checking should be skipped.
     */
    private function checkPendingFlow(BpmnFlowNode $flowNode): bool
    {
        $elementType = $flowNode->get('elementType');

        if (!in_array($elementType, $this->conditionalElementTypeList)) {
            return true;
        }

        if (!$flowNode->get('deferredAt')) {
            return true;
        }

        if (!$flowNode->get('isDeferred')) {
            return true;
        }

        $period = $this->getConfig()->get('bpmnPendingDeferIntervalPeriod', self::PENDING_DEFER_INTERVAL_PERIOD);

        $diff = DateTime::createFromFormat('Y-m-d H:i:s', $flowNode->get('deferredAt'))
            ->diff(
                (new DateTime())->modify('-' . $period)
            );

        if (!$diff->invert) {
            return true;
        }

        return false;
    }

    private function controlDeferPendingFlow(BpmnFlowNode $flowNode)
    {
        $elementType = $flowNode->get('elementType');

        if (!in_array($elementType, $this->conditionalElementTypeList)) {
            return;
        }

        $from = $flowNode->get('deferredAt') ?? $flowNode->get('createdAt');

        if (!$from) {
            return;
        }

        $period = $flowNode->get('deferredAt') ?
            $this->getConfig()->get('bpmnPendingDeferIntervalPeriod', self::PENDING_DEFER_INTERVAL_PERIOD) :
            $this->getConfig()->get('bpmnPendingDeferPeriod', self::PENDING_DEFER_PERIOD);

        $diff = DateTime::createFromFormat('Y-m-d H:i:s', $from)
            ->diff(
                (new DateTime())->modify('-' . $period)
            );

        if (
            $diff->invert &&
            $flowNode->get('deferredAt') &&
            !$flowNode->get('isDeferred')
        ) {
            // If a node was set as not deferred, it should be checked only once.

            $flowNode->set([
                'isDeferred' => true,
            ]);

            $this->getEntityManager()->saveEntity($flowNode);

            return;
        }

        if ($diff->invert) {
            return;
        }

        $flowNode->set([
            'deferredAt' => (new DateTime())->format('Y-m-d H:i:s'),
            'isDeferred' => true,
        ]);

        $this->getEntityManager()->saveEntity($flowNode);
    }

    public function cleanupSignalListeners()
    {
        $limit = $this->getConfig()->get('bpmnProceedPendingMaxSize', self::PROCEED_PENDING_MAX_SIZE);

        $listenerList = $this->getEntityManager()
            ->getRepository('BpmnSignalListener')
            ->select(['id', 'name', 'flowNodeId'])
            ->order('number')
            ->leftJoin([[
                'BpmnFlowNode',
                'flowNode',
                ['flowNode.id:' => 'flowNodeId'],
            ]])
            ->where([
                'OR' => [
                    'flowNode.deleted' => true,
                    'flowNode.id' => null,
                    'flowNode.status!=' => ['Standby', 'Created', 'Pending'],
                ],
            ])
            ->limit(0, $limit)
            ->find();

        foreach ($listenerList as $item) {
            $GLOBALS['log']->debug("BPM: Delete not actual signal listener for flow node " . $item->get('flowNodeId'));

            $this->getEntityManager()->getRepository('BpmnSignalListener')->deleteFromDb($item->id);
        }
    }

    public function processTriggeredSignals()
    {
        $limit = $this->getConfig()->get('bpmnProceedPendingMaxSize', self::PROCEED_PENDING_MAX_SIZE);

        $GLOBALS['log']->debug("BPM: processTriggeredSignals");

        $listenerList = $this->getEntityManager()
            ->getRepository('BpmnSignalListener')
            ->select(['id', 'name', 'flowNodeId'])
            ->order('number')
            ->where([
                'isTriggered' => true,
            ])
            ->limit(0, $limit)
            ->find();

        foreach ($listenerList as $item) {
            $this->getEntityManager()->getRepository('BpmnSignalListener')->deleteFromDb($item->id);

            $flowNodeId = $item->get('flowNodeId');

            if (!$flowNodeId) {
                continue;
            }

            $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $flowNodeId);

            if (!$flowNode) {
                $GLOBALS['log']->notice("BPM: Flow Node {$flowNodeId} not found.");

                continue;
            }

            try {
                $this->proceedPendingFlow($flowNode);
            }
            catch (Throwable $e) {
                $GLOBALS['log']->error($e->getMessage());
            }
        }
    }

    public function setFlowNodeFailed(BpmnFlowNode $flowNode)
    {
        $flowNode->set([
            'status' => 'Failed',
            'processedAt' => date('Y-m-d H:i:s'),
        ]);

        $this->getEntityManager()->saveEntity($flowNode);
    }

    protected function checkFlowIsActual($target, $flowNode, $process)
    {
        if (!$process) {
            $this->setFlowNodeFailed($flowNode);

            throw new Error("Could not find process " . $flowNode->get('processId') . ".");
        }

        if (!$target) {
            $this->setFlowNodeFailed($flowNode);
            $this->interruptProcess($process);

            throw new Error("Could not find target for process " . $process->id . ".");
        }

        if ($process->get('status') === 'Paused') {
            $this->unlockFlowNode($flowNode);

            throw new Error();
        }

        if ($process->get('status') !== 'Started') {
            $this->setFlowNodeFailed($flowNode);

            throw new Error("Attempted to continue flow of not active process " . $process->id . ".");
        }
    }

    protected function lockTable()
    {
        $this->getEntityManager()->getPdo()->query('LOCK TABLES `bpmn_flow_node` WRITE');
    }

    protected function unlockTable()
    {
        $this->getEntityManager()->getPdo()->query('UNLOCK TABLES');
    }

    protected function getAndLockFlowNodeById($id)
    {
        $this->lockTable();

        $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $id);

        if (!$flowNode) {
            $this->unlockTable();

            throw new Error("Can't find Flow Node " . $id . ".");
        }

        if ($flowNode->get('isLocked')) {
            $this->unlockTable();

            throw new Error("Can't get locked Flow Node " . $id . ".");
        }

        $this->lockFlowNode($flowNode);

        $this->unlockTable();

        return $flowNode;
    }

    protected function lockFlowNode(BpmnFlowNode $flowNode)
    {
        $flowNode->set('isLocked', true);

        $this->getEntityManager()->saveEntity($flowNode);
    }

    protected function unlockFlowNode(BpmnFlowNode $flowNode)
    {
        $flowNode->set('isLocked', false);

        $this->getEntityManager()->saveEntity($flowNode);
    }

    public function proceedPendingFlow(BpmnFlowNode $flowNode)
    {
        $GLOBALS['log']->debug("BPM: proceedPendingFlow, node {$flowNode->id}");

        $flowNode = $this->getAndLockFlowNodeById($flowNode->id);
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $flowNode->get('processId'));
        $target = $this->getEntityManager()->getEntity($flowNode->get('targetType'), $flowNode->get('targetId'));

        if ($flowNode->get('status') !== 'Pending' && $flowNode->get('status') !== 'Standby') {
            $this->unlockFlowNode($flowNode);

            $GLOBALS['log']->info(
                "BPM: Can not proceed not pending or standby (".$flowNode->get('status').") flow node in process " .
                $process->id . "."
            );

            return;
        }

        $this->checkFlowIsActual($target, $flowNode, $process);

        $impl = $this->getFlowNodeImplementation($target, $flowNode, $process);

        $impl->beforeProceedPending();
        $impl->proceedPending();
        $impl->afterProceedPending();

        $this->unlockFlowNode($flowNode);
    }

    public function completeFlow(BpmnFlowNode $flowNode)
    {
        $GLOBALS['log']->debug("BPM: completeFlow, node {$flowNode->id}");

        $flowNode = $this->getAndLockFlowNodeById($flowNode->id);
        $target = $this->getEntityManager()->getEntity($flowNode->get('targetType'), $flowNode->get('targetId'));
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $flowNode->get('processId'));

        if ($flowNode->get('status') !== 'In Process') {
            throw new Error("BPM: Can not complete not 'In Process' flow node in process " . $process->id . ".");
        }

        if ($flowNode->get('elementType') !== 'eventSubProcess') {
            $this->checkFlowIsActual($target, $flowNode, $process);
        }

        $this->getFlowNodeImplementation($target, $flowNode, $process)->complete();
        $this->unlockFlowNode($flowNode);
    }

    public function failFlow(BpmnFlowNode $flowNode)
    {
        $GLOBALS['log']->debug("BPM: failFlow, node {$flowNode->id}");

        $flowNode = $this->getAndLockFlowNodeById($flowNode->id);
        $process = $this->getEntityManager()->getEntity('BpmnProcess', $flowNode->get('processId'));
        $target = $this->getEntityManager()->getEntity($flowNode->get('targetType'), $flowNode->get('targetId'));

        if (!$this->isFlowNodeIsActual($flowNode)) {
            throw new Error("Can not proceed not In Process flow node in process " . $process->id . ".");
        }

        $this->checkFlowIsActual($target, $flowNode, $process);
        $this->getFlowNodeImplementation($target, $flowNode, $process)->fail();
        $this->unlockFlowNode($flowNode);
    }

    public function cancelActivityByBoundaryEvent(BpmnFlowNode $flowNode)
    {
        $GLOBALS['log']->debug("BPM: cancelActivityByBoundaryEvent, node {$flowNode->id}");

        $activityFlowNode = $this->getAndLockFlowNodeById($flowNode->get('previousFlowNodeId'));

        $process = $this->getEntityManager()->getEntity('BpmnProcess', $flowNode->get('processId'));
        $target = $this->getEntityManager()->getEntity($flowNode->get('targetType'), $flowNode->get('targetId'));

        if (!$this->isFlowNodeIsActual($activityFlowNode)) {
            $this->unlockFlowNode($activityFlowNode);

            return;
        }

        $this->checkFlowIsActual($target, $activityFlowNode, $process);
        $this->getFlowNodeImplementation($target, $activityFlowNode, $process)->interrupt();

        if (in_array($activityFlowNode->get('elementType'), ['callActivity', 'subProcess', 'eventSubProcess'])) {
            $subProcess = $this->getEntityManager()
                ->getRepository('BpmnProcess')
                ->where([
                    'parentProcessFlowNodeId' => $activityFlowNode->id,
                ])
                ->findOne();

            if ($subProcess) {
                try {
                    $this->interruptProcess($subProcess);
                }
                catch (Throwable $e) {
                    $GLOBALS['log']->error("BPM: Fail when tried to interrupt sub-process; " . $e->getMessage());
                }
            }
        }

        $this->unlockFlowNode($activityFlowNode);
    }

    protected function isFlowNodeIsActual(BpmnFlowNode $flowNode)
    {
        return !in_array($flowNode->get('status'), ['Failed', 'Rejected', 'Processed', 'Interrupted']);
    }

    protected function failProcessFlow(Entity $target, BpmnFlowNode $flowNode, BpmnProcess $process)
    {
        $this->getFlowNodeImplementation($target, $flowNode, $process)->fail();
    }

    public function getFlowNodeImplementation(Entity $target, BpmnFlowNode $flowNode, BpmnProcess $process)
    {
        $elementType = $flowNode->get('elementType');

        $className = 'Espo\\Modules\\Advanced\\Core\\Bpmn\\Elements\\' . ucfirst($elementType);

        $flowNodeImpementation = new $className($this->container, $this, $target, $flowNode, $process);

        return $flowNodeImpementation;
    }

    protected function getActiveFlowCount(BpmnProcess $process)
    {
        return $this->getEntityManager()
            ->getRepository('BpmnFlowNode')
            ->where([
                'status!=' => ['Processed', 'Rejected', 'Failed', 'Interrupted', 'Standby'],
                'processId' => $process->id,
                'elementType!=' => 'eventSubProcess',
            ])
            ->count();
    }

    protected function getActiveEventSubProcessCount(BpmnProcess $process)
    {
        return $this->getEntityManager()
            ->getRepository('BpmnFlowNode')
            ->where([
                'status' => ['In Process'],
                'processId' => $process->id,
                'elementType' => 'eventSubProcess',
            ])
            ->count();
    }

    public function endProcessFlow(BpmnFlowNode $flowNode, BpmnProcess $process)
    {
        $GLOBALS['log']->debug("BPM: endProcessFlow, node {$flowNode->id}");

        if ($this->isFlowNodeIsActual($flowNode)) {
            $flowNode->set('status', 'Rejected');

            $this->getEntityManager()->saveEntity($flowNode);
        }

        $this->tryToEndProcess($process);
    }

    public function tryToEndProcess(BpmnProcess $process)
    {
        $GLOBALS['log']->debug("BPM: tryToEndProcess, process {$process->id}");

        if (!$this->getActiveFlowCount($process) && in_array($process->get('status'), ['Started', 'Paused'])) {
            if ($this->getActiveEventSubProcessCount($process)) {
                $this->rejectActiveFlows($process);

                return;
            }

            $this->endProcess($process);
        }
    }

    public function endProcess(BpmnProcess $process, $interruptSubProcesses = false)
    {
        $GLOBALS['log']->debug("BPM: endProcess, process {$process->id}");

        $this->rejectActiveFlows($process);

        if (!in_array($process->get('status'), ['Started', 'Paused'])) {
            throw new Error('Process ' . $process->id . " can't be ended because it's not active.");
        }

        if ($interruptSubProcesses) {
            $this->interruptSubProcesses($process);
        }

        $process->set([
            'status' => 'Ended',
            'endedAt' => date('Y-m-d H:i:s'),
            'modifiedById' => 'system',
        ]);

        $this->getEntityManager()->saveEntity($process, ['skipModifiedBy' => true]);

        if ($process->hasParentProcess()) {
            $flowNode = $this->getEntityManager()
                ->getEntity('BpmnFlowNode', $process->get('parentProcessFlowNodeId'));

            if ($flowNode) {
                $this->completeFlow($flowNode);
            }
        }
    }

    public function escalate(BpmnProcess $process, $escalationCode = null)
    {
        $GLOBALS['log']->debug("BPM: escalate, process {$process->id}");

        if (!in_array($process->get('status'), ['Started', 'Paused'])) {
            throw new Error('Process ' . $process->id . " can't have an escalation because it's not active.");
        }

        $escalationEventSubProcessFlowNode = $this->prepareEscalationEventSubProcessFlowNode($process, $escalationCode);

        if ($escalationEventSubProcessFlowNode) {
            $GLOBALS['log']->info("BPM: escalation event sub-process found");

            $target = $this->getEntityManager()->getEntity($process->get('targetType'), $process->get('targetId'));

            if ($target) {
                $isInterrupting = (
                    $escalationEventSubProcessFlowNode->getElementDataItemValue('eventStartData') ??
                    (object) []
                )->isInterrupting ?? false;

                if ($isInterrupting) {
                    $this->interruptProcessByEventSubProcess($process, $escalationEventSubProcessFlowNode);
                }

                $this->processPreparedFlowNode($target, $escalationEventSubProcessFlowNode, $process);
            }

            return;
        }

        if ($process->hasParentProcess()) {
            $parentProcess = $this->getEntityManager()
                ->getEntity('BpmnProcess', $process->get('parentProcessId'));
            $parentFlowNode = $this->getEntityManager()
                ->getEntity('BpmnFlowNode', $process->get('parentProcessFlowNodeId'));

            if ($parentProcess && $parentFlowNode ) {
                $target = $this->getEntityManager()->getEntity(
                    $parentFlowNode->get('targetType'), $parentFlowNode->get('targetId')
                );

                $boundaryFlowNode = $this->prepareBoundaryEscalationFlowNode(
                    $parentFlowNode,
                    $parentProcess,
                    $escalationCode
                );

                if ($boundaryFlowNode && $target) {
                    $this->processPreparedFlowNode($target, $boundaryFlowNode, $parentProcess);
                }
            }
        }
    }


    public function broadcastSignal(string $signal)
    {
        $GLOBALS['log']->debug("BPM: broadcastSignal");

        $itemList = $this->getEntityManager()
            ->getRepository('BpmnSignalListener')
            ->select(['id', 'flowNodeId'])
            ->where([
                'name' => $signal,
                'isTriggered' => false,
            ])
            ->order('number')
            ->find();

        foreach ($itemList as $item) {
            $this->getEntityManager()->getRepository('BpmnSignalListener')->deleteFromDb($item->id);
        }

        foreach ($itemList as $item) {
            $flowNodeId = $item->get('flowNodeId');

            $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $flowNodeId);

            if (!$flowNode) {
                $GLOBALS['log']->notice("BPM: broadcastSignal, flow node {$flowNodeId} not found.");

                continue;
            }

            try {
                $this->proceedPendingFlow($flowNode);
            }
            catch (Throwable $e) {
                $GLOBALS['log']->error($e->getMessage());
            }
        }
    }

    public function prepareBoundaryEscalationFlowNode(
        BpmnFlowNode $flowNode,
        BpmnProcess $process,
        $escalationCode = null
    ) {
        $attachedElementIdList = $process->getAttachedToFlowNodeElementIdList($flowNode);

        $found1Id = null;
        $found2Id = null;

        foreach ($attachedElementIdList as $id) {
            $item = $process->getElementDataById($id);

            if (!isset($item->type)) {
                continue;
            }

            if ($item->type === 'eventIntermediateEscalationBoundary') {
                if (!$escalationCode) {
                    if (empty($item->escalationCode)) {
                        $found1Id = $id;

                        break;
                    }
                }
                else {
                    if (empty($item->escalationCode)) {
                        if (!$found2Id) {
                            $found2Id = $id;
                        }
                    }
                    else {
                        if ($item->escalationCode == $escalationCode) {
                            $found1Id = $id;

                            break;
                        }
                    }
                }
            }
        }

        $elementId = $found1Id ? $found1Id : $found2Id;

        $boundaryFlowNode = null;

        if ($elementId) {
            $target = $this->getEntityManager()->getEntity($flowNode->get('targetType'), $flowNode->get('targetId'));
            if ($target) {
                $boundaryFlowNode = $this->prepareFlow(
                    $target, $process, $elementId, $flowNode->id, $flowNode->get('elementType')
                );
            }
        }

        return $boundaryFlowNode;
    }

    public function endProcessWithError(BpmnProcess $process, $errorCode = null)
    {
        $GLOBALS['log']->debug("BPM: endProcessWithError, process {$process->id}");

        $this->rejectActiveFlows($process);

        if (!in_array($process->get('status'), ['Started', 'Paused'])) {
            throw new Error('Process ' . $process->id . " can't be ended because it's not active.");
        }

        $this->interruptSubProcesses($process);

        $process->set([
            'status' => 'Ended',
            'endedAt' => date('Y-m-d H:i:s'),
            'modifiedById' => 'system',
        ]);

        $this->getEntityManager()->saveEntity($process, ['skipModifiedBy' => true]);

        $this->triggerError($process, $errorCode);
    }

    public function triggerError(BpmnProcess $process, $errorCode = null)
    {
        $GLOBALS['log']->info("BPM: triggerError");

        $errorEventSubProcessFlowNode = $this->prepareErrorEventSubProcessFlowNode($process, $errorCode);

        if ($errorEventSubProcessFlowNode) {
            $GLOBALS['log']->info("BPM: error event sub-process found");

            $target = $this->getEntityManager()->getEntity($process->get('targetType'), $process->get('targetId'));

            if ($target) {
                $this->processPreparedFlowNode($target, $errorEventSubProcessFlowNode, $process);
            }

            return;
        }

        if (!$process->hasParentProcess()) {
            return;
        }

        $parentProcess = $this->getEntityManager()->getEntity('BpmnProcess', $process->get('parentProcessId'));
        $parentFlowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $process->get('parentProcessFlowNodeId'));

        if (!$parentProcess || !$parentFlowNode) {
            return;
        }

        $parentFlowNode->setDataItemValue('errorCode', $errorCode);
        $parentFlowNode->setDataItemValue('errorTriggered', true);

        $this->getEntityManager()->saveEntity($parentFlowNode);

        $this->failFlow($parentFlowNode);
    }

    protected function interruptSubProcesses(BpmnProcess $process)
    {
        $subProcessList = $this->getEntityManager()
            ->getRepository('BpmnProcess')
            ->where([
                'parentProcessId' => $process->id,
                'status' => ['Started', 'Paused'],
            ])
            ->find();

        foreach ($subProcessList as $subProcess) {
            try {
                $this->interruptProcess($subProcess);
            } catch (Throwable $e) {
                $GLOBALS['log']->error($e->getMessage());
            }
        }
    }

    public function interruptProcess(BpmnProcess $process)
    {
        $GLOBALS['log']->debug("BPM: interruptProcess, process {$process->id}");

        $process = $this->getEntityManager()->getEntity('BpmnProcess', $process->id);

        if (!in_array($process->get('status'), ['Started', 'Paused'])) {
            throw new Error('Process ' . $process->id . " can't be interrupted because it's not active.");
        }

        $this->rejectActiveFlows($process);

        $process->set([
            'status' => 'Interrupted',
        ]);

        $this->getEntityManager()->saveEntity($process, ['skipModifiedBy' => true]);

        $this->interruptSubProcesses($process);
    }

    public function interruptProcessByEventSubProcess(BpmnProcess $process, BpmnFlowNode $interruptingFlowNode)
    {
        $GLOBALS['log']->debug("BPM: interruptProcessByEventSubProcess, process {$process->id}");

        $process = $this->getEntityManager()->getEntity('BpmnProcess', $process->id);

        if (!in_array($process->get('status'), ['Started', 'Paused'])) {
            throw new Error('Process ' . $process->id . " can't be interrupted because it's not active.");
        }

        $this->rejectActiveFlows($process, $interruptingFlowNode->id);

        $process->set([
            'status' => 'Interrupted',
        ]);

        $this->getEntityManager()->saveEntity($process, ['skipModifiedBy' => true]);

        $this->interruptSubProcesses($process);
    }

    public function prepareEscalationEventSubProcessFlowNode(BpmnProcess $process, $escalationCode = null)
    {
        $found1Id = null;
        $found2Id = null;

        foreach ($this->getProcessElementEventSubProcessIdList($process) as $id) {
            $item = $process->getElementDataById($id);
            if (($item->type ?? null) !== 'eventSubProcess') {
                continue;
            }

            $item = $item->eventStartData ?? (object) [];

            if (($item->type ?? null) !== 'eventStartEscalation') {
                continue;
            }

            if (!$escalationCode) {
                if (empty($item->escalationCode)) {
                    $found1Id = $id;
                    break;
                }
            } else {
                if (empty($item->escalationCode)) {
                    if (!$found2Id) {
                        $found2Id = $id;
                    }
                } else {
                    if ($item->escalationCode == $escalationCode) {
                        $found1Id = $id;
                        break;
                    }
                }
            }
        }

        $elementId = $found1Id ? $found1Id : $found2Id;

        $flowNode = null;

        if ($elementId) {
            $target = $this->getEntityManager()->getEntity($process->get('targetType'), $process->get('targetId'));

            if ($target) {
                $flowNode = $this->prepareFlow($target, $process, $elementId, null, null, null, true);
            }
        }

        return $flowNode;
    }

    public function prepareErrorEventSubProcessFlowNode(BpmnProcess $process, $errorCode = null)
    {
        $found1Id = null;
        $found2Id = null;

        foreach ($this->getProcessElementEventSubProcessIdList($process) as $id) {
            $item = $process->getElementDataById($id);

            if (($item->type ?? null) !== 'eventSubProcess') {
                continue;
            }

            $item = $item->eventStartData ?? (object) [];

            if (($item->type ?? null) !== 'eventStartError') {
                continue;
            }

            if (!$errorCode) {
                if (empty($item->errorCode)) {
                    $found1Id = $id;
                    break;
                }
            } else {
                if (empty($item->errorCode)) {
                    if (!$found2Id) {
                        $found2Id = $id;
                    }
                } else {
                    if ($item->errorCode == $errorCode) {
                        $found1Id = $id;
                        break;
                    }
                }
            }
        }

        $elementId = $found1Id ? $found1Id : $found2Id;

        $flowNode = null;

        if ($elementId) {
            $target = $this->getEntityManager()->getEntity($process->get('targetType'), $process->get('targetId'));

            if ($target) {
                $flowNode = $this->prepareFlow($target, $process, $elementId, null, null, null, true);
            }
        }

        return $flowNode;
    }

    public function prepareBoundaryErrorFlowNode(BpmnFlowNode $flowNode, BpmnProcess $process, $errorCode = null)
    {
        $attachedElementIdList = $process->getAttachedToFlowNodeElementIdList($flowNode);

        $found1Id = null;
        $found2Id = null;

        foreach ($attachedElementIdList as $id) {
            $item = $process->getElementDataById($id);

            if (!isset($item->type)) {
                continue;
            }

            if ($item->type === 'eventIntermediateErrorBoundary') {
                if (!$errorCode) {
                    if (empty($item->errorCode)) {
                        $found1Id = $id;

                        break;
                    }
                } else {
                    if (empty($item->errorCode)) {
                        if (!$found2Id) {
                            $found2Id = $id;
                        }
                    } else {
                        if ($item->errorCode == $errorCode) {
                            $found1Id = $id;

                            break;
                        }
                    }
                }
            }
        }

        $errorElementId = $found1Id ? $found1Id : $found2Id;

        $boundaryFlowNode = null;

        if ($errorElementId) {
            $target = $this->getEntityManager()->getEntity($flowNode->get('targetType'), $flowNode->get('targetId'));

            if ($target) {
                $boundaryFlowNode = $this->prepareFlow(
                    $target, $process, $errorElementId, $flowNode->id, $flowNode->get('elementType')
                );
            }
        }

        return $boundaryFlowNode;
    }

    public function stopProcess(BpmnProcess $process)
    {
        $GLOBALS['log']->debug("BPM: stopProcess, process {$process->id}");

        $this->rejectActiveFlows($process);

        $this->interruptSubProcesses($process);

        $process->set([
            'endedAt' => date('Y-m-d H:i:s')
        ]);

        if ($process->get('status') !== 'Stopped') {
            $process->set([
                'status' => 'Stopped',
                'modifiedById' => 'system',
            ]);
        }

        $this->getEntityManager()->saveEntity($process, ['skipModifiedBy' => true, 'skipStopProcess' => true]);
    }

    protected function rejectActiveFlows(BpmnProcess $process, $exclusionFlowNodeId = null)
    {
        $GLOBALS['log']->debug("BPM: rejectActiveFlows, process {$process->id}");

        $where = [
            'status!=' => ['Processed', 'Rejected', 'Failed', 'Interrupted'],
            'processId' => $process->id,
            'elementType!=' => 'eventSubProcess',
        ];

        if ($exclusionFlowNodeId) {
            $where['id!='] = $exclusionFlowNodeId;
        }

        $flowNodeList = $this->getEntityManager()->getRepository('BpmnFlowNode')->where($where)->find();

        foreach ($flowNodeList as $flowNode) {
            if ($flowNode->get('status') === 'In Process') {
                $flowNode->set('status', 'Interrupted');
            } else {
                $flowNode->set('status', 'Rejected');
            }

            $this->getEntityManager()->saveEntity($flowNode);
        }

        $target = $this->getEntityManager()->getEntity($process->get('targetType'), $process->get('targetId'));

        if ($target) {
            foreach ($flowNodeList as $flowNode) {
                if ($flowNode->get('status') === 'Interrupted') {
                    $impl = $this->getFlowNodeImplementation($target, $flowNode, $process);

                    $impl->cleanupInterrupted();
                }
            }
        }
    }
}
