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

class Asterisk13 extends \Espo\Modules\Voip\Providers\Asterisk\EventListener
{
    protected $permittedEvents = [
        'Newexten' => [
            'application' => ['Queue'],
        ],
        'Newstate' => [],
        'DialBegin' => [],
        'Newchannel' => [],
        'Hangup' => [],
        'AgentConnect' => [],
    ];

    /**
     * Handle Asterisk incoming event
     * @see https://wiki.asterisk.org/wiki/display/AST/Asterisk+13+AMI+Events
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

        $uniqueid = $event->getKey('uniqueid');
        $destuniqueid = $event->getKey('linkedid');

        if ($uniqueid == $destuniqueid) {
            $destuniqueid = null;
        }

        $voipRepository = $this->getVoipEventRepository();

        $connector = $this->getConnector();

        $voipEvent = $voipRepository->createEvent($uniqueid, null, $connector);

        $isSave = false;

        switch ($eventName) {
            case 'AgentConnect':
                if ($event->getKey('queue')) {
                    $isSave = $this->checkMarkQueue($event->getKey('queue'), $voipEvent);
                }

                //fix click-to-call issue
                if ($voipEvent->get('phoneNumber') == $voipEvent->get('userExtension')) {
                    $voipEvent->set('type', VoipEventEntity::OUTGOING_CALL);
                    if ($voipEvent->get('queueNumber')) {
                        $voipEvent->set([
                            'phoneNumber' => $voipEvent->get('queueNumber'),
                            'ready' => true,
                        ]);
                    }
                    $isSave = true;
                }
                break;

            case 'Newexten':
                $exten = $event->getKey('extension') ? $event->getKey('extension') : $event->getKey('exten');
                if (!empty($exten)) {
                    $isSave = $this->checkMarkQueue($exten, $voipEvent, true);
                }
                break;

            case 'Newchannel':
                $callerIdNum = $event->getKey('calleridnum');

                if (!$this->isSystemNumber($callerIdNum) && !$voipEvent->get('userExtension')) {
                    $voipEvent = $this->updateVoipEvent(
                        $uniqueid,
                        $callerIdNum,
                        null,
                        [
                            'userExtension' => $callerIdNum,
                        ],
                        [
                            'destuniqueid' => $destuniqueid,
                        ]
                    );
                    $voipEvent->set('ready', false);
                }

                if ($this->isSystemNumber($callerIdNum)) { //system call
                    $voipEvent->set('system', true);
                }

                $isSave = true;
                break;

            case 'Newstate':
                switch ($event->getKey('channelstate')) {
                    case 4: // dial (outgoing call)
                        $this->setIfNotIsset($voipEvent, 'type', VoipEventEntity::OUTGOING_CALL);
                        $isSave = true;
                        break;

                    case 5: // Ringing (incoming call)
                        $userExtension = $event->getKey('calleridnum');
                        $phoneNumber = $event->getKey('connectedlinenum');

                        $voipEvent = $this->updateVoipEvent(
                            $uniqueid,
                            $userExtension,
                            $phoneNumber,
                            [
                                'type' => VoipEventEntity::INCOMING_CALL,
                                'status' => VoipEventEntity::RINGING,
                                'userExtension' => $userExtension,
                                'phoneNumber' => $phoneNumber,
                                'dateStart' => date('Y-m-d H:i:s'),
                                'ready' => false,
                            ],
                            [
                                'destuniqueid' => $destuniqueid,
                            ]
                        );

                        $exten = $event->getKey('exten');
                        if (!$this->isSystemNumber($exten) && !in_array($exten, [$userExtension, $phoneNumber])) {
                            $this->checkMarkQueue($exten, $voipEvent);
                        }

                        if (!$voipEvent->get('isQueue')) {
                            $queueNumber = $this->findParentQueueNumber($userExtension, $phoneNumber);
                            if (!empty($queueNumber)) {
                                $this->checkMarkQueue($queueNumber, $voipEvent);
                            }
                        }

                        $isSave = true;
                        break;
                }
                break;

            case 'DialBegin':
                if (!$uniqueid) {
                    break;
                }

                $userExtension = $event->getKey('calleridnum');
                $phoneNumber = $event->getKey('destcalleridnum');
                $destuniqueid = $event->getKey('destuniqueid');

                if ($event->getKey('destLinkedid') != $uniqueid) {
                    $destuniqueid = $event->getKey('destLinkedid');
                }

                $voipEvent = $this->updateVoipEvent(
                    $uniqueid,
                    $userExtension,
                    $phoneNumber,
                    [
                        'type' => VoipEventEntity::OUTGOING_CALL,
                        'status' => VoipEventEntity::DIALING,
                        'dateStart' => date('Y-m-d H:i:s'),
                        'destuniqueid' => $destuniqueid,
                        'phoneNumber' => $phoneNumber,
                    ],
                    [
                        'userExtension' => $userExtension,
                    ]
                );

                $isSave = true;

                if (!$voipEvent->get('isQueue')) {
                    $queueNumber = $this->findParentQueueNumber($userExtension, $phoneNumber);
                    if (!empty($queueNumber)) {
                        $this->checkMarkQueue($queueNumber, $voipEvent);
                    }
                }

                if ($destuniqueid) {
                    $voipEvent2 = $this->updateVoipEvent($destuniqueid, $phoneNumber, $userExtension, [
                        'destuniqueid' => $uniqueid,
                        'ready' => false,
                    ]);

                    if ($voipEvent2->get('isQueue')) {
                        $this->checkMarkQueue($voipEvent2->get('queueNumber'), $voipEvent);
                    }

                    if ($voipEvent->get('isQueue')) {
                        $this->checkMarkQueue($voipEvent->get('queueNumber'), $voipEvent2);
                    }

                    $this->saveEntity($voipEvent2);
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

        //status of the call
        $channelstate = $event->getKey('channelstate');
        if (!empty($channelstate) && $eventName != 'Hangup') {
            switch ($channelstate) {
                case 6:
                    $voipEvent->set('status', VoipEventEntity::ACTIVE);
                    $this->markReady($voipEvent);
                    $isSave = true;
                    break;
            }
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
