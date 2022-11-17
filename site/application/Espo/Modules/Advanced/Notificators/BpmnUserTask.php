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

namespace Espo\Modules\Advanced\Notificators;

use \Espo\ORM\Entity;

class BpmnUserTask extends \Espo\Core\Notificators\Base
{
    public function process(Entity $entity, array $options = [])
    {
        if (!$entity->get('assignedUserId')) return;
        if (!$entity->isAttributeChanged('assignedUserId')) return;

        $assignedUserId = $entity->get('assignedUserId');

        if ($entity->isNew()) {
            $isNotSelfAssignment = $assignedUserId !== $entity->get('createdById');
        } else {
            $isNotSelfAssignment = $assignedUserId !== $entity->get('modifiedById');
        }
        if (!$isNotSelfAssignment) return;

        $notification = $this->getEntityManager()->getEntity('Notification');
        $notification->set(array(
            'type' => 'Assign',
            'userId' => $assignedUserId,
            'data' => array(
                'entityType' => $entity->getEntityType(),
                'entityId' => $entity->id,
                'entityName' => $entity->get('name'),
                'isNew' => $entity->isNew(),
                'userId' => $this->getUser()->id,
                'userName' => $this->getUser()->get('name')
            )
        ));
        $this->getEntityManager()->saveEntity($notification);
    }
}