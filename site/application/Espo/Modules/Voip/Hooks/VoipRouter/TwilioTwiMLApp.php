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

namespace Espo\Modules\Voip\Hooks\VoipRouter;

use Espo\ORM\Entity;

class TwilioTwiMLApp extends \Espo\Core\Hooks\Base
{
    public static $order = 10;

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

    public function afterSave(Entity $entity)
    {
        if ($entity->isAttributeChanged('status')) {

            $connector = $entity->get('connector');
            $twilioConnectorList = $this->getVoipConnectionManager()->getActiveListByProviderName('Twilio');
            if (!in_array($connector, array_keys($twilioConnectorList))) {
                return;
            }

            if (!$entity->get('externalId')) {
                return;
            }

            $connectorManager = $this->getVoipManager()->getConnectorManager($connector);
            $connectorData = $connectorManager->getConnectorData();

            if (empty($connectorData->twilioApplicationSid)) {
                return;
            }

            $twilioPhoneData = $connectorManager->getPhoneNumber($entity->get('externalId'));

            switch ($entity->get('status')) {
                case 'Active':
                    if ($twilioPhoneData['voiceApplicationSid'] != $connectorData->twilioApplicationSid || $twilioPhoneData['smsApplicationSid'] != $connectorData->twilioApplicationSid) {
                        $connectorManager->updatePhoneNumber($twilioPhoneData['sid'], array(
                            'voiceApplicationSid' => $connectorData->twilioApplicationSid,
                            'smsApplicationSid' => $connectorData->twilioApplicationSid,
                        ));
                    }
                    break;

                case 'Inactive':
                    //todo: return previous configuration
                    break;
            }
        }

    }
}
