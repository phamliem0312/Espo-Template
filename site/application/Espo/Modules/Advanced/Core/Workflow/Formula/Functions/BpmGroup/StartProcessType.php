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

namespace Espo\Modules\Advanced\Core\Workflow\Formula\Functions\BpmGroup;

use Espo\Core\Exceptions\Error;

class StartProcessType extends \Espo\Core\Formula\Functions\Base
{
    protected function init()
    {
        $this->addDependency('entityManager');
        $this->addDependency('container');
    }

    public function process(\StdClass $item)
    {
        $args = $this->fetchArguments($item);

        $flowchartId = $args[0] ?? null;
        $targetType = $args[1] ?? null;
        $targetId = $args[2] ?? null;
        $elementId = $args[3] ?? null;

        if (!$flowchartId || !$targetType || !$targetId)
            throw new Error("Formula: bpm\\startProcess: Too few arguments.");

        if (!is_string($flowchartId) || !is_string($targetType) || !is_string($targetId))
            throw new Error("Formula: bpm\\startProcess: Bad arguments.");

        $em = $this->getInjection('entityManager');

        $flowchart = $em->getEntity('BpmnFlowchart', $flowchartId);
        $target = $em->getEntity($targetType, $targetId);

        if (!$flowchart) {
            $GLOBALS['log']->notice("Formula: bpm\\startProcess: Flowchart '{$flowchartId}' not found.");
            return null;
        }

        if (!$target) {
            $GLOBALS['log']->notice("Formula: bpm\\startProcess: Target {$targetType} '{$targetId}' not found.");
            return null;
        }

        if ($flowchart->get('targetType') != $targetType)
            throw new Error("Formula: bpm\\startProcess: Target entity type doesn't match flowchart target type.");

        $bpmnManager = new \Espo\Modules\Advanced\Core\Bpmn\BpmnManager($this->getInjection('container'));

        $bpmnManager->startProcess($target, $flowchart, $elementId);

        return true;
    }
}