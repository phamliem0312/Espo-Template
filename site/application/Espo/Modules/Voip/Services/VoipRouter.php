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

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;

use Espo\ORM\Entity;

class VoipRouter extends \Espo\Services\Record
{
    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);
        $this->loadUserOutgoingPhones($entity);
        $this->loadUserOrder($entity);
    }

    protected function loadUserOutgoingPhones($entity)
    {
        $result = [];

        $users = $this->getEntityManager()->getRepository('User')->where([
            'isActive' => true,
            'type' => ['admin', 'regular']
        ])->find();

        foreach($users as $user) {
            $voipConnector = $user->get('voipConnector', '');
            $voipUser = $user->get('voipUser','');

            $result[$user->id] = trim($voipConnector . '::' . $voipUser, '::');
        }

        $entity->set('outgoingRoutes', $result);
    }

    protected function loadUserOrder($entity)
    {
        $value = [];

        $rules = $entity->get('rules');
        if (is_object($rules)) {
            foreach ($rules as $userId => $rule) {
                $value[] = $userId;
            }
        }
        $entity->set('userOrder', $value);
    }
}
