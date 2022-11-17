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
use Espo\Core\Exceptions\Error;

class TwilioHandleSipDomain extends TwilioManageApplications
{
    public static $order = 20;

    public function afterSave(Entity $entity)
    {
        if (!$this->isVoipConnector($entity)) {
            return;
        }

        $connector = $entity->get('id');
        $data = $entity->get('data');

        $connectorManager = $this->getVoipManager()->getConnectorManager($connector);

        if ($connectorManager->getProviderName($connector) == 'Twilio'
            && $entity->get('enabled')
            && !empty($data->sipDomains)
        ) {
            $this->checkConnection($entity);

            $errorMessage = null;

            $sipDomainlist = $data->sipDomains;
            foreach ($sipDomainlist as $sipDomainSid) {
                try {
                    $connectorManager->activateSipDomain($sipDomainSid);
                } catch (\Exception $e) {
                    $errorMessage = $e->getMessage();
                }
            }

            if ($errorMessage) {
                throw new Error($errorMessage);
            }
        }
    }
}
