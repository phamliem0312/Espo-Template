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

namespace Espo\Modules\Voip\Providers\Twilio;

use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;

class EventListener extends \Espo\Modules\Voip\Bases\EventListener
{
    protected $permittedEvenets = array(
        'ringing',
        'in-progress',
        'completed',
        'busy',
        'no-answer',
        'failed',
    );

    public function handle(array $eventData)
    {
        $state = strtolower($eventData['CallStatus']);

        if (!in_array($state, $this->permittedEvenets)) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $voipRepository = $this->getVoipEventRepository();

        $connector = $this->getConnector();

        $voipEvent = $voipRepository->createEvent($eventData['CallSid'], null, $connector);

        $isSave = false;

        switch ($state) {
            case 'ringing':

                switch ($eventData['Direction']) {
                    case 'inbound': //Ringing (incoming call)
                        $phoneNumber = $eventData['From'];
                        $queueNumber = $eventData['To']; //twilio number

                        $searchData = array(
                            'phoneNumber' => $phoneNumber,
                            'queueNumber' => $queueNumber,
                        );

                        $voipEvent = $voipRepository->createEvent($eventData['CallSid'], $searchData, $connector);

                        $voipEvent->set(array(
                            'type' => VoipEventEntity::INCOMING_CALL,
                            'status' => VoipEventEntity::RINGING,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'phoneNumber' => $phoneNumber,
                            'queueNumber' => $queueNumber,
                            'voipRouterId' => !empty($eventData['espoVoipRouterId']) ? $eventData['espoVoipRouterId'] : null,
                        ));

                        if (!empty($eventData['SipDomain'])) {
                            $voipEvent->set('channel', $eventData['SipDomain']);
                        }

                        $isSave = true;
                        break;

                    case 'outbound-api': //ringing (ougoing call)
                        $userExtension = $eventData['To'];
                        $queueNumber = $eventData['From']; //twilio number

                        $voipEvent = $voipRepository->createEvent($eventData['CallSid'], array(
                            'userExtension' => $userExtension,
                        ), $connector);

                        $voipEvent->set(array(
                            'type' => VoipEventEntity::OUTGOING_CALL,
                            'status' => VoipEventEntity::DIALING,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'userExtension' => $userExtension,
                            'queueNumber' => $queueNumber,
                            'voipRouterId' => !empty($eventData['espoVoipRouterId']) ? $eventData['espoVoipRouterId'] : null,
                            'hidden' => true,
                        ));

                        $isSave = true;
                        break;

                    case 'outbound-dial': //ougoing call from api
                        $voipEvent->set(array(
                            'type' => VoipEventEntity::OUTGOING_CALL,
                            'status' => VoipEventEntity::DIALING,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'userExtension' => $eventData['To'],
                            'queueNumber' => $eventData['From'],
                            'phoneNumber' => $eventData['From'],
                            'hidden' => true,
                        ));
                        $isSave = true;
                        break;
                }

                break; //END: ringing

            case 'busy': //call is not answered
            case 'no-answer': //call is not answered
            case 'failed': //call is not connected to the second party

                switch ($eventData['Direction']) {
                    case 'inbound':
                    case 'outbound-api':
                    case 'outbound-dial':
                        $status = $voipRepository->getEndedCallStatus($voipEvent->get('status'));
                        $voipEvent->set('status', $status);
                        $isSave = true;
                        break;
                }

                break; //END: busy, no-answer

            case 'in-progress': //call is answered

                switch ($eventData['Direction']) {
                    case 'inbound':
                    case 'outbound-api':
                        if (!empty($eventData['RecordingSid'])) {
                            $this->updateData($voipEvent, array(
                                'RecordingSid' => $eventData['RecordingSid'],
                                'RecordingUrl' => $eventData['RecordingUrl'],
                            ));
                            $isSave = true;
                        }
                        break;

                    case 'outbound-dial': //bridge: the call connected to both parties
                        //parent event:
                        $parentVoipEvent = $voipRepository->createEvent($eventData['ParentCallSid'], null, $connector);

                        if ($parentVoipEvent->get('type') == VoipEventEntity::INCOMING_CALL) {
                            $this->setIfNotIsset($parentVoipEvent, 'userExtension', $eventData['To']); //incoming call
                            $this->setIfNotIsset($voipEvent, 'userExtension', $parentVoipEvent->get('phoneNumber')); //incoming call
                        } else {
                            $this->setIfNotIsset($parentVoipEvent, 'phoneNumber', $eventData['To']); //outgoing call
                            $this->setIfNotIsset($voipEvent, 'phoneNumber', $parentVoipEvent->get('userExtension')); //outgoing call
                        }

                        $parentVoipEvent->set(array(
                            'destuniqueid' => $eventData['CallSid'],
                            'status' => VoipEventEntity::ACTIVE,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'ready' => true,
                        ));

                        if ($voipRepository->isNeedToSave($parentVoipEvent)) {
                            $entityManager->saveEntity($parentVoipEvent);
                        }

                        //current event:
                        $voipEvent->set(array(
                            'destuniqueid' => $eventData['ParentCallSid'],
                            'status' => VoipEventEntity::ACTIVE,
                            'dateStart' => date('Y-m-d H:i:s'),
                            'ready' => true,
                        ));

                        if (isset($eventData['ForwardedFrom'])) {
                            $this->setIfNotIsset($voipEvent, 'queueNumber', $eventData['ForwardedFrom']);
                        }

                        if (!$voipEvent->get('voipRouterId') && $parentVoipEvent->get('voipRouterId')) {
                            $voipEvent->set('voipRouterId', $parentVoipEvent->get('voipRouterId'));
                        }
                        $isSave = true;
                        break;
                }

                if (!empty($eventData['espoToPhoneNumber'])) {
                    $voipEvent->set('phoneNumber', $eventData['espoToPhoneNumber']);
                    $voipEvent->set('hidden', false);
                    $isSave = true;
                }

                if (!empty($eventData['espoFromPhoneNumber'])) {
                    $voipEvent->set('userExtension', $eventData['espoFromPhoneNumber']);
                    $voipEvent->set('hidden', false);
                    $isSave = true;
                }

                if (!empty($eventData['espoVoipRouterId'])) {
                    $voipEvent->set('voipRouterId', $eventData['espoVoipRouterId']);
                    $isSave = true;
                }

                break; //END: in-progress

            case 'completed': //call is finished

                switch ($eventData['Direction']) {
                    case 'inbound':
                    case 'outbound-api':
                    case 'outbound-dial':
                        if (!empty($eventData['RecordingSid'])) {
                            $this->updateData($voipEvent, array(
                                'RecordingSid' => $eventData['RecordingSid'],
                                'RecordingUrl' => $eventData['RecordingUrl'],
                            ));
                        }
                        break;
                }

                $voipEvent->set([
                    'status' => $voipRepository->getEndedCallStatus($voipEvent->get('status')),
                    'dateEnd' => date('Y-m-d H:i:s'),
                ]);

                $isSave = true;
                break; //END: completed
        }

        if ($isSave && isset($voipEvent) && $voipRepository->isNeedToSave($voipEvent)) {
            $entityManager->saveEntity($voipEvent);
        }

        return $voipEvent;
    }

    protected function updateData(VoipEventEntity $entity, array $newData, $fieldName = 'data')
    {
        $data = $entity->get($fieldName);
        $data = ($data instanceof \stdClass) ? $data : new \stdClass;

        foreach ($newData as $key => $value) {
            $data->$key = $value;
        }

        $entity->set($fieldName, $data);
    }
}
