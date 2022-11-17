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

namespace Espo\Modules\Advanced\Jobs;

use Cron\CronExpression;

use Exception;
use DateTime;

class RunScheduledWorkflows extends \Espo\Core\Jobs\Base
{
    protected $serviceMethodName = 'runScheduledWorkflow';

    public function run()
    {
        $entityManager = $this->getEntityManager();

        $collection = $entityManager
            ->getRepository('Workflow')
            ->where([
                'type' => 'scheduled',
                'isActive' => true
            ])
            ->find();

        foreach ($collection as $entity) {
            $cronExpression = CronExpression::factory($entity->get('scheduling'));

            try {
                $executionTime = $cronExpression
                    ->getNextRunDate('now', 0, true)
                    ->format('Y-m-d H:i:s');
            }
            catch (Exception $e) {
                $GLOBALS['log']->error(
                    'RunScheduledWorkflows: Workflow ['.$entity->get('id').']: '.
                    'Impossible scheduling expression ['.$entity->get('scheduling').'].');

                continue;
            }

            if ($entity->get('lastRun') == $executionTime) {
                continue;
            }

            $jobData = [
                'workflowId' => $entity->id,
            ];

            if (!$this->isJobExisting($executionTime, $entity->id)) {
                if ($this->createJob($jobData, $executionTime, $entity->id)) {
                    $entity->set('lastRun', $executionTime);

                    $entityManager->saveEntity($entity, ['silent' => true]);
                }
            }
        }
    }

    protected function createJob(array $jobData, $executionTime, $workflowId)
    {
        $job = $this->getEntityManager()->getEntity('Job');

        $job->set([
            'serviceName' => 'Workflow',
            'method' => $this->serviceMethodName,
            'methodName' => $this->serviceMethodName,
            'data' => $jobData,
            'executeTime' => $executionTime,
            'targetId' => $workflowId,
            'targetType' => 'Workflow',
            'queue' => 'q1'
        ]);

        if ($this->getEntityManager()->saveEntity($job)) {
            return true;
        }

        return false;
    }

    protected function isJobExisting($time, $workflowId)
    {
        $timeWithoutSeconds = (new DateTime($time))->format('Y-m-d H:i:');

        $found = $this->getEntityManager()
            ->getRepository('Job')
            ->select(['id'])
            ->where([
                'methodName' => $this->serviceMethodName,
                'executeTime*' => $timeWithoutSeconds . '%',
                'targetId' => $workflowId,
                'targetType' => 'Workflow',
            ])
            ->findOne();

        if ($found) {
            return true;
        }

        return false;
    }
}
