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

class BpmnProcess extends \Espo\Core\ORM\Entity
{

    public function isSubProcess()
    {
        return $this->hasParentProcess();
    }

    public function hasParentProcess()
    {
        return $this->get('parentProcessId') && $this->get('parentProcessFlowNodeId');
    }

    public function getElementIdList($notSorted = false)
    {
        $elementsDataHash = $this->get('flowchartElementsDataHash');
        if (!$elementsDataHash) $elementsDataHash = (object) [];

        $elementIdList = array_keys(get_object_vars($elementsDataHash));

        if ($notSorted) {
            return $elementIdList;
        }

        usort($elementIdList, function ($id1, $id2) use ($elementsDataHash) {
            $item1 = $elementsDataHash->$id1;
            $item2 = $elementsDataHash->$id2;
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

        return $elementIdList;
    }

    public function getElementDataById($id)
    {
        if (!$id) return null;

        $elementsDataHash = $this->get('flowchartElementsDataHash');
        if (!$elementsDataHash) $elementsDataHash = (object) [];

        if (!property_exists($elementsDataHash, $id)) return null;

        return $elementsDataHash->$id;
    }

    public function getAttachedToFlowNodeElementIdList(\Espo\Modules\Advanced\Entities\BpmnFlowNode $flowNode)
    {
        $elementIdList = [];

        foreach ($this->getElementIdList() as $id) {
            $item = $this->getElementDataById($id);
            if (!isset($item->attachedToId)) continue;
            if ($item->attachedToId === $flowNode->get('elementId')) {
                $elementIdList[] = $id;
            }
        }

        return $elementIdList;
    }
}
