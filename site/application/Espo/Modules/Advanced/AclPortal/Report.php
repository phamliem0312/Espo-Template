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

namespace Espo\Modules\Advanced\AclPortal;

use \Espo\Entities\User;
use \Espo\ORM\Entity;

class Report extends \Espo\Core\AclPortal\Base
{
    public function checkEntityRead(User $user, Entity $entity, $data)
    {
        if (!$this->checkEntity($user, $entity, $data, 'read')) {
            return false;
        }

        $portalIdList = $entity->getLinkMultipleIdList('portals');

        if ($user->get('portalId') && !in_array($user->get('portalId'), $portalIdList)) {
            return false;
        }

        return true;
    }
}