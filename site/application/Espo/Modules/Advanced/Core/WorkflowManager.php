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

namespace Espo\Modules\Advanced\Core;

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;

use Espo\ORM\Entity;

use Espo\Core\Container;

use Exception;

class WorkflowManager
{
    private $container;

    private $conditionManager;

    private $actionManager;

    private $data;

    protected $cacheFile = 'data/cache/advanced/workflows.php';

    protected $cacheFields = [
        'conditionsAll',
        'conditionsAny',
        'conditionsFormula',
        'actions',
    ];

    const AFTER_RECORD_SAVED = 'afterRecordSaved';
    const AFTER_RECORD_CREATED = 'afterRecordCreated';
    const AFTER_RECORD_UPDATED = 'afterRecordUpdated';

    protected $entityListToIgnore = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->conditionManager = new Workflow\ConditionManager($this->container);
        $this->actionManager = new Workflow\ActionManager($this->container);

        $this->entityListToIgnore = $this->container->get('metadata')->get('entityDefs.Workflow.entityListToIgnore');
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getConditionManager()
    {
        return $this->conditionManager;
    }

    protected function getActionManager()
    {
        return $this->actionManager;
    }

    protected function getConfig()
    {
        return $this->container->get('config');
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }

    protected function getFileManager()
    {
        return $this->container->get('fileManager');
    }

    protected function getData(string $entityType, string $trigger) : ?array
    {
        if (!isset($this->data)) {
            $this->loadWorkflows();
        }

        if (!isset($this->data[$trigger])) {
            return null;
        }

        if (!isset($this->data[$trigger][$entityType])) {
            return null;
        }

        $result = $this->data[$trigger][$entityType];

        if ($result && !is_array($result)) {
            $GLOBALS['log']->error("WorkflowManager: Bad data for workflow [{$workflowId}].");

            return null;
        }

        return $result;
    }

    public function process(Entity $entity, string $trigger, array $options = [])
    {
        $entityType = $entity->getEntityType();

        if (in_array($entityType, $this->entityListToIgnore)) return;

        $data = $this->getData($entityType, $trigger);

        if (!$data) {
            return;
        }

        $GLOBALS['log']->debug('WorkflowManager: Start workflow ['.$trigger.'] for entity ['.$entityType.', '.$entity->id.'].');

        $conditionManager = $this->getConditionManager();
        $actionManager = $this->getActionManager();

        foreach ($data as $workflowId => $workflowData) {
            $GLOBALS['log']->debug('WorkflowManager: Start workflow rule ['.$workflowId.'].');

            if ($workflowData['portalOnly']) {
                if (!$this->getUser()->get('portalId')) {
                    continue;
                }

                if (!empty($workflowData['portalId'])) {
                    if ($this->getUser()->get('portalId') !== $workflowData['portalId']) {
                        continue;
                    }
                }
            }

            if (!empty($options['workflowId']) && $options['workflowId'] === $workflowId) {
                continue;
            }

            $conditionManager->setInitData($workflowId, $entity);

            $result = true;

            if (isset($workflowData['conditionsAll'])) {
                $result &= $conditionManager->checkConditionsAll($workflowData['conditionsAll']);
            }

            if (isset($workflowData['conditionsAny'])) {
                $result &= $conditionManager->checkConditionsAny($workflowData['conditionsAny']);
            }

            if (isset($workflowData['conditionsFormula']) && !empty($workflowData['conditionsFormula'])) {
                $result &= $conditionManager->checkConditionsFormula($workflowData['conditionsFormula']);
            }

            $GLOBALS['log']->debug(
                'WorkflowManager: Condition result ['.(bool) $result.'] for workflow rule ['.$workflowId.'].'
            );

            if ($result) {
                $workflowLogRecord = $this->getEntityManager()->getEntity('WorkflowLogRecord');

                $workflowLogRecord->set([
                    'workflowId' => $workflowId,
                    'targetId' => $entity->id,
                    'targetType' => $entity->getEntityType(),
                ]);

                $this->getEntityManager()->saveEntity($workflowLogRecord);
            }

            if ($result && isset($workflowData['actions'])) {
                $GLOBALS['log']->debug('WorkflowManager: Start running Actions for workflow rule ['.$workflowId.'].');

                $actionManager->setInitData($workflowId, $entity);

                try {
                    $actionResult = $actionManager->runActions($workflowData['actions']);
                }
                catch (Exception $e) {
                    $GLOBALS['log']->notice(
                        'Workflow: failed action execution for workflow [' . $workflowId . ']. Details: '. $e->getMessage()
                    );
                }

                $GLOBALS['log']->debug('WorkflowManager: End running Actions for workflow rule ['.$workflowId.'].');
            }

            $GLOBALS['log']->debug('WorkflowManager: End workflow rule ['.$workflowId.'].');
        }

        $GLOBALS['log']->debug('WorkflowManager: End workflow ['.$trigger.'] for Entity ['.$entityType.', '.$entity->id.'].');
    }

    public function checkConditions($workflow, $entity)
    {
        $result = true;

        $conditionsAll = $workflow->get('conditionsAll');
        $conditionsAny = $workflow->get('conditionsAny');

        $conditionsFormula = $workflow->get('conditionsFormula');

        $conditionManager = $this->getConditionManager();
        $conditionManager->setInitData($workflow->id, $entity);

        if (isset($conditionsAll)) {
            $result &= $conditionManager->checkConditionsAll($conditionsAll);
        }
        if (isset($conditionsAny)) {
            $result &= $conditionManager->checkConditionsAny($conditionsAny);
        }

        if ($conditionsFormula && $conditionsFormula !== '') {
            $result &= $conditionManager->checkConditionsFormula($conditionsFormula);
        }

        return $result;
    }

    public function runActions($workflow, $entity)
    {
        $actions = $workflow->get('actions');

        $actionManager = $this->getActionManager();
        $actionManager->setInitData($workflow->id, $entity);

        $actionManager->runActions($actions);
    }

    public function loadWorkflows($reload = false)
    {
        if (!$reload && $this->getConfig()->get('useCache') && file_exists($this->cacheFile)) {
            $this->data = $this->getFileManager()->getPhpContents($this->cacheFile);

            return;
        }

        $this->data = $this->getWorkflowData();

        if ($this->getConfig()->get('useCache')) {
            $this->getFileManager()->putPhpContents($this->cacheFile, $this->data, true);
        }
    }

    /**
     * Get all workflows from database and save into cache.
     *
     * @return array
     */
    protected function getWorkflowData()
    {
        $data = [];

        $em = $this->getContainer()->get('entityManager');

        $workflowList = $em
            ->getRepository('Workflow')
            ->where(['isActive' => true])
            ->find();

        foreach ($workflowList as $workflow) {
            $rowData = [];

            foreach ($this->cacheFields as $fieldName) {

                if ($workflow->get($fieldName) === null) {
                    continue;
                }

                $fieldValue = $workflow->get($fieldName);

                if (!empty($fieldValue)) {
                    $rowData[$fieldName] = $fieldValue;
                }
            }

            $rowData['portalOnly'] = (bool) $workflow->get('portalOnly');

            if ($rowData['portalOnly']) {
                $rowData['portalId'] = $workflow->get('portalId');
            }

            $entityType = $workflow->get('entityType');

            $id = $workflow->get('id');

            if ($workflow->get('type') === 'signal') {
                $trigger = '$' . $workflow->get('signalName');
            }
            else {
                $trigger = $workflow->get('type');
            }

            $data[$trigger][$entityType][$id] = $rowData;
        }

        return $data;
    }
}
