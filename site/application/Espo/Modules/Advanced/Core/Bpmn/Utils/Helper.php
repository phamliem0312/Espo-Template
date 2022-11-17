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

namespace Espo\Modules\Advanced\Core\Bpmn\Utils;

use \Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\Core\Utils\Json;

class Helper
{
    public static function getElementsDataFromFlowchartData($data)
    {
        $elementsDataHash = (object) [];
        $eventStartIdList = [];
        $eventStartAllIdList = [];

        if (isset($data->list) && is_array($data->list)) {
            foreach ($data->list as $item) {
                if (!is_object($item)) continue;
                if ($item->type === 'flow') continue;
                $nextElementIdList = [];
                $previousElementIdList = [];
                foreach ($data->list as $itemAnother) {
                    if ($itemAnother->type !== 'flow') continue;
                    if (!isset($itemAnother->startId) || !isset($itemAnother->endId)) continue;
                    if ($itemAnother->startId === $item->id) {
                        $nextElementIdList[] = $itemAnother->endId;
                    } else if ($itemAnother->endId === $item->id) {
                        $previousElementIdList[] = $itemAnother->startId;
                    }
                }
                usort($nextElementIdList, function ($id1, $id2) use ($data) {
                    $item1 = self::getItemById($data, $id1);
                    $item2 = self::getItemById($data, $id2);
                    if (isset($item1->center) && isset($item2->center)) {
                        if ($item1->center->y > $item2->center->y) {
                            return true;
                        } else if ($item1->center->y == $item2->center->y) {
                            if ($item1->center->x > $item2->center->x) {
                                return true;
                            }
                        }
                    }
                });
                $id = $item->id;
                $o = clone $item;
                $o->nextElementIdList = $nextElementIdList;
                $o->previousElementIdList = $previousElementIdList;

                if (isset($item->flowList)) {
                    $o->flowList = [];
                    foreach ($item->flowList as $nextFlowData) {
                        $nextFlowDataCloned = clone $nextFlowData;
                        foreach ($data->list as $itemAnother) {
                            if ($itemAnother->id !== $nextFlowData->id) continue;
                            $nextFlowDataCloned->elementId = $itemAnother->endId;
                            break;
                        }
                        $o->flowList[] = $nextFlowDataCloned;
                    }
                }
                if (!empty($item->defaultFlowId)) {
                    foreach ($data->list as $itemAnother) {
                        if ($itemAnother->id !== $item->defaultFlowId) continue;
                        $o->defaultNextElementId = $itemAnother->endId;
                        break;
                    }
                }
                if ($item->type === 'eventStart') {
                    $eventStartIdList[] = $id;
                }

                if (strpos($item->type, 'eventStart') === 0) {
                    $eventStartAllIdList[] = $id;
                }

                $elementsDataHash->$id = $o;
            }
        }

        return [
            'elementsDataHash' => $elementsDataHash,
            'eventStartIdList' => $eventStartIdList,
            'eventStartAllIdList' => $eventStartAllIdList,
        ];
    }

    protected static function getItemById($data, $id)
    {
        foreach ($data->list as $item) {
            if ($item->id === $id) {
                return $item;
            }
        }
    }
}
