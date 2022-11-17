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

namespace Espo\Modules\Voip\Hooks\User;

use Espo\ORM\Entity;

class DoNotDisturb extends \Espo\Core\Hooks\Base
{
    public static $order = 30;

    public function beforeSave(Entity $entity)
    {
        if ($entity->isAttributeChanged('voipDoNotDisturb') && !$entity->get('voipDoNotDisturb')) {
            $entity->set('voipDoNotDisturbUntil', null);
        }
    }

    public function afterSave(Entity $entity)
    {
        if (($entity->isAttributeChanged('voipDoNotDisturb') || $entity->isAttributeChanged('voipDoNotDisturbUntil')) && $entity->get('voipDoNotDisturbUntil')) {
            $utcTZ = new \DateTimeZone('UTC');
            $until = new \DateTime($entity->get('voipDoNotDisturbUntil'), $utcTZ);
            $data = array('userId' => $entity->get('id'));

            $job = $this->getEntityManager()->getEntity('Job');
            $job->set(array(
                'serviceName' => 'VoipUser',
                'methodName' => 'resetDoNotDisturbFlag',
                'data' => json_encode($data),
                'executeTime' => $until->format('Y-m-d H:i:s')
            ));
             $this->getEntityManager()->saveEntity($job);
        }
    }
}
