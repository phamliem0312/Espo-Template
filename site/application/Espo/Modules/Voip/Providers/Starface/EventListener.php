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

namespace Espo\Modules\Voip\Providers\Starface;

use Starface\Response\CallState;
use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;

class EventListener extends \Espo\Modules\Voip\Bases\EventListener
{
    protected $permittedEvenets = array(
        'RINGING',
        'RINGBACK',
        'CONNECTED',
        'HANGUP',
        'INCOMING',
        'PROCEEDING',
        //'REQUESTED',
    );

    public function handle(CallState $event)
    {
        $state = strtoupper($event->getState());

        $GLOBALS['log']->debug('Starface Event: ' . print_r($event, true));

        if (!in_array($state, $this->permittedEvenets)) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $voipRepository = $this->getVoipEventRepository();

        $connector = $this->getConnector();

        $user = $this->getUser();

        $callerNumber = $event->getCallerNumber(); //the person who is calling
        $phoneNumber = $event->getCalledNumber(); //the person who receives the call

        $voipEvents = $voipRepository->createEvents($event->getId(), null, $connector);

        foreach ($voipEvents as $voipEventId => $voipEvent) {
            $isSave = false;

            switch ($state) {

                case 'INCOMING':  //init incoming call
                case 'RINGING':  //ringing (incoming call)
                    $calledNumber = $event->getCalledNumber();
                    $phoneNumber = $event->getCallerNumber();

                    if (empty($calledNumber) || empty($phoneNumber)) {
                        break;
                    }

                    $voipEvent = $this->createEvent($event->getId(), array(
                        'assignedUserId' => $user->get('id'),
                        'phoneNumber' => $phoneNumber,
                        'type' => VoipEventEntity::INCOMING_CALL,
                    ), $connector, VoipEventEntity::INCOMING_CALL);

                    $voipEvent->set(array(
                        'type' => VoipEventEntity::INCOMING_CALL,
                        'status' => VoipEventEntity::RINGING,
                        'dateStart' => date('Y-m-d H:i:s'),
                        'assignedUserId' => $user->get('id'),
                        //'userExtension' => $user->get('voipUser'),
                        'phoneNumber' => $phoneNumber,
                        'ready' => true,
                    ));

                    $isSave = true;
                    break;

                case 'PROCEEDING': //init ougoing call
                case 'RINGBACK': //ringing (ougoing call)
                    if (empty($callerNumber) || empty($phoneNumber)) {
                        break;
                    }

                    $voipEvent = $this->createEvent($event->getId(), array(
                        'assignedUserId' => $user->get('id'),
                        'phoneNumber' => $phoneNumber,
                        'type' => VoipEventEntity::OUTGOING_CALL,
                    ), $connector, VoipEventEntity::OUTGOING_CALL);

                    $voipEvent->set(array(
                        'type' => VoipEventEntity::OUTGOING_CALL,
                        'status' => VoipEventEntity::DIALING,
                        'dateStart' => date('Y-m-d H:i:s'),
                        'assignedUserId' => $user->get('id'),
                        //'userExtension' => $user->get('voipUser'),
                        'phoneNumber' => $phoneNumber,
                        'ready' => true,
                    ));

                    $isSave = true;
                    break;

                case 'CONNECTED': //call is answered
                    if (!$voipEvent->isNew()) {
                        $voipEvent->set(array(
                            'status' => VoipEventEntity::ACTIVE,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'ready' => true,
                        ));
                        $isSave = true;
                    }
                    break;

                case 'HANGUP':
                    if (!$voipEvent->isNew()) {
                        $voipEvent->set([
                            'status' => $voipRepository->getEndedCallStatus($voipEvent->get('status')),
                            'dateEnd' => date('Y-m-d H:i:s'),
                        ]);
                        $isSave = true;
                    }
                    break;
            }

            if ($isSave && $voipRepository->isNeedToSave($voipEvent)) {
                $entityManager->saveEntity($voipEvent);
            }
        }

        return $voipEvent;
    }

    protected function createEvent($uniqueid = null, array $searchParams = null, $connector = null, $type)
    {
        $voipRepository = $this->getVoipEventRepository();

        $voipEvents = $voipRepository->createEvents($uniqueid, $searchParams, $connector);
        foreach ($voipEvents as $voipEvent) {
            if ($voipEvent->get('type') == $type) {
                return $voipEvent;
            }
        }

        $voipEvent = $voipRepository->createEvent(null, null, $connector);
        $voipEvent->set('uniqueid', $uniqueid);

        return $voipEvent;
    }
}
