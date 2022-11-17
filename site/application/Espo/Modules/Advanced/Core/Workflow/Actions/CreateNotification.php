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

use Espo\Core\Exceptions\Error;
use Espo\Modules\Advanced\Core\Workflow\Utils;

use Espo\ORM\Entity;

class CreateNotification extends Base
{
    /**
     * Main run method
     *
     * @param  array $actionData
     * @return string
     */
    protected function run(Entity $entity, $actionData)
    {
        if (empty($actionData->recipient)) {
            return;
        }
        if (empty($actionData->messageTemplate)) {
            return;
        }

        $userList = [];
        switch ($actionData->recipient) {
            case 'specifiedUsers':
                if (empty($actionData->userIdList) || !is_array($actionData->userIdList)) {
                    return;
                }
                $userIds = $actionData->userIdList;
                break;

            case 'specifiedTeams':
                $userIds = $this->getHelper()->getUserIdsByTeamIds($actionData->specifiedTeamsIds);
                break;

            case 'teamUsers':
                $entity->loadLinkMultipleField('teams');
                $userIds = $this->getHelper()->getUserIdsByTeamIds($entity->get('teamsIds'));
                break;

            case 'followers':
                $userIds = $this->getHelper()->getFollowerUserIds($entity);
                break;

            case 'followersExcludingAssignedUser':
                $userIds = $this->getHelper()->getFollowerUserIdsExcludingAssignedUser($entity);
                break;
            case 'currentUser':
                $userIds = [$this->getUser()->id];
                break;
            default:
                $userIds = $this->getRecipientUserIdList($actionData->recipient);
                break;
        }

        if (isset($userIds)) {
            foreach ($userIds as $userId) {
                $user = $this->getEntityManager()->getEntity('User', $userId);
                $userList[] = $user;
            }
        }

        $message = $actionData->messageTemplate ?? '';

        $variables = $this->getVariables() ?? (object) [];

        if ($variables) {
            foreach (get_object_vars($variables) as $key => $value) {
                if (is_string($value) || is_int($value) || is_float($value)) {
                    if (is_int($value) || is_float($value)) {
                        $value = strval($value);
                    } else {
                        if (!$value) continue;
                    }
                    $message = str_replace('{$$' . $key . '}', $value, $message);
                }
            }
        }

        foreach ($userList as $user) {
            $notification = $this->getEntityManager()->getEntity('Notification');
            $notification->set(array(
                'type' => 'message',
                'data' => array(
                    'entityId' => $entity->id,
                    'entityType' => $entity->getEntityType(),
                    'entityName' => $entity->get('name'),
                    'userId' => $this->getUser()->id,
                    'userName' => $this->getUser()->get('name')
                ),
                'userId' => $user->id,
                'message' => $message,
                'relatedId' => $entity->id,
                'relatedType' => $entity->getEntityType()
            ));
            $this->getEntityManager()->saveEntity($notification);
        }
        return true;
    }


    /**
     * Get email address defined in workflow
     *
     * @param  string $type
     * @return array
     */
    protected function getRecipientUserIdList($recipient)
    {
        $data = $this->getActionData();

        $link = $recipient;
        $entity = $this->getEntity();
        $e = $entity;

        if (strpos($link, 'link:') === 0) {
            $link = substr($link, 5);
        }

        if (strpos($link, '.')) {
            list ($firstLink, $link) = explode('.', $link);
            if (!$entity->hasRelation($firstLink) && ($entity->getRelationType($firstLink) === 'belongsTo' || $entity->getRelationType($firstLink) === 'belongsToParent')) {
                return [];
            }
            $e = $entity->get($firstLink);
            if (!$e) return [];
        }

        if ($link === 'followers') {
            $idList = $this->getServiceFactory()->create('Stream')->getEntityFolowerIdList($e);
            return $idList;
        }

        if (
            $e->hasRelation($link)
            &&
            ($e->getRelationType($link) === 'hasMany' || $e->getRelationType($link) === 'manyMany')
            &&
            $e->hasLinkMultipleField($link)
            &&
            $e->getRelationParam($link, 'entity')
        ) {
            $idList = $e->getLinkMultipleIdList($link);
            if (!empty($idList)) {
                return $idList;
            }
        }

        $fieldEntity = Utils::getFieldValue($e, $link, true, $this->getEntityManager());
        if ($fieldEntity instanceof \Espo\ORM\Entity) {
            return [$fieldEntity->get('id')];
        }
        return [];
    }
}