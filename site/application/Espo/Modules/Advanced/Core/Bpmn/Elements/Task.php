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

use Throwable;
use StdClass;

class Task extends Activity
{
    protected $localVariableList = [
        '_lastHttpResponseBody',
    ];

    public function process()
    {
        try {
            $actionList = $this->getAttributeValue('actionList');

            if (!is_array($actionList)) {
                $actionList = [];
            }

            $localVariables = (object) [];

            foreach ($actionList as $item) {
                if (empty($item->type)) {
                    continue;
                }

                $actionImpl = $this->getActionImplementation($item->type);

                $item = clone $item;
                $item->elementId = $this->getFlowNode()->get('elementId');
                $actionData = $item;
                $originalVariables = $this->getVariablesForFormula();

                $this->copyLocalVariables($localVariables, $originalVariables);

                $target = $this->getTarget();

                $actionImpl->process(
                    $target,
                    $actionData,
                    $this->getCreatedEntitiesData(),
                    $originalVariables,
                    $this->getProcess()
                );

                $variables = $actionImpl->getVariablesBack();

                // To preserve variables needed to be available within a task
                // but not needed to be stored in a process.
                $this->copyLocalVariables($variables, $localVariables);

                foreach ($this->localVariableList as $var) {
                    unset($variables->$var);
                }

                if ($actionImpl->isCreatedEntitiesDataChanged() || $variables != $originalVariables) {
                    $this->sanitizeVariables($variables);

                    $this->getProcess()->set('createdEntitiesData', $actionImpl->getCreatedEntitiesData());
                    $this->getProcess()->set('variables', $variables);

                    $this->getEntityManager()->saveEntity($this->getProcess(), ['silent' => true]);
                }
            }
        } catch (Throwable $e) {
            $GLOBALS['log']->error(
                'Process ' . $this->getProcess()->id . ' element ' .
                $this->getFlowNode()->get('elementId') . ': ' . $e->getMessage()
            );

            $this->setFailedWithCode((string) $e->getCode());

            return;
        }

        $this->processNextElement();
    }

    protected function copyLocalVariables(StdClass $source, StdClass $destination)
    {
        foreach ($this->localVariableList as $key) {
            if (!property_exists($source, $key)) {
                continue;
            }

            $destination->$key = $source->$key;
        }
    }

    protected function getActionImplementation($name)
    {
        $name = ucfirst($name);
        $name = str_replace("\\", "", $name);

        $className = 'Espo\\Custom\\Modules\\Advanced\\Core\\Workflow\\Actions\\' . $name;

        if (!class_exists($className)) {
            $className .= 'Type';

            if (!class_exists($className)) {
                $className = 'Espo\\Modules\\Advanced\\Core\\Workflow\\Actions\\' . $name;

                if (!class_exists($className)) {
                    $className .= 'Type';

                    if (!class_exists($className)) {
                        throw new Error('Action class ' . $className . ' does not exist.');
                    }
                }
            }
        }

        $impl = new $className($this->getContainer());

        $workflowId = $this->getProcess()->get('workflowId');

        if ($workflowId) {
            $impl->setWorkflowId($workflowId);
        }

        return $impl;
    }
}
