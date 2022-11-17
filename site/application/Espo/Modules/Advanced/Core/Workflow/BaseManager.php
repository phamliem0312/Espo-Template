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

use Espo\Core\Exceptions\Error;

use Espo\Core\Container;
use Espo\ORM\Entity;

abstract class BaseManager
{
    protected $dirName;

    private $container;

    private $processId;

    private $entityList;

    private $workflowIdList;

    private $actionClassNameMap = [];

    protected $requiredOptions = [];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    public function setInitData($workflowId, Entity $entity)
    {
        $this->processId = $workflowId . '-'. $entity->id;

        $this->workflowIdList[$this->processId] = $workflowId;
        $this->entityList[$this->processId] = $entity;
    }

    protected function getProcessId()
    {
        if (empty($this->processId)) {
            throw new Error('Workflow['.__CLASS__.'], getProcessId(): Empty processId.');
        }

        return $this->processId;
    }

    protected function getWorkflowId($processId = null)
    {
        if (!isset($processId)) {
            $processId = $this->getProcessId();
        }

        if (empty($this->workflowIdList[$processId])) {
            throw new Error('Workflow['.__CLASS__.'], getWorkflowId(): Empty workflowId.');
        }

        return $this->workflowIdList[$processId];
    }

    protected function getEntity($processId = null)
    {
        if (!isset($processId)) {
            $processId = $this->getProcessId();
        }

        if (empty($this->entityList[$processId])) {
            throw new Error('Workflow['.__CLASS__.'], getEntity(): Empty Entity object.');
        }

        return $this->entityList[$processId];
    }

    private function getClassName(string $name): string
    {
        if (!isset($this->actionClassNameMap[$name])) {
            $className = 'Espo\Custom\Modules\Advanced\Core\Workflow\\' . ucfirst($this->dirName) . '\\' . $name;

            if (!class_exists($className)) {
                $className .=  'Type';

                if (!class_exists($className)) {
                    $className = 'Espo\Modules\Advanced\Core\Workflow\\' . ucfirst($this->dirName) . '\\' . $name;

                    if (!class_exists($className)) {
                        $className .=  'Type';

                        if (!class_exists($className)) {
                            throw new Error('Class ['.$className.'] does not exist.');
                        }
                    }
                }
            }

            $this->actionClassNameMap[$name] = $className;
        }

        return $this->actionClassNameMap[$name];
    }

    protected function getClass($name, $processId = null)
    {
        $name = ucfirst($name);

        $name = str_replace("\\", "", $name);

        if (!isset($processId)) {
            $processId = $this->getProcessId();
        }

        $workflowId = $this->getWorkflowId($processId);

        $className = $this->getClassName($name);

        $obj = new $className($this->getContainer());

        $obj->setWorkflowId($workflowId);

        return $obj;
    }

    protected function validate($options)
    {
        foreach ($this->requiredOptions as $optionName) {
            if (!property_exists($options, $optionName)) {
                return false;
            }
        }

        return true;
    }
}
