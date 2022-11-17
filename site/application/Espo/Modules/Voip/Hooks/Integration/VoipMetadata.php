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

namespace Espo\Modules\Voip\Hooks\Integration;

use Espo\ORM\Entity;

class VoipMetadata extends VoipBaseHook
{
    public static $order = 9;

    protected function init()
    {
        parent::init();
        $this->addDependency('dataManager');
    }

    protected function getDataManager()
    {
        return $this->getInjection('dataManager');
    }

    public function afterSave(Entity $entity)
    {
        if (!$this->isVoipConnector($entity)) {
            return;
        }

        $metadata = $this->getMetadata();

        $save = false;

        if ($entity->getFetched('enabled') != $entity->get('enabled')) {
            $save |= $this->updateConnectorList($entity, $metadata);
            $save |= $this->updatePopupNotifications($entity, $metadata);
        }

        $save |= $this->updateVoipOptions($entity, $metadata);

        if ($save) {
            $metadata->save();
            $this->getDataManager()->clearCache();
        }
    }

    protected function updatePopupNotifications(Entity $entity, $metadata)
    {
        $save = false;

        $voipActiveList = $this->getVoipConnectionManager()->getActiveList();
        $disabled = empty($voipActiveList) ? true : false;

        $metadataData = [];

        if ($metadata->get('app.popupNotifications.voipNotification.disabled') != $disabled) {
            $metadataData['voipNotification'] = array(
                'disabled' => $disabled,
            );
        }

        $data = $entity->get('data');
        if (isset($data->messageSupport) && $data->messageSupport && $metadata->get('app.popupNotifications.voipMessageNotification.disabled') != $disabled) {
            $metadataData['voipMessageNotification'] = array(
                'disabled' => $disabled,
            );
        }

        if (!empty($metadataData)) {
            $metadata->set('app', 'popupNotifications', $metadataData);
            $save = true;
        }

        return $save;
    }

    protected function updateConnectorList(Entity $entity, $metadata)
    {
        if ($entity->get('enabled')) {
            $this->getVoipConnectionManager()->addToConnectorList($entity->id, false);
            return true;
        }

        $this->getVoipConnectionManager()->deleteFromConnectorList($entity->id, false);
        return true;
    }

    protected function updateVoipOptions(Entity $entity, $metadata)
    {
        $save = false;

        if (!$entity->get('enabled')) {
            return $save;
        }

        $entityId = $entity->get('id');
        $entityData = $entity->get('data');

        $voipMetadata = $metadata->get('app.voip.options');
        $voipOptions = $voipMetadata[$entityId] ?? [];

        $defs = $metadata->get('integrations.' . $entityId);

        foreach ($defs['fields'] as $name => $options) {
            if (isset($options['metadata']) && $options['metadata'] && (!isset($voipOptions[$name]) || $voipOptions[$name] != $entityData->$name)) {
                $voipOptions[$name] = $entityData->$name;
                $save = true;
            }
        }

        if ($save) {
            $metadata->set('app', 'voip', array(
                'options' => array(
                    $entityId => $voipOptions,
                ),
            ));
        }

        return $save;
    }
}
