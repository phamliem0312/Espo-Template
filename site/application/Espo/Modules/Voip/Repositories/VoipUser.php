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

class VoipUser extends \Espo\Core\ORM\Repositories\RDB
{
    protected function init()
    {
        $this->addDependency('user');
    }

    protected function getUser()
    {
        return $this->getInjection('user');
    }

    /**
     * Set last online time
     *
     * @return void
     */
    public function keepOnline()
    {
        $user = $this->getUser();

        $voipUser = $this->where(array(
                        'userId' => $user->get('id'),
                        'connector' => $user->get('voipConnector'),
                    ))->findOne();

        if (empty($voipUser)) {
            $voipUser = $this->get();
            $voipUser->set(array(
                'userId' => $user->get('id'),
                'connector' => $user->get('voipConnector'),
            ));
        }

        $voipUser->set('lastOnlineTime', date('Y-m-d H:i:s'));

        $this->save($voipUser);
    }
}
