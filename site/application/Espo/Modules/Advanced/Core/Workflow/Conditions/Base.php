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

namespace Espo\Modules\Advanced\Core\Workflow\Conditions;

use Espo\Core\Exceptions\Error;

use Espo\Core\Container;

use Espo\Modules\Advanced\Core\Workflow\Utils;

abstract class Base
{
    protected $container;

    private $workflowId;

    protected $entity;

    protected $condition;

    protected $createdEntitiesData = null;

    private $entityManager;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->entityManager = $container->get('entityManager');
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getConfig()
    {
        return $this->container->get('config');
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    public function setWorkflowId($workflowId)
    {
        $this->workflowId = $workflowId;
    }

    protected function getWorkflowId()
    {
        return $this->workflowId;
    }

    protected function getEntity()
    {
        return $this->entity;
    }

    protected function getCondition()
    {
        return $this->condition;
    }

    public function process($entity, $condition, $createdEntitiesData = null)
    {
        $this->entity = $entity;
        $this->condition = $condition;
        $this->createdEntitiesData = $createdEntitiesData;

        if (!empty($condition->fieldValueMap)) {
            return $this->compareComplex($entity, $condition);
        }
        else {
            $fieldName = $this->getFieldName();

            if (isset($fieldName)) {
                return $this->compare($this->getFieldValue());
            }
        }

        return false;
    }

    protected function compareComplex($entity, $condition)
    {
        return false;
    }

    abstract protected function compare($fieldValue);

    /**
     * Get field name based on fieldToCompare value
     *
     * @return string
     */
    protected function getFieldName()
    {
        $condition = $this->getCondition();

        if (isset($condition->fieldToCompare)) {
            $entity = $this->getEntity();
            $fieldName = $condition->fieldToCompare;

            $normalizeFieldName = Utils::normalizeFieldName($entity, $fieldName);
            if (is_array($normalizeFieldName)) { //if field is parent
                return reset($normalizeFieldName);
            }

            return $normalizeFieldName;
        }
    }

    protected function getAttributeName()
    {
        return $this->getFieldName();
    }

    protected function getAttributeValue()
    {
        return $this->getFieldValue();
    }

    /**
     * Get value of fieldToCompare field
     *
     * @return mixed
     */
    protected function getFieldValue()
    {
        $entity = $this->getEntity();
        $condition = $this->getCondition();

        $fieldValue = Utils::getFieldValue(
            $entity,
            $condition->fieldToCompare,
            false, $this->getEntityManager(),
            $this->createdEntitiesData
        );

        if (!is_array($fieldValue)) {
            return $fieldValue;
        }

        return $fieldValue;
    }

    /**
     * Get value of subject field
     *
     * @return mixed
     */
    protected function getSubjectValue()
    {
        $entity = $this->getEntity();
        $condition = $this->getCondition();

        switch ($condition->subjectType) {
            case 'value':
                $subjectValue = $condition->value;

                break;

            case 'field':
                $subjectValue = Utils::getFieldValue($entity, $condition->field);

                if (isset($condition->shiftDays)) {
                    $shiftUnits = isset($condition->shiftUnits) ? $condition->shiftUnits : 'days';

                    $timezone = $this->getConfig()->get('timeZone');

                    return Utils::shiftDays($condition->shiftDays, $subjectValue, 'date', $shiftUnits, $timezone);
                }

                break;

            case 'today':
                $shiftUnits = isset($condition->shiftUnits) ? $condition->shiftUnits : 'days';

                $timezone = $this->getConfig()->get('timeZone');

                return Utils::shiftDays($condition->shiftDays, null, 'date', $shiftUnits, $timezone);

            default:
                throw new Error(
                    'Workflow['.$this->getWorkflowId().']: Unknown object type [' . $condition->subjectType . '].'
                );
        }

        return $subjectValue;
    }
}