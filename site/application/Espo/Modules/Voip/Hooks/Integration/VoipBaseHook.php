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

class VoipBaseHook extends \Espo\Core\Hooks\Base
{
    protected function init()
    {
        $this->addDependency('voipManager');
        $this->addDependency('voipConnectionManager');
    }

    protected function getVoipManager()
    {
        return $this->getInjection('voipManager');
    }

    protected function getVoipConnectionManager()
    {
        return $this->getInjection('voipConnectionManager');
    }

    protected function isVoipConnector(Entity $entity)
    {
        $isVoip = $this->getMetadata()->get(['integrations', $entity->id, 'voip']);

        if ($isVoip) {
            return true;
        }

        return false;
    }

    protected function getConnectorProviderName(Entity $entity)
    {
        if ($this->isVoipConnector($entity)) {
            $connector = $entity->get('id');
            return $this->getVoipManager()->getProviderName($connector);
        }
    }

    /**
     * Get fetched value. Works only for "beforeSave"
     * @param  string $id
     * @return \Espo\ORM\Entity
     */
    protected function getFetchedConnectorById($id)
    {
        return $this->getEntityManager()->getEntity('Integration', $id);
    }

    public function afterSave(Entity $entity)
    {
    }
}
