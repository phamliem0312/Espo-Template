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

namespace Espo\Modules\Advanced\Entities;

class Report extends \Espo\Core\ORM\Entity
{
    protected function _hasJoinedReportsIds()
    {
        return $this->has('joinedReportDataList');
    }

    protected function _hasJoinedReportsNames()
    {
        return $this->has('joinedReportDataList');
    }

    protected function _hasJoinedReportsColumns()
    {
        return $this->has('joinedReportDataList');
    }

    protected function _getJoinedReportsIds()
    {
        $idList = [];
        $dataList = $this->get('joinedReportDataList');
        if (!is_array($dataList)) return [];

        foreach ($dataList as $item) {
            if (empty($item->id)) continue;
            $idList[] = $item->id;
        }
        return $idList;
    }

    protected function _getJoinedReportsNames()
    {
        $nameMap = (object) [];
        $dataList = $this->get('joinedReportDataList');
        if (!is_array($dataList)) return $nameMap;

        foreach ($dataList as $item) {
            if (empty($item->id)) continue;
            $report = $this->entityManager->getEntity('Report', $item->id);
            if (!$report) continue;
            $nameMap->{$item->id} = $report->get('name');
        }

        return $nameMap;
    }

    protected function _getJoinedReportsColumns()
    {
        $map = (object) [];
        $dataList = $this->get('joinedReportDataList');
        if (!is_array($dataList)) return $map;

        foreach ($dataList as $item) {
            if (empty($item->id)) continue;
            if (!isset($item->label)) continue;
            $map->{$item->id} = (object) [
                'label' => $item->label
            ];
        }
        return $map;
    }
}
