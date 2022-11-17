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

namespace Espo\Modules\Advanced\Business\Workflow\AssignmentRules;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

use Espo\Core\Exceptions\Error;

use Espo\Modules\Advanced\Entities\WorkflowRoundRobin;

class RoundRobin
{
    private $entityManager;

    private $selectManager;

    private $entityType;

    private $reportService;

    private $actionId;

    private $workflowId;

    private $flowchartId;

    public function __construct(
        EntityManager $entityManager,
        $selectManager,
        $reportService,
        string $entityType,
        string $actionId,
        ?string $workflowId,
        ?string $flowchartId
    ) {
        $this->entityManager = $entityManager;
        $this->selectManager = $selectManager;
        $this->reportService = $reportService;
        $this->entityType = $entityType;
        $this->actionId = $actionId;
        $this->workflowId = $workflowId;
        $this->flowchartId = $flowchartId;
    }

    public function getAssignmentAttributes(
        Entity $entity,
        $targetTeamId,
        $targetUserPosition
    ) {
        $team = $this->entityManager->getEntity('Team', $targetTeamId);

        if (!$team) {
            throw new Error('RoundRobin: No team with id ' . $targetTeamId);
        }

        $params = [
            'select' => ['id'],
            'orderBy' => [['id', 'ASC']],
        ];

        if (!empty($targetUserPosition)) {
            $params['additionalColumnsConditions'] = [
                'role' => $targetUserPosition,
            ];
        }

        $userList = $team->get('users', $params);

        $this->entityManager
            ->getRepository('Team')
            ->findRelated($team, 'users', $params);

        if (count($userList) == 0) {
            throw new Error('RoundRobin: No users found in team ' . $targetTeamId);
        }

        $userIdList = [];

        foreach ($userList as $user) {
            $userIdList[] = $user->id;
        }

        // @todo lock table ?

        $lastUserId = $this->getLastUserId();

        if ($lastUserId === null) {
            $num = 0;
        }
        else {
            $num = array_search($lastUserId, $userIdList);

            if ($num === false || $num == count($userIdList) - 1) {
                $num = 0;
            }
            else {
                $num++;
            }
        }

        $userId = $userIdList[$num];

        $this->storeLastUserId($userId);

        $attributes = [
            'assignedUserId' => $userId,
        ];

        if ($attributes['assignedUserId']) {
            $user = $this->entityManager->getEntity('User', $attributes['assignedUserId']);

            if ($user) {
                $attributes['assignedUserName'] = $user->get('name');
            }
        }

        return $attributes;
    }

    private function getLastUserId(): ?string
    {
        $item = $this->entityManager
            ->getRepository(WorkflowRoundRobin::ENTITY_TYPE)
            ->select(['lastUserId'])
            ->where([
                'actionId' => $this->actionId,
            ])
            ->findOne();

        if (!$item) {
            return null;
        }

        return $item->get('lastUserId');
    }

    private function storeLastUserId(string $userId): void
    {
        $item = $this->entityManager
            ->getRepository(WorkflowRoundRobin::ENTITY_TYPE)
            ->select(['id', 'lastUserId'])
            ->where([
                'actionId' => $this->actionId,
                'workflowId' => $this->workflowId,
                'flowchartId' => $this->flowchartId,
            ])
            ->findOne();

        if (!$item) {
            $this->entityManager->createEntity(WorkflowRoundRobin::ENTITY_TYPE, [
                'actionId' => $this->actionId,
                'lastUserId' => $userId,
                'entityType' => $this->entityType,
                'workflowId' => $this->workflowId,
                'flowchartId' => $this->flowchartId,
            ]);

            return;
        }

        $item->set('lastUserId', $userId);

        $this->entityManager->saveEntity($item);
    }
}
