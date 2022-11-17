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

namespace Espo\Modules\Advanced\Hooks\Common;

use Espo\Modules\Advanced\Core\WorkflowManager;

class Workflow extends \Espo\Core\Hooks\Base
{
    public static $order = 99;

    protected function init()
    {
        $this->addDependency('workflowManager');
    }

    protected function getWorkflowManager()
    {
        return $this->getInjection('workflowManager');
    }

    public function afterSave(\Espo\ORM\Entity $entity, array $options = [])
    {
        $workflowManager = $this->getWorkflowManager();

        if (!empty($options['skipWorkflow'])) return;
        if (!empty($options['silent'])) return;

        if ($entity->isNew()) {
            $workflowManager->process($entity, WorkflowManager::AFTER_RECORD_CREATED, $options);
        } else {
            $workflowManager->process($entity, WorkflowManager::AFTER_RECORD_UPDATED, $options);
        }

        $workflowManager->process($entity, WorkflowManager::AFTER_RECORD_SAVED, $options);
    }
}
