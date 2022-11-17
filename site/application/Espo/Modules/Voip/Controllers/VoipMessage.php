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

namespace Espo\Modules\Voip\Controllers;

use Espo\Core\Exceptions\{
    Error,
    Forbidden,
    NotFound,
};

class VoipMessage extends \Espo\Core\Controllers\Record
{
    public function actionCheckMessage($params, $data)
    {
        return $this->getEntityManager()->getRepository('VoipMessage')->getInboundMessageList();
    }

    public function actionRead($params, $data, $request)
    {
        $id = $params['id'];

        if (class_exists('\\Espo\\Core\\Record\\ReadParams')) {
            $entity = $this->getRecordService()->read($id, \Espo\Core\Record\ReadParams::create());
        } else {
            $entity = $this->getRecordService()->read($id);
        }

        if (!$entity) throw new NotFound();

        if (!$entity->get('isRead')) {
            $this->getRecordService()->markAsRead($entity->id);
        }

        return $entity->getValueMap();
    }

    public function actionCancel($params, $data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        $entity = $this->getEntityManager()->getEntity('VoipMessage', $data['eventId']);
        if (!isset($entity)) {
            throw new NotFound('VoipMessage not found.');
        }

        if (
            !in_array($this->getUser()->get('type'), ['admin', 'super-admin'])
            && $entity->get('assignedUserId') != $this->getUser()->get('id')
        ) {
            throw new Forbidden();
        }

        $this->getEntityManager()->getRepository('VoipMessage')->markProcessed($entity);

        return true;
    }

    public function getActionGetFoldersNotReadCounts(&$params, $request, $data)
    {
        return $this->getRecordService()->getFoldersNotReadCounts();
    }

    protected function fetchListParamsFromRequest(&$params, $request, $data)
    {
        parent::fetchListParamsFromRequest($params, $request, $data);

        $folderId = $request->get('folderId');
        if ($folderId) {
            $params['folderId'] = $request->get('folderId');
        }
    }

    public function postActionMarkAsRead($params, $data, $request)
    {
        if (!empty($data->ids)) {
            $idList = $data->ids;
        } else {
            if (!empty($data->id)) {
                $idList = [$data->id];
            } else {
                throw new BadRequest();
            }
        }
        return $this->getRecordService()->markAsReadByIdList($idList);
    }

    public function postActionMarkAsNotRead($params, $data, $request)
    {
        if (!empty($data->ids)) {
            $idList = $data->ids;
        } else {
            if (!empty($data->id)) {
                $idList = [$data->id];
            } else {
                throw new BadRequest();
            }
        }
        return $this->getRecordService()->markAsNotReadByIdList($idList);
    }
}
