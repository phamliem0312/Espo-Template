<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Acl;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class BpmnProcess extends \Espo\Core\Acl\Base
{
    public function checkIsOwner(User $user, Entity $entity)
    {
        if ($entity->get('parentProcessId') && $entity->get('parentProcessId') !== $entity->id) {
            $id = $entity->get('parentProcessId');
            if (!$id) return false;
            $parent = $this->getEntityManager()->getEntity('BpmnProcess', $id);
            if ($parent && $this->getAclManager()->getImplementation('BpmnProcess')->checkIsOwner($user, $parent)) {
                return true;
            }
        } else {
            return parent::checkIsOwner($user, $entity);
        }

        return false;
    }

    public function checkInTeam(User $user, Entity $entity)
    {
        if ($entity->get('parentProcessId') && $entity->get('parentProcessId') !== $entity->id) {
            $id = $entity->get('parentProcessId');
            if (!$id) return false;
            $parent = $this->getEntityManager()->getEntity('BpmnProcess', $id);
            if ($parent && $this->getAclManager()->getImplementation('BpmnProcess')->checkInTeam($user, $parent)) {
                return true;
            }
        } else {
            return parent::checkInTeam($user, $entity);
        }

        return false;
    }
}
