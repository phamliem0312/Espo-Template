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

namespace Espo\Modules\Advanced\SelectManagers;

class Report extends \Espo\Core\SelectManagers\Base
{
    protected function filterListTargets(&$result)
    {
        $result['whereClause'][] = [
            'type=' => 'List',
            'entityType' => ['Contact', 'Lead', 'User', 'Account']
        ];
    }

    protected function filterListAccounts(&$result)
    {
        $result['whereClause'][] = [
            'type=' => 'List',
            'entityType' => 'Account'
        ];
    }

    protected function filterListContacts(&$result)
    {
        $result['whereClause'][] = [
            'type=' => 'List',
            'entityType' => 'Contact'
        ];
    }

    protected function filterListLeads(&$result)
    {
        $result['whereClause'][] = [
            'type=' => 'List',
            'entityType' => 'Lead'
        ];
    }

    protected function filterListUsers(&$result)
    {
        $result['whereClause'][] = [
            'type=' => 'List',
            'entityType' => 'User'
        ];
    }

    protected function filterList(&$result)
    {
        $result['whereClause'][] = [
            'type=' => 'List'
        ];
    }

    protected function filterGrid(&$result)
    {
        $result['whereClause'][] = [
            'type=' => 'Grid'
        ];
    }

    protected function access(&$result)
    {
        parent::access($result);

        if (!$this->getUser()->isAdmin() && !$this->checkIsPortal()) {
            $forbiddenEntityTypeList = [];
            $scopes = $this->getMetadata()->get('scopes', []);
            foreach ($scopes as $scope => $d) {
                if (empty($d['entity']) || !$d['entity']) continue;
                if (!$this->getAcl()->checkScope($scope, 'read')) {
                    $forbiddenEntityTypeList[] = $scope;
                }
            }
            if (!empty($forbiddenEntityTypeList)) {
                $result['whereClause'][] = [
                    'entityType!=' => $forbiddenEntityTypeList
                ];
            }
        }

        if ($this->checkIsPortal()) {
            $this->setDistinct(true, $result);
            $this->addLeftJoin(['portals', 'portalsAccess'], $result);
            $this->addOrWhere([
                ['portalsAccess.id' => $this->getUser()->get('portalId')]
            ], $result);
        }
    }
 }
