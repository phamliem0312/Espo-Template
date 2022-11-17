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

namespace Espo\Modules\Advanced\Core\Workflow;

use Exception;
use StdClass;

class ActionManager extends BaseManager
{
    protected $dirName = 'Actions';

    protected $requiredOptions = [
        'type',
    ];

    public function runActions($actions)
    {
        if (!isset($actions)) {
            return true;
        }

        $GLOBALS['log']->debug('Workflow\ActionManager: Start workflow rule ID ['.$this->getWorkflowId().'].');

        $processId = $this->getProcessId();

        $variables = (object) [];

        foreach ($actions as $action) {
            $this->runAction($action, $processId, $variables);
        }

        $GLOBALS['log']->debug('Workflow\ActionManager: End workflow rule ID ['.$this->getWorkflowId().'].');

        return true;
    }

    protected function runAction($action, $processId, StdClass $variables)
    {
        $entity = $this->getEntity($processId);

        $entityType = $entity->getEntityType();

        if (!$this->validate($action)) {
            $GLOBALS['log']->warning(
                'Workflow['.$this->getWorkflowId($processId).']: Action data is broken for the Entity ['.$entityType.'].'
            );

            return false;
        }

        $actionImpl = $this->getClass($action->type, $processId);

        if (!isset($actionImpl)) {
            return;
        }

        try {
            $actionImpl->process($entity, $action, null, $variables);

            $this->copyVariables($actionImpl->getVariablesBack(), $variables);
        }
        catch (Exception $e) {
            $GLOBALS['log']->error(
                'Workflow[' . $this->getWorkflowId($processId) . ']: Action failed [' . $action->type . '] with cid [' .
                $action->cid . '], details: ' . $e->getMessage() . '.'
            );
        }
    }

    protected function copyVariables(object $source, object $destination)
    {
        foreach (get_object_vars($destination) as $k => $v) {
            unset($destination->$k);
        }

        foreach (get_object_vars($source) as $k => $v) {
            $destination->$k = $v;
        }
    }
}
