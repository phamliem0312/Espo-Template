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

namespace Espo\Modules\Advanced\Core\Workflow;

use Espo\ORM\Entity;

use Espo\Core\Container;

class Helper
{
    private $container;

    private $streamService;

    private $entityHelper;

    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    protected function getServiceFactory()
    {
        return $this->container->get('serviceFactory');
    }

    protected function getStreamService()
    {
        if (empty($this->streamService)) {
            $this->streamService = $this->getServiceFactory()->create('Stream');
        }

        return $this->streamService;
    }

    public function getEntityHelper()
    {
        if (!isset($this->entityHelper)) {
            $this->entityHelper = new EntityHelper($this->container);
        }

        return $this->entityHelper;
    }

    /**
     * Get followers users ids
     *
     * @param  Entity $entity
     *
     * @return array
     */
    public function getFollowerUserIds(Entity $entity)
    {
        return $this->getStreamService()->getEntityFolowerIdList($entity);
    }

    /**
     * Get followers users ids excluding AssignedUserId
     *
     * @param  Entity $entity
     *
     * @return array
     */
    public function getFollowerUserIdsExcludingAssignedUser(Entity $entity)
    {
        $userIds = $this->getFollowerUserIds($entity);

        if ($entity->get('assignedUserId')) {
            $assignedUserId = $entity->get('assignedUserId');
            $userIds = array_diff($userIds, array($assignedUserId));
        }

        return $userIds;
    }

    /**
     * Get user ids for team ids
     *
     * @param  array  $teamIds
     *
     * @return array
     */
    public function getUserIdsByTeamIds(array $teamIds)
    {
        $userIds = array();

        if (!empty($teamIds)) {
            $pdo = $this->getEntityManager()->getPDO();

            $quotedIdList = [];

            foreach ($teamIds as $teamId) {
                $quotedIdList[] = $pdo->quote($teamId);
            }

            $sql = "
                SELECT user.id
                FROM team_user
                LEFT JOIN user ON user.id = team_user.user_id
                WHERE
                    user.deleted = 0
                    AND user.is_active = 1
                    AND team_user.team_id IN (" . implode(", ", $quotedIdList) . ")
                    AND team_user.deleted = 0
            ";

            $sth = $pdo->prepare($sql);
            $sth->execute();

            if ($rows = $sth->fetchAll()) {
                foreach ($rows as $row) {
                    $userIds[] = $row['id'];
                }
            }
        }

        return $userIds;
    }

    /**
     * Get email addresses for an entity with specified ids
     *
     * @param  string $entityType
     * @param  array  $entityIds
     *
     * @return array
     */
    public function getEmailAddressesForEntity($entityType, array $entityIds)
    {
        $entityList = $this->getEntityManager()
            ->getRepository($entityType)
            ->select(['id', 'emailAddress'])
            ->where([
                'id' => $entityIds
            ])
            ->find();

        $list = [];

        foreach ($entityList as $entity) {
            $emailAddress = $entity->get('emailAddress');
            if ($emailAddress) {
                $list[] = $emailAddress;
            }
        }

        return $list;
    }

    /**
     * Get primary email addresses for user list
     *
     * @param  array  $userList
     *
     * @return array
     */
    public function getUsersEmailAddress(array $userList)
    {
        return $this->getEmailAddressesForEntity('User', $userList);
    }
}