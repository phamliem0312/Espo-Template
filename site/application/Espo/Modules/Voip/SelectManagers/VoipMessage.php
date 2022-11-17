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

namespace Espo\Modules\Voip\SelectManagers;

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;

class VoipMessage extends \Espo\Core\SelectManagers\Base
{
    public function applyAdditional(array $params, array &$result)
    {
        parent::applyAdditional($params, $result);

        $folderId = $params['folderId'] ?? null;

        if ($folderId) {
            $this->applyFolder($folderId, $result);
        }

        if ($folderId !== 'drafts') {
            $this->addUsersJoin($result);
        }
    }

    public function applyFolder(?string $folderId, array &$result)
    {
        switch ($folderId) {
            case 'all':
                break;
            case 'inbox':
                $this->filterInbox($result);
                break;
            case 'sent':
                $this->filterSent($result);
                break;
            case 'drafts':
                $this->filterDrafts($result);
                break;
            /*case 'important':
                $this->filterImportant($result);
                break;
            case 'trash':
                $this->filterTrash($result);
                break;*/
            default:
                throw new Error('Folder ['. $folderId .'] is not found.');
        }
    }

    protected function filterInbox(&$result)
    {
        $group = [
            'usersMiddle.inTrash=' => false,
            'usersMiddle.folderId' => null,
            [
                'status' => ['receiving', 'received']
            ]
        ];
        $result['whereClause'][] = $group;

        $this->filterOnlyMy($result);
    }

    protected function filterSent(&$result)
    {
        $result['whereClause'][] = [
            'usersMiddle.inTrash=' => false,
            [
                'status' => ['sent', 'accepted', 'queued', 'sending', 'delivered', 'undelivered', 'failed'],
            ],
        ];
    }

    protected function filterDrafts(&$result)
    {
        $result['whereClause'][] = array(
            'status' => 'draft',
            'createdById' => $this->getUser()->get('id')
        );
    }

    protected function filterImportant(&$result)
    {
        $result['whereClause'][] = $this->getWherePartIsImportantIsTrue();
        $this->filterOnlyMy($result);
    }

    protected function filterTrash(&$result)
    {
        $result['whereClause'][] = [
            'usersMiddle.inTrash=' => true
        ];
        $this->filterOnlyMy($result);
    }

    protected function accessOnlyOwn(&$result)
    {
        $this->boolFilterOnlyMy($result);
    }

    protected function accessPortalOnlyOwn(&$result)
    {
        $this->boolFilterOnlyMy($result);
    }

    protected function textFilter($textFilter, array &$result, $noFullText = false)
    {
        $d = array();

        $d['body*'] = '%' . $textFilter . '%';

        $result['whereClause'][] = array(
            'OR' => $d
        );
    }

    protected function filterOnlyMy(&$result)
    {
        if (!$this->hasJoin('users', $result) && !$this->hasLeftJoin('users', $result)) {
            $this->addJoin('users', $result);
        }

        $result['whereClause'][] = [
            'usersMiddle.userId' => $this->getUser()->id
        ];

        $this->addUsersColumns($result);
    }

    public function addUsersJoin(array &$result)
    {
        if (!$this->hasJoin('users', $result) && !$this->hasLeftJoin('users', $result)) {
            $this->addLeftJoin('users', $result);
        }

        $this->setJoinCondition('users', [
            'userId' => $this->getUser()->id
        ], $result);

        $this->addUsersColumns($result);
    }

    protected function addUsersColumns(&$result)
    {
        if (!isset($result['select'])) {
            $result['additionalSelectColumns']['usersMiddle.is_read'] = 'isRead';
            $result['additionalSelectColumns']['usersMiddle.is_important'] = 'isImportant';
            $result['additionalSelectColumns']['usersMiddle.in_trash'] = 'inTrash';
            $result['additionalSelectColumns']['usersMiddle.folder_id'] = 'folderId';
        }
    }

    public function getWherePartIsNotReadIsTrue()
    {
        return [
            'usersMiddle.isRead' => false,
            'OR' => [
                'sentById' => null,
                'sentById!=' => $this->getUser()->id
            ]
        ];
    }

    protected function getWherePartIsNotReadIsFalse()
    {
        return [
            'usersMiddle.isRead' => true
        ];
    }

    protected function getWherePartIsReadIsTrue()
    {
        return [
            'usersMiddle.isRead' => true
        ];
    }

    protected function getWherePartIsReadIsFalse()
    {
        return [
            'usersMiddle.isRead' => false,
            'OR' => [
                'sentById' => null,
                'sentById!=' => $this->getUser()->id
            ]
        ];
    }

    protected function getWherePartIsImportantIsTrue()
    {
        return [
            'usersMiddle.isImportant' => true
        ];
    }

    protected function getWherePartIsImportantIsFalse()
    {
        return [
            'usersMiddle.isImportant' => false
        ];
    }

    public function getActivitiesSelectParams(Entity $entity, array $statusList = [], $isHistory = false)
    {
        $scope = $entity->getEntityType();
        $id = $entity->id;

        $select = array(
            'id',
            'name',
            ['dateSent', 'dateStart'],
            ['VALUE:', 'dateEnd'],
            ['VALUE:', 'dateStartDate'],
            ['VALUE:', 'dateEndDate'],
            ['VALUE:VoipMessage', '_scope'],
            'assignedUserId',
            'assignedUserName',
            'parentType',
            'parentId',
            'status',
            'createdAt',
            'hasAttachment',
        );

        $selectParams = $this->getEmptySelectParams();

        $selectParams['select'] = $select;

        if ($entity->getEntityType() === 'User') {
            $selectParams['whereClause'][] = array(
                'assignedUserId' => $entity->id
            );
        } else {

            if ($scope == 'Account') {
                $selectParams['whereClause'][] = array(
                    'OR' => array(
                        array(
                            'parentId' => $id,
                            'parentType' => 'Account'
                        ),
                        array(
                            'accountId' => $id
                        )
                    )
                );
            } else if ($scope == 'Lead' && $entity->get('createdAccountId')) {
                $selectParams['whereClause'][] = array(
                    'OR' => array(
                        array(
                            'parentId' => $id,
                            'parentType' => 'Lead'
                        ),
                        array(
                            'accountId' => $entity->get('createdAccountId')
                        )
                    )
                );
            } else {
                $selectParams['whereClause'][] = array(
                    'parentId' => $entity->id,
                    'parentType' => $entity->getEntityType()
                );
            }
        }

        $selectParams['whereClause'][]  = array(
            'status' => $statusList
        );

        $this->applyAccess($selectParams);

        return $selectParams;
    }

    public function getSelectParams(array $params, bool $withAcl = false, bool $checkWherePermission = false, bool $forbidComplexExpressions = false) : array
    {
        if (isset($params['orderBy']) && $params['orderBy'] == 'dateStart') {
            $params['orderBy'] = 'dateSent';
        }

        return parent::getSelectParams($params, $withAcl, $checkWherePermission, $forbidComplexExpressions);
    }
}
