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

class ModifyVoipUser extends \Espo\Core\Hooks\Base
{
    public static $order = 20;

    public function beforeSave(Entity $entity)
    {
        if ($entity->isAttributeChanged('teamsIds')) {
            if ($entity->get('voipUser')) {
                $router = $this->getEntityManager()->getRepository('VoipRouter')->where([
                    'name' => $entity->get('voipUser'),
                    'connector' => $entity->get('voipConnector')]
                    )->findOne();
                if ($router) {
                    $oldEntity = $this->getEntityManager()->getEntity($entity->getEntityName(), $entity->get('id'));
                    $oldEntity->loadLinkMultipleField('teams');

                    $teamId = $router->get('teamId');

                    if ($teamId &&
                        in_array($teamId, $oldEntity->get('teamsIds', [])) &&
                        !in_array($teamId, $entity->get('teamsIds', []))) {

                        $entity->set('voipUser', null);
                        $entity->set('voipConnector', null);
                    }
                }
            }
        }
    }
}
