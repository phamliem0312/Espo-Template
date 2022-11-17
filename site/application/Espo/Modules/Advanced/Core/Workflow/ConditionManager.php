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

class ConditionManager extends BaseManager
{
    protected $dirName = 'Conditions';

    protected $requiredOptions = [
        'comparison',
        'fieldToCompare',
    ];

    protected function getFormulaManager()
    {
        return $this->getContainer()->get('formulaManager');
    }

    public function check($conditionsAll = null, $conditionsAny = null, $conditionsFormula = null)
    {
        $result = true;

        if (!is_null($conditionsAll)) {
            $result &= $this->checkConditionsAll($conditionsAll);
        }

        if (!is_null($conditionsAny)) {
            $result &= $this->checkConditionsAny($conditionsAny);
        }

        if (!is_null($conditionsFormula) && !empty($conditionsFormula)) {
            $result &= $this->checkConditionsFormula($conditionsFormula);
        }

        return $result;
    }

    public function checkConditionsAny(array $conditions)
    {
        if (!isset($conditions) || empty($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if ($this->processCheck($condition)) {
                return true;
            }
        }

        return false;
    }

    public function checkConditionsAll(array $conditions)
    {
        if (!isset($conditions)) {
            return true;
        }

        foreach ($conditions as $condition) {
            if (!$this->processCheck($condition)) {
                return false;
            }
        }

        return true;
    }

    public function checkConditionsFormula($formula)
    {
        if (!isset($formula) || empty($formula)) {
            return true;
        }

        $formula = trim($formula, " \t\n\r");

        if (empty($formula)) {
            return true;
        }

        if (substr($formula, -1) === ';') {
            $formula = substr($formula, 0, -1);
        }

        if (empty($formula)) {
            return true;
        }

        $o = (object) [];

        $o->__targetEntity = $this->getEntity();

        return $this->getFormulaManager()->run($formula, $this->getEntity(), $o);
    }

    protected function processCheck($condition)
    {
        $entity = $this->getEntity();

        $entityType = $entity->getEntityType();

        if (!$this->validate($condition)) {
            $GLOBALS['log']->warning(
                'Workflow['.$this->getWorkflowId().']: Condition data is broken for the Entity ['.$entityType.'].'
            );

            return false;
        }

        $compareClass = $this->getClass($condition->comparison);

        if (isset($compareClass)) {
            return $compareClass->process($entity, $condition);
        }

        return false;
    }
}
