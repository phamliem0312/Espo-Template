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

use Espo\Modules\Crm\Business\Event\Invitations;

class EventInvitationsAdvanced extends \Espo\Core\Services\Base
{
    protected function init()
    {
        $this->addDependency('mailSender');
        $this->addDependency('config');
        $this->addDependency('dateTime');
        $this->addDependency('fileManager');
        $this->addDependency('number');
        $this->addDependency('defaultLanguage');
        $this->addDependency('templateFileManager');
    }

    protected function getInvitationManager(): Invitations
    {
        return new Invitations(
            $this->getInjection('entityManager'),
            null,
            $this->getInjection('mailSender'),
            $this->getInjection('config'),
            $this->getInjection('fileManager'),
            $this->getInjection('dateTime'),
            $this->getInjection('number'),
            $this->getInjection('defaultLanguage'),
            $this->getInjection('templateFileManager')
        );
    }

    public function sendInvitationsAction($workflowId, $entity)
    {
        $invitationManager = $this->getInvitationManager();
        $emailHash = [];
        $sentCount = 0;

        $users = $entity->get('users');
        foreach ($users as $user) {
            if ($user->id === $this->getUser()->id) {
                if ($entity->getLinkMultipleColumn('users', 'status', $user->id) === 'Accepted') {
                    continue;
                }
            }
            if ($user->get('emailAddress') && !array_key_exists($user->get('emailAddress'), $emailHash)) {
                $invitationManager->sendInvitation($entity, $user, 'users');
                $emailHash[$user->get('emailAddress')] = true;
                $sentCount ++;
            }
        }

        $contacts = $entity->get('contacts');
        foreach ($contacts as $contact) {
            if ($contact->get('emailAddress') && !array_key_exists($contact->get('emailAddress'), $emailHash)) {
                $invitationManager->sendInvitation($entity, $contact, 'contacts');
                $emailHash[$contact->get('emailAddress')] = true;
                $sentCount ++;
            }
        }

        $leads = $entity->get('leads');
        foreach ($leads as $lead) {
            if ($lead->get('emailAddress') && !array_key_exists($lead->get('emailAddress'), $emailHash)) {
                $invitationManager->sendInvitation($entity, $lead, 'leads');
                $emailHash[$lead->get('emailAddress')] = true;
                $sentCount ++;
            }
        }
    }
}
