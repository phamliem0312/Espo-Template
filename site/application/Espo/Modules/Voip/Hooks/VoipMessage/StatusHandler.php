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

namespace Espo\Modules\Voip\Hooks\VoipMessage;

use Espo\ORM\Entity;

class StatusHandler extends \Espo\Core\Hooks\Base
{
    public static $order = 20;

    protected function init()
    {
        $this->addDependency('serviceFactory');
        $this->addDependency('metadata');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    public function afterSave(Entity $entity)
    {
        if ($entity->isAttributeChanged('status')) {
            if ($entity->get('direction') == 'outgoing') {

                $note = $this->getEntityManager()->getEntity('Note');

                $note->set('type', 'VoipMessageStatus');
                $note->set('parentId', $entity->get('id'));
                $note->set('parentType', $entity->getEntityType());

                $style = 'default';
                $entityType = $entity->getEntityType();
                $value = $entity->get('status');
                $statusStyles = $this->getMetadata()->get('entityDefs.' . $entity->getEntityName() . '.fields.status.style', array());
                if (!empty($statusStyles) && !empty($statusStyles[$value])) {
                    $style = $statusStyles[$value];
                }

                $note->set('data', array(
                    'field' => 'status',
                    'value' => $value,
                    'style' => $style,
                ));

                $this->getEntityManager()->saveEntity($note);

                $this->getServiceFactory()->create('Notification')->notifyAboutNote(array($entity->get('assignedUserId')), $note);

            }
        }
    }
}
