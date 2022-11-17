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

class Report extends \Espo\Core\ORM\Repositories\RDB
{
    protected function beforeSave(Entity $entity, array $options = [])
    {
        if (
            $entity->isAttributeChanged('emailSendingInterval')
            ||
            $entity->isAttributeChanged('emailSendingTime')
            ||
            $entity->isAttributeChanged('emailSendingSettingWeekdays')
            ||
            $entity->isAttributeChanged('emailSendingSettingDay')
        ) {
            $entity->set('emailSendingLastDateSent', null);
        }

        if (
            $entity->get('type') === 'Grid' &&
            ($entity->has('chartOneColumns') || $entity->has('chartOneY2Columns'))
        ) {
            $this->handleChartDataList($entity);
        }

        parent::beforeSave($entity, $options);
    }

    protected function handleChartDataList(Entity $entity)
    {
        $groupBy = $entity->get('groupBy') ?? [];

        if (count($groupBy) > 1) {
            $entity->set('chartDataList', null);

            return;
        }

        $chartDataList = $entity->get('chartDataList');

        $y = null;
        $y2 = null;

        if ($chartDataList && count($chartDataList) !== 0) {
            $y = $chartDataList[0]->columnList ?? null;
            $y2 = $chartDataList[0]->y2ColumnList ?? null;
        }

        $newY = $y ?? null;
        $newY2 = $y2 ?? null;

        if ($entity->has('chartOneColumns')) {
            $newY = $entity->get('chartOneColumns');

            if ($newY && count($newY) === 0) {
                $newY = null;
            }
        }

        if ($entity->has('chartOneY2Columns')) {
            $newY2 = $entity->get('chartOneY2Columns');

            if ($newY2 && count($newY) === 0) {
                $newY2 = null;
            }
        }

        if ($newY || $newY2) {
            $newItem = (object) [
                'columnList' => $newY,
                'y2ColumnList' => $newY2,
            ];

            $entity->set('chartDataList', [$newItem]);

            return;
        }

        $entity->set('chartDataList', null);
    }
}
