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

namespace Espo\Modules\Voip\Entities;

class VoipRouter extends \Espo\Core\ORM\Entity
{
    const IN_QUEUE = 'inQueue';
    const INCOMING = 'incoming';
    const OUTGOING = 'outgoing';
    const SMS = 'sms';
    const MMS = 'mms';

    public function getUserListByRuleType($type)
    {
        $rules = $this->get('rules');

        $users = array();
        if (!empty($rules)) {
            foreach ($rules as $userId => $rule) {
                if ($rule->$type) {
                    $users[] = $userId;
                }
            }
        }

        return $users;
    }

}
