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

class TwilioAddSyncJob extends VoipBaseHook
{
    public static $order = 30;

    public function afterSave(Entity $entity)
    {
        if (!$this->isVoipConnector($entity)) {
            return;
        }

        if ($entity->isAttributeChanged('enabled')) {
            $connector = $entity->get('id');

            $entityManager = $this->getEntityManager();
            $twilioManager = $this->getVoipManager()->getConnectorManager($connector);

            if ($twilioManager->getProviderName($connector) != 'Twilio') {
                return;
            }

            /* Remove TwilioSyncPhoneNumbers job if no active Twilio connectors */
            if (!$entity->get('enabled')) {
                $twilioConnectors = $this->getVoipConnectionManager()->getActiveListByProviderName('Twilio');

                if (empty($twilioConnectors) && $job = $entityManager->getRepository('ScheduledJob')->where(array('job' => 'TwilioSyncPhoneNumbers'))->findOne()) {
                    $entityManager->removeEntity($job);
                }
                return true;
            }

            /* Add TwilioSyncPhoneNumbers job */
            $scheduledJob = $entityManager->getRepository('ScheduledJob')->where(array('job' => 'TwilioSyncPhoneNumbers'))->findOne();
            if (!$scheduledJob) {
                $scheduledJob = $entityManager->getEntity('ScheduledJob');
                $scheduledJob->set(array(
                   'name' => 'Twilio: Sync phone numbers',
                   'job' => 'TwilioSyncPhoneNumbers',
                   'status' => 'Active',
                   'scheduling' => '*/5 * * * *',
                ));
                $entityManager->saveEntity($scheduledJob);
            }

            $job = $entityManager->getEntity('Job');
            $job->set(array(
                'name' => $scheduledJob->get('name'),
                'scheduledJobId' => $scheduledJob->get('id'),
                'executeTime' => date('Y-m-d H:i:s'),
            ));
            $entityManager->saveEntity($job);
        }
    }
}
