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

class TwilioManageApplications extends VoipBaseHook
{
    public static $order = 15;

    public function beforeSave(Entity $entity)
    {
        if ($this->getConnectorProviderName($entity) != 'Twilio') {
            return;
        }

        $fetchedConnector = $this->getFetchedConnectorById($entity->get('id'));

        if ($fetchedConnector) {
            $data = $fetchedConnector->get('data');
            $entity->setFetched('accessKey', $data->accessKey);
            $entity->setFetched('twilioAccountSid', $data->twilioAccountSid);
            $entity->setFetched('twilioAuthToken', $data->twilioAuthToken);
        }
    }

    public function afterSave(Entity $entity)
    {
        if (!$this->isVoipConnector($entity) || isset($entity->skipTwilioManageApplicationsHook)) {
            return;
        }

        $connector = $entity->get('id');
        $data = $entity->get('data');

        if ($this->getConnectorProviderName($entity) == 'Twilio'
            && $entity->get('enabled')
            && ($entity->isAttributeChanged('enabled')
            || $entity->get('accessKey') != $entity->getFetched('accessKey')
            || $entity->get('twilioAccountSid') != $entity->getFetched('twilioAccountSid')
            || $entity->get('twilioAuthToken') != $entity->getFetched('twilioAuthToken')
            || empty($data->twilioApplicationSid))
        ) {
            $connectorManager = $this->getVoipManager()->getConnectorManager($connector);
            $connectorManager->activateTwilioApplication($entity);
        }
    }

    protected function checkConnection(Entity $entity)
    {
        $connector = $entity->get('id');
        $connectorManager = $this->getVoipManager()->getConnectorManager($connector);

        //test connection
        try {
            $connectorManager->testCurrentConnection();
        } catch (\Exception $e) {
            $integration = $this->getEntityManager()->getEntity('Integration', $connector);
            $entity->set('enabled', false);
            $integration->set('enabled', false);
            $this->getEntityManager()->saveEntity($integration);

            throw new Error('Connection could not be established');
        }
    }
}
