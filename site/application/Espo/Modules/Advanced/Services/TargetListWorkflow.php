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

namespace Espo\Modules\Advanced\Services;

use \Espo\Core\Exceptions\Error;

use \Espo\Modules\Crm\Business\Event\Invitations;

class TargetListWorkflow extends \Espo\Core\Services\Base
{
    protected function init()
    {
        $this->addDependency('entityManager');
    }

    public function optOut($workflowId, $entity, $data)
    {
        $targetListId = $data->targetListId ?? null;

        $em = $this->getInjection('entityManager');

        if ($targetListId) {
            $em->getRepository($entity->getEntityType())->updateRelation($entity, 'targetLists', $targetListId, [
                'optedOut' => true
            ]);
        } else {
            $emailAddress = $entity->get('emailAddress');
            if ($emailAddress) {
                $ea = $em->getRepository('EmailAddress')->getByAddress($emailAddress);
                if ($ea) {
                    $ea->set('optOut', true);
                    $em->saveEntity($ea);
                }
            }
        }
    }
}
