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

namespace Espo\Modules\Advanced\Core;

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\Forbidden;

class ReportFilter extends \Espo\Core\Injectable
{
    protected $dependencyList = [
        'serviceFactory',
        'metadata',
        'entityManager',
        'user'
    ];

    protected function getEntityManager()
    {
        return $this->getInjection('entityManager');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getUser()
    {
        return $this->getInjection('user');
    }


    public function applyFilter($entityType, $filterName, &$result, $selectManger)
    {
        $reportFilterId = $this->getMetadata()->get(['entityDefs', $entityType, 'collection', 'filters', $filterName, 'id']);

        if (!$reportFilterId) {
            throw new Error('Report Filter error.');
        }

        $reportFilter = $this->getEntityManager()->getEntity('ReportFilter', $reportFilterId);
        if (!$reportFilter) {
            throw new Error('Report Filter not found.');
        }

        $teamIdList = $reportFilter->getLinkMultipleIdList('teams');
        if (count($teamIdList) && !$this->getUser()->isAdmin()) {
            $isInTeam = false;
            $userTeamIdList = $this->getUser()->getLinkMultipleIdList('teams');
            foreach ($userTeamIdList as $teamId) {
                if (in_array($teamId, $teamIdList)) {
                    $isInTeam = true;
                    break;
                }
            }
            if (!$isInTeam) {
                throw new Forbidden("Access denied to Report Filter.");
            }
        }

        $reportId = $reportFilter->get('reportId');
        if (!$reportId) {
            throw new Error('Report Filter error.');
        }

        $report = $this->getEntityManager()->getEntity('Report', $reportId);
        if (!$report) {
            throw new Error('Report Filter error. Report not found.');
        }

        $reportService = $this->getServiceFactory()->create('Report');

        $selectParams = $reportService->fetchSelectParamsFromListReport($report, $this->getUser());

        $result['whereClause'][] = $selectParams['whereClause'];

        if (!empty($selectParams['customJoin'])) {
            $result['customJoin'] = $result['customJoin'] ?? '';
            if ($result['customJoin']) {
                $result['customJoin'] .= ' ';
            }
            $result['customJoin'] .= $selectParams['customJoin'];
        }

        if (!empty($selectParams['customWhere'])) {
            $result['customWhere'] = $result['customWhere'] ?? '';
            if ($result['customWhere']) {
                $result['customWhere'] .= ' ';
            }
            $result['customWhere'] .= $selectParams['customWhere'];
        }

        if (!empty($selectParams['customHaving'])) {
            if (!empty($result['customHaving'])) {
                $result['customHaving'] .= ' ';
            } else {
                $result['customHaving'] = '';
            }
            $result['customHaving'] .= $selectParams['customHaving'];
        }

        foreach ($selectParams['joins'] as $join) {
            $selectManger->addJoin($join, $result);
        }

        foreach ($selectParams['leftJoins'] as $join) {
            $selectManger->addLeftJoin($join, $result);
        }

        foreach ($selectParams['additionalSelectColumns'] as $column) {
            $result['additionalSelectColumns'][] = $column;
        }

        if (!empty($selectParams['distinct'])) {
            $selectManger->setDistinct(true, $result);
        }

        if (isset($selectParams['havingClause'])) {
            $result['havingClause'] = $selectParams['havingClause'];
        }

        if (isset($selectParams['groupBy'])) {
            $result['groupBy'] = $selectParams['groupBy'];
        }

        foreach ($selectParams['joinConditions'] as $join => $condition) {
            $selectManger->setJoinCondition($join, $condition, $result);
        }
    }
}
