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

namespace Espo\Modules\Advanced\Repositories;

use Espo\ORM\Entity;

class Workflow extends \Espo\Core\ORM\Repositories\RDB
{
    protected function afterSave(Entity $entity, array $options = [])
    {
        parent::afterSave($entity, $options);

        if ($entity->get('type') == 'scheduled' && $entity->isAttributeChanged('scheduling')) {
            $this->removePendingJobsForScheduledWorkflow($entity);
        }
    }

    protected function removePendingJobsForScheduledWorkflow(Entity $entity)
    {
        $pendingJobList = $this->getEntityManager()->getRepository('Job')->where([
            'methodName' => 'runScheduledWorkflow',
            'status' => 'Pending',
            'targetType' => 'Workflow',
            'targetId' => $entity->get('id'),
        ])->find();

        foreach ($pendingJobList as $pendingJob) {
            $this->getEntityManager()->removeEntity($pendingJob);
        }
    }
}
