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

class VoipUser extends \Espo\Services\Record
{
    public function resetDoNotDisturbFlag($data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (isset($data['userId'])) {
            $user = $this->getEntityManager()->getEntity('User', $data['userId']);
            if ($user) {
                $utcTZ = new \DateTimeZone('UTC');
                $now = new \DateTime("now", $utcTZ);

                if ($user->get('voipDoNotDisturb') && $user->get('voipDoNotDisturbUntil')) {
                    $until = new  \DateTime($user->get('voipDoNotDisturbUntil'), $utcTZ);
                    if ($until <= $now) {
                        $user->set('voipDoNotDisturb', false);
                        $user->set('voipDoNotDisturbUntil', null);
                        $this->getEntityManager()->saveEntity($user, ['silent' => true]);
                    }
                }
            }
        }
    }

}
