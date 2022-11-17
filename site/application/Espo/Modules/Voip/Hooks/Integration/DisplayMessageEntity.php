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

class DisplayMessageEntity extends VoipBaseHook
{
    public static $order = 50;

    protected $permittedEntities = array(
        'Twilio',
    );

    public function afterSave(Entity $entity)
    {
        if (!$this->isVoipConnector($entity)) {
            return;
        }

        if ($entity->isAttributeChanged('enabled') && $entity->get('enabled') && in_array($entity->get('id'), $this->getPemittedConnectorList())) {
            if ($this->addToMenu('tabList')) {
                $this->addToMenu('quickCreateList');
                $this->getConfig()->save();
            }
        }
    }

    protected function getPemittedConnectorList()
    {
        $connectorList = array();
        foreach ($this->permittedEntities as $connectorName) {
            $connectors = $this->getVoipConnectionManager()->getActiveListByProviderName($connectorName);
            if (!empty($connectors)) {
                $connectorList = array_merge($connectorList, array_keys($connectors));
            }
        }

        return $connectorList;
    }

    /**
     * Add VopMessageEntity to menu
     *
     * @param string $optionName
     *
     * @return bool
     */
    protected function addToMenu($optionName)
    {
        $config = $this->getConfig();

        $menu = $config->get($optionName);
        if (!in_array('VoipMessage', $menu)) {
            $menu[] = 'VoipMessage';
            $config->set($optionName, $menu);

            return true;
        }

        return false;
    }
}
