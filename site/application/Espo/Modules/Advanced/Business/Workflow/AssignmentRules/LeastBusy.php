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

class LeastBusy
{
    private $entityManager;

    private $selectManager;

    private $entityType;

    private $reportService;

    public function __construct(
        EntityManager $entityManager,
        $selectManager,
        $reportService,
        $entityType
    ) {
        $this->entityManager = $entityManager;
        $this->selectManager = $selectManager;
        $this->reportService = $reportService;
        $this->entityType = $entityType;
    }

    public function getAssignmentAttributes(
        Entity $entity,
        $targetTeamId,
        $targetUserPosition,
        $listReportId = null,
        $selectParams = null
    ) {
        $team = $this->entityManager->getEntity('Team', $targetTeamId);

        if (!$team) {
            throw new Error('LeastBusy: No team with id ' . $targetTeamId);
        }

        $userSelectParams = [
            'select' => ['id'],
            'orderBy' => 'userName',
        ];

        if (!empty($targetUserPosition)) {
            $userSelectParams['additionalColumnsConditions'] = [
                'role' => $targetUserPosition,
            ];
        }

        $userList = $this->entityManager
            ->getRepository('Team')
            ->findRelated($team, 'users', $userSelectParams);

        if (count($userList) == 0) {
            throw new Error('LeastBusy: No users found in team ' . $targetTeamId);
        }

        $userIdList = [];

        foreach ($userList as $user) {
            $userIdList[] = $user->id;
        }

        $counts = [];

        foreach ($userIdList as $id) {
            $counts[$id] = 0;
        }

        if ($listReportId) {
            $report = $this->entityManager->getEntity('Report', $listReportId);

            if (!$report) {
                throw new Error();
            }

            $this->reportService->checkReportIsPosibleToRun($report);

            $selectParams = $this->reportService->fetchSelectParamsFromListReport($report);
        }
        else {
            if (!$selectParams) {
                $selectParams = $this->selectManager->getEmptySelectParams();
            }
            else {
                $selectParamsNew = $this->selectManager->getEmptySelectParams();

                foreach ($selectParams as $k => $v) {
                    $selectParamsNew[$k] = $v;
                }

                $selectParams = $selectParamsNew;
            }
        }

        $selectParams['whereClause'][] = [
            'assignedUserId' => $userIdList,
            'id!=' => $entity->id,
        ];

        $selectParams['groupBy'] = ['assignedUserId'];
        $selectParams['select'] = ['assignedUserId', 'COUNT:id'];
        $selectParams['orderBy'] = [[1, false]];

        $this->selectManager->addJoin(['assignedUser', 'assignedUserAssignedRule'], $selectParams);

        $selectParams['whereClause'][] = ['assignedUserAssignedRule.isActive' => true];

        $sql = $this->entityManager
            ->getQuery()
            ->createSelectQuery($this->entityType, $selectParams);

        $pdo = $this->entityManager->getPDO();

        $sth = $pdo->prepare($sql);

        $sth->execute();

        $rowList = $sth->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rowList as $row) {
            $id = $row['assignedUserId'];

            if (!$id) {
                continue;
            }

            $counts[$id] = $row['COUNT:id'];
        }

        $minCount = null;

        $minCountIdList = [];

        foreach ($counts as $id => $count) {
            if (is_null($minCount) || $count <= $minCount) {
                $minCount = $count;
                $minCountIdList[] = $id;
            }
        }

        $attributes = [];

        if (count($minCountIdList)) {
            $attributes['assignedUserId'] = $minCountIdList[array_rand($minCountIdList)];

            if ($attributes['assignedUserId']) {
                $user = $this->entityManager->getEntity('User', $attributes['assignedUserId']);

                if ($user) {
                    $attributes['assignedUserName'] = $user->get('name');
                }
            }
        }

        return $attributes;
    }
}
