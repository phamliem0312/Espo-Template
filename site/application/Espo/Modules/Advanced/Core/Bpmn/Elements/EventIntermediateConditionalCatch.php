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

use Espo\Modules\Advanced\Core\Bpmn\Utils\ConditionManager;

class EventIntermediateConditionalCatch extends Event
{
    protected $pendingStatus = 'Pending';

    public function process()
    {
        $target = $this->getConditionsTarget();

        if (!$target) {
            $this->fail();

            return;
        }

        $result = $this->getConditionManager()->check(
            $target,
            $this->getAttributeValue('conditionsAll'),
            $this->getAttributeValue('conditionsAny'),
            $this->getAttributeValue('conditionsFormula'),
            $this->getVariablesForFormula()
        );

        if ($result) {
            $this->rejectConcurrentPendingFlows();
            $this->processNextElement();

            return;
        }

        $flowNode = $this->getFlowNode();

        $flowNode->set([
            'status' => $this->pendingStatus,
        ]);

        $this->getEntityManager()->saveEntity($flowNode);
    }

    public function proceedPending()
    {
        $target = $this->getConditionsTarget();

        if (!$target) {
            $this->fail();

            return;
        }

        $result = $this->getConditionManager()->check(
            $target,
            $this->getAttributeValue('conditionsAll'),
            $this->getAttributeValue('conditionsAny'),
            $this->getAttributeValue('conditionsFormula'),
            $this->getVariablesForFormula()
        );

        if ($result) {
            $this->rejectConcurrentPendingFlows();
            $this->processNextElement();
        }
    }

    protected function getConditionsTarget()
    {
        return $this->getTarget();
    }

    protected function getConditionManager()
    {
        $conditionManager = new ConditionManager($this->getContainer());

        $conditionManager->setCreatedEntitiesData($this->getCreatedEntitiesData());

        return $conditionManager;
    }
}
