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

namespace Espo\Modules\Voip\Services;

use Espo\ORM\Entity;

class VoipMessage extends \Espo\Services\Record
{
    protected $mandatorySelectAttributeList = [
        'dateSent',
    ];

    public function getFoldersNotReadCounts()
    {
        $data = [];

        $selectManager = $this->getSelectManager($this->getEntityType());
        $selectParams = $selectManager->getEmptySelectParams();
        $selectManager->applyAccess($selectParams);

        $draftsSelectParams = $selectParams;

        $selectParams['whereClause'][] = $selectManager->getWherePartIsNotReadIsTrue();

        $folderIdList = ['inbox', 'drafts'];

        foreach ($folderIdList as $folderId) {
            if ($folderId === 'drafts') {
                $folderSelectParams = $draftsSelectParams;
            } else {
                $folderSelectParams = $selectParams;
            }
            $selectManager->applyFolder($folderId, $folderSelectParams);
            $selectManager->addUsersJoin($folderSelectParams);
            $data[$folderId] = $this->getEntityManager()->getRepository('VoipMessage')->count($folderSelectParams);
        }

        return $data;
    }

    public function markAsReadByIdList(array $idList, $userId = null)
    {
        foreach ($idList as $id) {
            $this->markAsRead($id, $userId);
        }
        return true;
    }

    public function markAsNotReadByIdList(array $idList, $userId = null)
    {
        foreach ($idList as $id) {
            $this->markAsNotRead($id, $userId);
        }
        return true;
    }

    public function markAsRead(string $id, ?string $userId = null)
    {
        if (!method_exists($this->entityManager, 'getQueryBuilder')) {
            return $this->markAsReadDeprecated();
        }

        $userId = $userId ?? $this->getUser()->id;

        $update = $this->entityManager->getQueryBuilder()->update()
            ->in('UserVoipMessage')
            ->set(['isRead' => true])
            ->where([
                'deleted' => false,
                'userId' => $userId,
                'voipMessageId' => $id,
                'isRead' => false,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);

        $this->markPopupAsProcessed($id, $userId);

        return true;
    }

    /*
     * For EspoCRM 5 and lower
     */
    protected function markAsReadDeprecated($id, $userId = null)
    {
        if (!$userId) {
            $userId = $this->getUser()->id;
        }
        $pdo = $this->getEntityManager()->getPDO();
        $sql = "
            UPDATE user_voip_message SET is_read = 1
            WHERE
                deleted = 0 AND
                user_id = " . $pdo->quote($userId) . " AND
                voip_message_id = " . $pdo->quote($id) . "
        ";
        $pdo->query($sql);

        return true;
    }

    public function markAsNotRead($id, $userId = null)
    {
        if (!method_exists($this->entityManager, 'getQueryBuilder')) {
            return $this->markAsNotReadDeprecated();
        }

        $userId = $userId ?? $this->getUser()->id;

        $update = $this->entityManager->getQueryBuilder()->update()
            ->in('UserVoipMessage')
            ->set(['isRead' => false])
            ->where([
                'deleted' => false,
                'userId' => $userId,
                'voipMessageId' => $id,
                'isRead' => true,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);

        return true;
    }

    /*
     * For EspoCRM 5 and lower
     */
    public function markAsNotReadDeprecated($id, $userId = null)
    {
        if (!$userId) {
            $userId = $this->getUser()->id;
        }
        $pdo = $this->getEntityManager()->getPDO();
        $sql = "
            UPDATE user_voip_message SET is_read = 0
            WHERE
                deleted = 0 AND
                user_id = " . $pdo->quote($userId) . " AND
                voip_message_id = " . $pdo->quote($id) . "
        ";

        $pdo->query($sql);
        return true;
    }

    public function markPopupAsProcessed(string $id, string $userId)
    {
        $update = $this->entityManager->getQueryBuilder()->update()
            ->in('VoipMessage')
            ->set(['processed' => true])
            ->where([
                'id' => $id,
                'deleted' => false,
                'assignedUserId' => $userId,
                'processed' => false,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($update);
    }
}
