<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM VoIP Integration
 * Extension Agreement ("License") which can be viewed at
 * https://www.espocrm.com/voip-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2021 Letrium Ltd.
 * 
 * License ID: e36042ded1ed7ba87a149ac5079bd238
 ***********************************************************************************/

namespace Espo\Modules\Voip\Repositories;

use Espo\Core\Utils\Util;
use Espo\Core\Utils\Json;
use Espo\Modules\Voip\Entities\VoipRouter as VoipRouterEntity;
use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;

class VoipRouter extends \Espo\Core\ORM\Repositories\RDB
{
    protected $additionalNumberTypes = [
        'sms',
        'mms',
    ];

    public function beforeSave(Entity $entity, array $options = array())
    {
        $userOrder = $entity->get('userOrder');
        $rules = $entity->get('rules', new \StdClass());

        if (!$userOrder && !empty($rules)) {
            $userOrder = array_keys((array) $rules);
        }

        $orderedRules = new \StdClass();
        if ($userOrder) {
            foreach ($userOrder as $userId) {
                $orderedRules->$userId = new \StdClass();
                if (isset($rules->$userId)) {
                    $orderedRules->$userId = $rules->$userId;
                }
            }
            $entity->setFetched('rules', null);
            $entity->set('rules', $orderedRules);
        }

        parent::beforeSave($entity, $options);
    }

    public function afterSave(Entity $entity, array $options = array())
    {
        parent::afterSave($entity, $options);

        /* manage voipAdditionalNumbers for a User */
        if ($entity->isAttributeChanged('rules') || $entity->isAttributeChanged('status')) {
            switch ($entity->get('status')) {
                case 'Active':
                    $this->addUserAdditionalNumbersForVoipRouter($entity);
                    break;

                case 'Inactive':
                    $this->clearUserAdditionalNumbersForVoipRouter($entity);
                    break;
            }
        }
    }

    protected function beforeRemove(Entity $entity, array $options = array())
    {
        parent::beforeRemove($entity, $options);

        $this->clearUserAdditionalNumbersForVoipRouter($entity);
    }

    /**
     * Remove VoipRouter phone number from "voipAdditionalNumbers" field of all Users from "rules"
     * @param  Entity $voipRouter
     * @return void
     */
    public function clearUserAdditionalNumbersForVoipRouter(Entity $voipRouter)
    {
        $routerNumber = $voipRouter->get('name');
        $rules = $voipRouter->get('rules', new \StdClass());

        foreach ($rules as $userId => $rule) {
            $user = $this->getEntityManager()->getEntity('User', $userId);

            if ($user) {
                $additionalNumbers = $user->get('voipAdditionalNumbers');

                if (isset($additionalNumbers)) {

                    $save = false;
                    foreach ($this->additionalNumberTypes as $type) {
                        if (isset($additionalNumbers->$type) && in_array($routerNumber, $additionalNumbers->$type)) {
                            $typePhoneNumbers = array_diff($additionalNumbers->$type, array($routerNumber));
                            $additionalNumbers->$type = array_values($typePhoneNumbers);
                            $save = true;
                        }
                    }

                    if ($save) {
                        $user->set('voipAdditionalNumbers', $additionalNumbers);
                        $this->getEntityManager()->saveEntity($user);
                    }
                }
            }
        }
    }

    /**
     * Add VoipRouter phone number to "voipAdditionalNumbers" field of all Users from "rules"
     * @param  Entity $voipRouter
     * @return void
     */
    protected function addUserAdditionalNumbersForVoipRouter(Entity $voipRouter)
    {
        $connector = $voipRouter->get('connector');
        $phoneNumber = $voipRouter->get('name');
        $pair = $phoneNumber; //$connector . ':' . $phoneNumber;

        $rules = $voipRouter->get('rules', new \StdClass());

        foreach ($rules as $userId => $rule) {
            $user = $this->getEntityManager()->getEntity('User', $userId);
            $changedUser = false;

            if ($user) {
                $additionalNumbers = $user->get('voipAdditionalNumbers');

                if (empty($additionalNumbers) || !is_object($additionalNumbers)) {
                    $additionalNumbers = new \StdClass();
                }

                $modified = false;

                foreach ($this->additionalNumberTypes as $additionalNumberType) {

                    $typePhoneNumbers = (isset($additionalNumbers->$additionalNumberType)) ? $additionalNumbers->$additionalNumberType : [];

                    if (is_object($typePhoneNumbers)) {
                        $typePhoneNumbers = get_object_vars($typePhoneNumbers);
                    }

                    if (isset($rule->$additionalNumberType) && $rule->$additionalNumberType) {
                        if (!in_array($pair, $typePhoneNumbers)) {
                            array_push($typePhoneNumbers, $pair);
                            $modified = true;
                        }
                    } else {
                        if (in_array($pair, $typePhoneNumbers)) {
                            $typePhoneNumbers = array_diff($typePhoneNumbers, array($pair));
                            $modified = true;
                        }
                    }

                    if ($modified) {
                        $additionalNumbers->$additionalNumberType = array_values($typePhoneNumbers);
                    }
                }

                if ($modified) {
                    $user->set('voipAdditionalNumbers', $additionalNumbers);
                    $changedUser = true;
               }

                if (isset($rule->outgoing) && $rule->outgoing) {

                    if ($user->get('voipUser') != $phoneNumber || $user->get('voipConnector') != $connector) {
                        $user->set('voipUser', $phoneNumber);
                        $user->set('voipConnector', $voipRouter->get('connector'));
                        $changedUser = true;
                    }

                } else {

                    if ($user->get('voipUser') == $phoneNumber && $user->get('voipConnector') == $connector) {
                        $user->set('voipUser', null);
                        $user->set('voipConnector', null);
                        $changedUser = true;
                    }

                }
                if ($changedUser) {
                    $this->getEntityManager()->saveEntity($user);
                }
            }
        }
    }

    /**
     * Get record by externalId
     *
     * @param  string $externalId
     *
     * @return \Espo\Modules\Voip\Entities\VoipRouter
     */
    public function getByExternalId($externalId)
    {
        return $this->where(array(
            'externalId' => $externalId,
        ))->findOne();
    }

    /**
     * Get entity by name and connector
     *
     * @param  string $name
     * @param  string $connector
     *
     * @return \Espo\Modules\Voip\Entities\VoipRouter
     */
    public function getByName($name, $connector = null)
    {
        if (isset($connector)) {
            return $this->where(array(
                'name' => $name,
                'connector' => $connector,
            ))->findOne();
        }

        return $this->where(array(
            'name' => $name,
        ))->findOne();
    }

    /**
     * Get acrive records by connector in array format
     *
     * @param  string $connector
     *
     * @return array
     */
    public function getListByConnector($connector)
    {
        $pdo = $this->getEntityManager()->getPDO();
        $sql = "
            SELECT * FROM `voip_router`
            WHERE `deleted` = 0
            AND `connector` = ".$pdo->quote($connector)."
            AND `status` = 'Active'
        ";

        $sth = $pdo->prepare($sql);
        $sth->execute();

        $records = array();
        if ($data = $sth->fetchAll(\PDO::FETCH_ASSOC)) {
            foreach ($data as $row) {
                $record = array();
                foreach ($row as $fieldName => $fieldValue) {
                    $camelCaseName = Util::toCamelCase($fieldName);
                    $record[$camelCaseName] = $fieldValue;
                }

                $record['data'] = Json::decode($record['data']);
                $records[ $row['id'] ] = $record;
            }
        }

        return $records;
    }

    public function markInactive($ids)
    {
        if (!is_array($ids)) {
            $ids = (array) $ids;
        }

        foreach ($ids as $id) {
            $entity = $this->get($id);
            $entity->set('status', 'Inactive');
            $this->save($entity);
        }
    }

    /**
     * Get message assigned user
     *
     * @param  VoipRouterEntity $entity
     * @param  string           $ruleType
     * @param  array            $ignoredUserList
     * @param  array            $preferedUserList
     *
     * @return string|null
     */
    public function getMessageAssignedUser(VoipRouterEntity $entity, $ruleType, array $ignoredUserList = array(), array $preferedUserList = array())
    {
        $userList = $entity->getUserListByRuleType($ruleType);

        if (!empty($preferedUserList)) {
            foreach ($preferedUserList as $key => $preferedUserId) {
                if (in_array($preferedUserId, $userList)) {
                    return $preferedUserId;
                }
            }
        }

        $assignToField = $ruleType . 'AssignToId';
        if ($entity->get($assignToField)) {
            return $entity->get($assignToField);
        }
    }

    /**
     * Get next user info (id, phoneNumber) from queue
     *
     * @param  VoipRouterEntity $entity
     * @param  array            $ignoredUserList
     * @param  array            $preferedUserList
     * @param  string           $distribution
     *
     * @return array|null
     */
    public function getQueueNextUser(VoipRouterEntity $entity, array $preferedUserList = [], array $ignoredUserList = [], $distribution = 'roundRobin')
    {
        $queueUserIdList = $this->getQueueUserIdList($entity, $preferedUserList, $ignoredUserList);

        if (!empty($preferedUserList)) {
            foreach ($preferedUserList as $preferedUserId) {
                if (in_array($preferedUserId, $queueUserIdList)) {
                    $nextUserId = $preferedUserId;
                    break;
                }
            }
        }

        if (!isset($nextUserId)) {
            $nextUserId = $this->distributeQueueUser($queueUserIdList, $distribution);
        }

        if ($nextUserId) {
            $user = $this->getEntityManager()->getEntity('User', $nextUserId);

            if (!$user || !$user->get('phoneNumber') || $user->get('voipDoNotDisturb')) {
                $ignoredUserList[] = $nextUserId;
                return $this->getQueueNextUser($entity, $preferedUserList, $ignoredUserList, $distribution);
            }

            return [
                'id' => $user->get('id'),
                'phoneNumber' => $user->get('phoneNumber'),
            ];
        }
    }

    /**
     * Get queue list of users for an VoipRouter
     * @param  VoipRouterEntity $entity
     * @param  array            $preferedUserList
     * @param  array            $ignoredUserList
     * @param  string           $distribution
     * @return array
     */
    public function getQueueUserList(VoipRouterEntity $entity, array $preferedUserList = [], array $ignoredUserList = [])
    {
        $queueUserList = [];

        $queueUserIdList = $this->getQueueUserIdList($entity, $preferedUserList, $ignoredUserList);
        foreach ($queueUserIdList as $userId) {
            $user = $this->getEntityManager()->getEntity('User', $userId);
            if (!$user || !$user->get('phoneNumber') || $user->get('voipDoNotDisturb')) {
                continue;
            }

            $queueUserList[] = [
                'id' => $user->get('id'),
                'phoneNumber' => $user->get('phoneNumber'),
            ];
        }

        return $queueUserList;
    }

    protected function getQueueUserIdList(VoipRouterEntity $entity, array $preferedUserList = [], array $ignoredUserList = [])
    {
        $queueUserIdList = [];

        $routerUserList = $entity->getUserListByRuleType(VoipRouterEntity::IN_QUEUE);

        if (!empty($preferedUserList)) {
            $incomingUserList = $entity->getUserListByRuleType(VoipRouterEntity::INCOMING);
            foreach ($preferedUserList as $userId) {
                if (in_array($userId, $routerUserList) ||  in_array($userId, $incomingUserList)) {
                    $queueUserIdList[] = $userId;
                }
            }
        }

        foreach ($routerUserList as $userId) {
            if (!in_array($userId, $queueUserIdList)) {
                $queueUserIdList[] = $userId;
            }
        }

        if (!empty($ignoredUserList)) {
            foreach ($ignoredUserList as $userId) {
                $key = array_search($userId, $queueUserIdList);
                if ($key !== false) {
                    unset($queueUserIdList[$key]);
                }
            }
        }

        return $queueUserIdList;
    }

    /**
     * Get user from queue based on distribution type
     *
     * @param  array  $userList
     * @param  string $type
     *
     * @return string|null
     */
    protected function distributeQueueUser(array $userList, $type = 'roundRobin')
    {
        switch ($type) {
            case 'roundRobin':
                foreach ($userList as $userId) {
                    return $userId;
                }
                break;

            default:
                throw new Error("VoipRouter[distributeQueueUser]: Requested type[".$type."] does not permitted.");
                break;
        }
    }
}
