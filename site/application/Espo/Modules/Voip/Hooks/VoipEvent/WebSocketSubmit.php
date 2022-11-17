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

namespace Espo\Modules\Voip\Hooks\VoipEvent;

use Espo\ORM\Entity;

class WebSocketSubmit extends \Espo\Core\Hooks\Base
{
    public static $order = 99;

    protected function init()
    {
        $this->addDependency('webSocketSubmission');
    }

    protected function getWebSocketSubmission()
    {
        return $this->getInjection('webSocketSubmission');
    }

    public function afterSave(Entity $entity, array $options = [])
    {
        if (!$this->getConfig()->get('useWebSocket')) return;

        $userId = $entity->get('assignedUserId');
        if (!$userId) return;

        $list = $this->getEntityManager()->getRepository('VoipEvent')->getNotificationList($entity);
        if (empty($list)) return;

        try {
            $GLOBALS['log']->debug('VoIP: Submit data to websocket: user ['. $userId .'], list ['. var_export($list, true) .']');
            $this->getWebSocketSubmission()->submit('popupNotifications.voipNotification', $userId, (object) [
                'list' => $list
            ]);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('VoIP: Submit popup notification: [' . $e->getCode() . '] ' .$e->getMessage());
        }
    }
}
