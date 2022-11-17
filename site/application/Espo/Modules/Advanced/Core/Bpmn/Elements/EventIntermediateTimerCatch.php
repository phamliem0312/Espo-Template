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

use \Espo\Core\Exceptions\Error;

class EventIntermediateTimerCatch extends Event
{
    protected $pendingStatus = 'Pending';

    protected function getFormulaManager()
    {
        return $this->getContainer()->get('formulaManager');
    }

    public function process()
    {
        $timerBase = $this->getAttributeValue('timerBase');

        if (!$timerBase || $timerBase === 'moment') {
            $dt = new \DateTime();
            $this->shiftDateTime($dt);

        } else if ($timerBase === 'formula') {
            $timerFormula = $this->getAttributeValue('timerFormula');
            $formulaManager = $this->getFormulaManager();
            if (!$timerFormula) {
                $this->setFailed();
                throw new Error('Bpmn Flow: EventIntermediateTimer error.');
            }
            $value = $formulaManager->run($timerFormula, $this->getTarget(), $this->getVariablesForFormula());
            if (!$value || !is_string($value)) {
                $this->setFailed();
                throw new Error();
            }
            try {
                $dt = new \DateTime($value);
            } catch (\Exception $e) {
                $this->setFailed();
                throw new Error('Bpmn Flow: EventIntermediateTimer error.');
            }

        } else if (strpos($timerBase, 'field:') === 0) {
            $field = substr($timerBase, 6);
            $entity = $this->getTarget();
            if (strpos($field, '.') > 0) {
                list($link, $field) = explode('.', $field);
                $entity = $this->getTarget()->get($link);
                if (!$entity) {
                    $this->setFailed();
                    throw new Error("Bpmn Flow: EventIntermediateTimer. Related entity doesn't exist.");
                }
            }
            $value = $entity->get($field);
            if (!$value || !is_string($value)) {
                $this->setFailed();
                throw new Error('Bpmn Flow: EventIntermediateTimer.');
            }
            try {
                $dt = new \DateTime($value);
            } catch (\Exception $e) {
                $this->setFailed();
                throw new Error('Bpmn Flow: EventIntermediateTimer error.');
            }

            $this->shiftDateTime($dt);

        } else {
            $this->setFailed();
            throw new Error('Bpmn Flow: EventIntermediateTimer error.');
        }

        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => $this->pendingStatus,
            'proceedAt' => $dt->format('Y-m-d H:i:s')
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
    }

    protected function shiftDateTime(\DateTime $dt)
    {
        $timerShiftOperator = $this->getAttributeValue('timerShiftOperator');
        $timerShift = $this->getAttributeValue('timerShift');
        $timerShiftUnits = $this->getAttributeValue('timerShiftUnits');

        if (!in_array($timerShiftUnits, ['minutes', 'hours', 'days', 'months', 'seconds'])) {
            $flowNode = $this->getFlowNode();
            $this->setFailed();
            throw new Error("Bpmn Flow: Bad shift in ". $flowNode->get('elementType') . " " . $flowNode->get('elementId') . " in flowchart " . $flowNode->get('flowchartId') . ".");
        }

        if ($timerShift) {
            $modifyString = strval($timerShift) . ' ' . $timerShiftUnits;
            if ($timerShiftOperator === 'minus') {
                $modifyString = '-' . $modifyString;
            }
            try {
                $dt->modify($modifyString);
            } catch (\Exception $e) {
                $this->setFailed();
                throw new Error('Bpmn Flow: EventIntermediateTimer error.');
            }
        }
    }

    public function proceedPending()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set('status', 'In Process');
        $this->getEntityManager()->saveEntity($flowNode);

        $this->rejectConcurrentPendingFlows();
        $this->processNextElement();
    }
}
