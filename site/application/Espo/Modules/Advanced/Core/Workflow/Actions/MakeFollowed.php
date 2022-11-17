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

namespace Espo\Modules\Advanced\Core\Workflow\Actions;

use Espo\ORM\Entity;
use Espo\Modules\Advanced\Core\Workflow\Utils;

class MakeFollowed extends BaseEntity
{
    protected function run(Entity $entity, $actionData)
    {
        if (empty($actionData->whatToFollow)) {
            $actionData->whatToFollow = 'targetEntity';
        }

        $target = $actionData->whatToFollow;

        $targetEntity = null;

        if ($target == 'targetEntity') {
            $targetEntity = $entity;
        }
        else if (strpos($target, 'created:') === 0) {
            $targetEntity = $this->getCreatedEntity($target);
        }
        else {
            $link = $target;

            if (strpos($target, 'link:') === 0) {
                $link = substr($target, 5);
            }

            $type = $this->getMetadata()
                ->get('entityDefs.' . $entity->getEntityType() . '.links.' . $link . '.type');

            if (empty($type)) {
                return;
            }

            $idField = $link . 'Id';

            if ($type == 'belongsTo') {
                if (!$entity->get($idField)) {
                    return;
                }

                $foreignEntityType = $this->getMetadata()
                    ->get('entityDefs.' . $entity->getEntityType() . '.links.' . $link . '.entity');

                if (empty($foreignEntityType)) {
                    return;
                }

                $targetEntity = $this->getEntityManager()
                    ->getEntity($foreignEntityType, $entity->get($idField));
            }
            else if ($type == 'belongsToParent') {
                $typeField = $link . 'Type';

                if (!$entity->get($idField)) {
                    return;
                }

                if (!$entity->get($typeField)) {
                    return;
                }

                $targetEntity = $this->getEntityManager()
                    ->getEntity($entity->get($typeField), $entity->get($idField));
            }
        }

        if (!$targetEntity) {
            return;
        }

        $userIdList = $this->getUserIdList($actionData);

        $streamService = $this->getServiceFactory()->create('Stream');
        $streamService->followEntityMass($targetEntity, $userIdList);

        return true;
    }

    protected function getUserIdList($actionData)
    {
        $entity = $this->getEntity();

        if (!empty($actionData->recipient)) {
            $recipient = $actionData->recipient;
        }
        else {
            $recipient = 'specifiedUsers';
        }

        $userIdList = [];

        if (isset($actionData->userIdList) && is_array($actionData->userIdList)) {
            $userIdList = $actionData->userIdList;
        }

        $teamIdList = [];

        if (isset($actionData->specifiedTeamsIds) && is_array($actionData->specifiedTeamsIds)) {
            $teamIdList = $actionData->specifiedTeamsIds;
        }

        switch ($recipient) {
            case 'specifiedUsers':
                return $userIdList;

            case 'specifiedTeams':
                return $this->getHelper()->getUserIdsByTeamIds($teamIdList);

            case 'currentUser':
                return [$this->getUser()->id];

            case 'teamUsers':
                return $this->getHelper()->getUserIdsByTeamIds($entity->getLinkMultipleIdList('teams'));

            case 'followers':
                return $this->getHelper()->getFollowerUserIds($entity);

            default:
                return $this->getRecipientUserIdList($recipient);

        }
    }

    protected function getRecipientUserIdList($recipient)
    {
        $link = $recipient;

        $entity = $this->getEntity();

        $e = $entity;

        if (strpos($link, 'link:') === 0) {
            $link = substr($link, 5);
        }

        if (strpos($link, '.')) {
            list ($firstLink, $link) = explode('.', $link);

            if (
                !$entity->hasRelation($firstLink) &&
                (
                    $entity->getRelationType($firstLink) === 'belongsTo' ||
                    $entity->getRelationType($firstLink) === 'belongsToParent'
                )
            ) {
                return [];
            }

            $e = $entity->get($firstLink);

            if (!$e) {
                return [];
            }
        }

        if ($link === 'followers') {
            $idList = $this->getServiceFactory()->create('Stream')->getEntityFolowerIdList($e);
            return $idList;
        }

        if (
            $e->hasRelation($link) &&
            ($e->getRelationType($link) === 'hasMany' || $e->getRelationType($link) === 'manyMany') &&
            $e->hasLinkMultipleField($link) &&
            $e->getRelationParam($link, 'entity')
        ) {
            $idList = $e->getLinkMultipleIdList($link);

            return $idList;
        }


        $fieldEntity = Utils::getFieldValue($e, $link, true, $this->getEntityManager());

        if ($fieldEntity instanceof Entity) {
            return [$fieldEntity->get('id')];
        }

        return [];
    }
}
