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

namespace Espo\Modules\Advanced\Core\Bpmn\Utils;

use Espo\Core\Exceptions\Error;
use Espo\Core\Container;

use Espo\ORM\Entity;

use StdClass;

class ConditionManager
{
    protected $createdEntitiesData = null;

    protected $requiredOptionList = [
        'comparison',
        'fieldToCompare',
    ];

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getFormulaManager()
    {
        return $this->getContainer()->get('formulaManager');
    }

    public function check(
        Entity $entity,
        $conditionsAll = null,
        $conditionsAny = null,
        $conditionsFormula = null,
        $variables = null
    ): bool {

        $result = true;

        if (!is_null($conditionsAll)) {
            $result &= $this->checkConditionsAll($entity, $conditionsAll);
        }

        if (!is_null($conditionsAny)) {
            $result &= $this->checkConditionsAny($entity, $conditionsAny);
        }

        if (!is_null($conditionsFormula) && !empty($conditionsFormula)) {
            $result &= $this->checkConditionsFormula($entity, $conditionsFormula, $variables);
        }

        return (bool) $result;
    }

    public function checkConditionsAll(Entity $entity, array $conditionList)
    {
        if (!isset($conditionList)) {
            return true;
        }

        foreach ($conditionList as $condition) {
            if (!$this->processCheck($entity, $condition)) {
                return false;
            }
        }

        return true;
    }

    public function checkConditionsAny(Entity $entity, array $conditionList)
    {
        if (!isset($conditionList) || empty($conditionList)) {
            return true;
        }

        foreach ($conditionList as $condition) {
            if ($this->processCheck($entity, $condition)) {
                return true;
            }
        }

        return false;
    }

    public function checkConditionsFormula(Entity $entity, $formula, $variables = null)
    {
        if (!isset($formula) || empty($formula)) {
            return true;
        }

        $o = (object) [];

        $o->__targetEntity = $entity;

        if ($variables) {
            foreach (get_object_vars($variables) as $name => $value) {
                $o->$name = $value;
            }
        }

        if ($this->createdEntitiesData) {
            $o->__createdEntitiesData = $this->createdEntitiesData;
        }

        return $this->getFormulaManager()->run($formula, $entity, $o);
    }

    protected function processCheck(Entity $entity, \StdClass $condition)
    {
        if (!$this->validate($condition)) {
            return false;
        }

        $compareImpl = $this->getConditionImplementation($condition->comparison);

        if (isset($compareImpl)) {
            return $compareImpl->process($entity, $condition, $this->createdEntitiesData);
        }

        return false;
    }

    protected function getConditionImplementation($name)
    {
        $name = ucfirst($name);
        $name = str_replace("\\", "", $name);

        $className = 'Espo\\Custom\\Modules\\Advanced\\Core\\Workflow\\Conditions\\' . $name;

        if (!class_exists($className)) {
            $className .= 'Type';

            if (!class_exists($className)) {
                $className = 'Espo\\Modules\\Advanced\\Core\\Workflow\\Conditions\\' . $name;

                if (!class_exists($className)) {
                    $className .= 'Type';

                    if (!class_exists($className)) {
                        throw new Error('ConditionManager: Class ' . $className . ' does not exist.');
                    }
                }
            }
        }

        $impl = new $className($this->getContainer());

        return $impl;
    }

    public function setCreatedEntitiesData(StdClass $createdEntitiesData)
    {
        $this->createdEntitiesData = $createdEntitiesData;
    }

    protected function validate($options)
    {
        foreach ($this->requiredOptionList as $optionName) {
            if (!property_exists($options, $optionName)) {
                return false;
            }
        }

        return true;
    }
}
