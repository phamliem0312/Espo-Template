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

namespace Espo\Modules\Voip\Providers\Asterisk\EventListeners;

use PAMI\Message\Event\EventMessage;
use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtil;

class Asterisk11 extends \Espo\Modules\Voip\Providers\Asterisk\EventListener
{
    protected $permittedEvents = array(
        'Newstate' => array(),
        'Dial' => array(
            'subevent' => array('Begin')
        ),
        'Bridge' => array(
            'bridgestate' => array('Link')
        ),
        'Newchannel' => array(),
        'Hangup' => array(),
        'Join' => array(),
        'AgentConnect' => array(),
    );

    /**
     * Handle Asterisk incoming event
     * @see https://wiki.asterisk.org/wiki/display/AST/Asterisk+11+AMI+Events
     *
     * @param  \PAMI\Message\Event\EventMessage $event
     *
     * @return \Espo\Modules\Voip\Entities\VoipEvent
     */
    public function handle(EventMessage $event)
    {
        $eventName = $event->getName();

        if ($this->isReturn($event)) {
            return;
        }

        $GLOBALS['log']->debug('AMI Event: ' . var_export($event, true));

        $entityManager = $this->getEntityManager();
        $voipRepository = $this->getVoipEventRepository();

        $connector = $this->getConnector();

        $voipEvent = $voipRepository->createEvent($event->getKey('uniqueid'), null, $connector);

        $isSave = false;

        switch ($eventName) {
            case 'Dial':
                switch ($event->getKey('subevent')) {
                    case 'Begin': //dialing (outgoing call)
                        $userExtension = $event->getKey('calleridnum');
                        $phoneNumber = $event->getKey('dialstring') ? $event->getKey('dialstring') : $event->getKey('connectedlinenum');

                        $voipEvent = $this->updateVoipEvent($event->getKey('uniqueid'), $userExtension, $phoneNumber, array(
                            'type' => VoipEventEntity::OUTGOING_CALL,
                            'status' => VoipEventEntity::DIALING,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'destuniqueid' => $event->getKey('destuniqueid'),
                            'phoneNumber' => $phoneNumber,
                        ), array(
                            'userExtension' => $userExtension,
                        ));

                        /*if ($event->getKey('destuniqueid')) {
                            $voipEvent->set('destuniqueid', $event->getKey('destuniqueid'));

                            $voipEvent2 = $this->updateVoipEvent($event->getKey('destuniqueid'), $phoneNumber, $userExtension, array(
                                'type' => VoipEventEntity::INCOMING_CALL,
                                'status' => VoipEventEntity::RINGING,
                                'dateStart' => date('Y-m-d H:i:s'),
                                'destuniqueid' => $event->getKey('uniqueid'),
                                'phoneNumber' => $userExtension,
                            ), array(
                                'userExtension' => $userExtension,
                            ));

                            $voipEvent2->set('ready', false);
                        }*/

                        $isSave = true;
                        break;
                }
                break;

            case 'Join':
            case 'AgentConnect':
                if ($event->getKey('queue')) {
                    $isSave = $this->checkMarkQueue($event->getKey('queue'), $voipEvent);
                }

                //fix click-to-call issue
                if ($voipEvent->get('phoneNumber') == $voipEvent->get('userExtension')) {
                    $voipEvent->set('type', VoipEventEntity::OUTGOING_CALL);
                    if ($voipEvent->get('queueNumber')) {
                        $voipEvent->set('phoneNumber', $voipEvent->get('queueNumber'));
                    }
                    $isSave = true;
                }
                break;

            case 'Newchannel':
                $callerIdNum = $event->getKey('calleridnum');
                $exten = $event->getKey('exten');

                if (!empty($callerIdNum) && !empty($exten)) { //outgoing call
                    $voipEvent = $this->updateVoipEvent($event->getKey('uniqueid'), $callerIdNum, $exten, array(
                        'type' => VoipEventEntity::OUTGOING_CALL,
                        'status' => VoipEventEntity::DIALING,
                        'phoneNumber' => $exten,
                        'userExtension' => $callerIdNum,
                        'dateStart' => date('Y-m-d H:i:s'),
                        'ready' => true,
                    ));
                    $this->checkMarkQueue($exten, $voipEvent, true);

                } else if (!empty($callerIdNum) && !$voipEvent->get('userExtension')) {
                    $voipEvent = $this->updateVoipEvent($event->getKey('uniqueid'), $callerIdNum, null, array(
                        'userExtension' => $callerIdNum,
                    ));
                    $voipEvent->set('ready', false);
                }

                if ($this->isSystemNumber($callerIdNum)) { //system call
                    $voipEvent->set('system', true);
                }

                $isSave = true;
                break;

            case 'Newstate':
            //case 'Newchannel':
                switch ($event->getKey('channelstate')) {
                    case 4:
                        $this->setIfNotIsset($voipEvent, 'type', VoipEventEntity::OUTGOING_CALL);
                        $isSave = true;
                        break;

                    case 5: //Ringing (incoming call)
                        $userExtension = $event->getKey('calleridnum');
                        $phoneNumber = $event->getKey('connectedlinenum');

                        $voipEvent = $this->updateVoipEvent($event->getKey('uniqueid'), $userExtension, $phoneNumber, array(
                            'type' => VoipEventEntity::INCOMING_CALL,
                            'status' => VoipEventEntity::RINGING,
                            'phoneNumber' => $phoneNumber,
                        ), array(
                            'userExtension' => $userExtension,
                        ));

                        $voipEvent->set('ready', false);

                        if (!$voipEvent->get('isQueue')) {
                            $queueNumber = $this->findParentQueueNumber($userExtension, $phoneNumber);
                            if (!empty($queueNumber)) {
                                $this->checkMarkQueue($queueNumber, $voipEvent);
                            }
                        }

                        $this->markReady($voipEvent);

                        $isSave = true;
                        break;

                    case 6: //Up
                        $voipEvent->set(array(
                            'status' => VoipEventEntity::ACTIVE,
                        ));
                        $this->markReady($voipEvent);
                        $isSave = true;
                        break;
                }
                break;

            case 'Bridge': //call is answered
                switch ($event->getKey('bridgestate')) {
                    case 'Link':
                        //caller
                        $voipEvent = $this->updateVoipEvent($event->getKey('uniqueid1'), $event->getKey('callerid1'), $event->getKey('callerid2'), array(
                            'destuniqueid' => $event->getKey('uniqueid2'),
                            'status' => VoipEventEntity::ACTIVE,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'channel' => $event->getKey('channel1'),
                        ), array(
                            'phoneNumber' => $event->getKey('callerid2'),
                            'userExtension' => $event->getKey('callerid1'),
                        ));

                        //incoming call
                        $voipEvent2 = $this->updateVoipEvent($event->getKey('uniqueid2'), $event->getKey('callerid2'), $event->getKey('callerid1'), array(
                            'destuniqueid' => $event->getKey('uniqueid1'),
                            'status' => VoipEventEntity::ACTIVE,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'channel' => $event->getKey('channel2'),
                        ), array(
                            'phoneNumber' => $event->getKey('callerid1'),
                            'userExtension' => $event->getKey('callerid2'),
                        ));

                        if ($voipEvent2->get('isQueue')) {
                            $this->checkMarkQueue($voipEvent2->get('queueNumber'), $voipEvent);
                        }

                        if ($voipEvent->get('isQueue')) {
                            $this->checkMarkQueue($voipEvent->get('queueNumber'), $voipEvent2);
                        }

                        $this->saveEntity($voipEvent2);

                        $isSave = true;
                        break;
                }
                break;

            case 'Hangup': //hangup
                if (!$voipEvent->isNew()) {
                    $voipEvent->set([
                        'status' => $voipRepository->getEndedCallStatus($voipEvent->get('status')),
                        'dateEnd' => date('Y-m-d H:i:s'),
                    ]);
                    $isSave = true;
                }
                break;
        }

        $channel = $event->getKey('channel');
        if (!empty($channel) && !$voipEvent->get('channel')) {
            $voipEvent->set('channel', $channel);
        }

        if ($isSave) {
            $this->saveEntity($voipEvent);
        }

        return $voipEvent;
    }
}
