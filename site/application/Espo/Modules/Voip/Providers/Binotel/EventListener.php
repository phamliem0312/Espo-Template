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

namespace Espo\Modules\Voip\Providers\Binotel;

use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtils;

class EventListener extends \Espo\Modules\Voip\Bases\EventListener
{
    protected $permittedEvents = array(
        'receivedTheCall',
        'answeredTheCall',
        'hangupTheCall',
        //'transferredTheCall',
    );

    /*
    Разъяснения данных посылаемых от АТС Binotel:
        pbxNumber - номер на который поступил звонок - OLD value in API 2.0: didNumber
        externalNumber - номер абонента в поступившем звонке - OLD value in API 2.0: srcNumber
        dstNumber - номер абонента в совершенном звонке
        internalNumber - внутренний короткий номер сотрудника - OLD value in API 2.0: extNumber
        requestType - тип PUSH запроса
        generalCallID - идентификатор звонка
        callType - тип звонка: входящий - 0, исходящий - 1
        companyID - идентификатор компании в АТС Binotel
        billsec - длительность разговора в секундах
        disposition - состояние звонка
     */

    public function handle(array $eventData)
    {
        $state = $eventData['requestType'];

        $GLOBALS['log']->debug('Binotel Event: ' . print_r($eventData, true));

        if (!in_array($state, $this->permittedEvents)) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $voipRepository = $this->getVoipEventRepository();

        $connector = $this->getConnector();

        $voipEvent = $voipRepository->createEvent($eventData['generalCallID'], null, $connector);

        $isSave = false;

        switch ($state) {
            case 'receivedTheCall':
                if ($eventData['callType'] == '0') { //Ringing (incoming call)
                    $phoneNumber = $eventData['externalNumber'];
                    $queueNumber = $eventData['pbxNumber'];

                    $searchData = array(
                        'phoneNumber' => $phoneNumber,
                        'queueNumber' => $queueNumber,
                    );

                    $voipEvent = $voipRepository->createEvent($eventData['generalCallID'], $searchData, $connector);

                    $voipEvent->set([
                        'type' => VoipEventEntity::INCOMING_CALL,
                        'status' => VoipEventEntity::RINGING,
                        'dateStart' => date('Y-m-d H:i:s'),
                        'phoneNumber' => $phoneNumber,
                        'queueNumber' => $queueNumber,
                        'ready' => false,
                    ]);

                    if (isset($userExtension)) {
                        $voipEvent->set('userExtension', $userExtension);
                    }

                    $isSave = true;

                } else if ($eventData['callType'] == '1') { //ringing (ougoing call)

                    $userExtension = $eventData['internalNumber'];
                    $phoneNumber = VoipUtils::getFirstValueByKeys($eventData, ['dstNumber', 'externalNumber']);

                    $voipEvent = $voipRepository->createEvent($eventData['generalCallID'], array(
                        'userExtension' => $userExtension,
                        'phoneNumber' => $phoneNumber,
                    ), $connector);

                    $voipEvent->set([
                        'type' => VoipEventEntity::OUTGOING_CALL,
                        'status' => VoipEventEntity::DIALING,
                        'dateStart' => date('Y-m-d H:i:s'),
                        'userExtension' => $userExtension,
                        'phoneNumber' => $phoneNumber,
                        'ready' => true,
                    ]);
                    $isSave = true;

                }
                break;

            case 'answeredTheCall': //call is answered
                if (!$voipEvent->isNew()) {
                    $voipEvent->set([
                        'status' => VoipEventEntity::ACTIVE,
                        'dateStart' => date('Y-m-d H:i:s'),
                        'ready' => true,
                    ]);

                    $this->setIfNotIsset($voipEvent, 'userExtension', $eventData['internalNumber']);

                    if (isset($eventData['externalNumber'])) {
                        $this->setIfNotIsset($voipEvent, 'phoneNumber', $eventData['externalNumber']);
                    }
                    if (isset($eventData['pbxNumber'])) {
                        $this->setIfNotIsset($voipEvent, 'queueNumber', $eventData['pbxNumber']);
                    }

                    $isSave = true;
                }
                break;

            case 'hangupTheCall': //call is finished
            case 'apiCallCompleted':
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

        return $voipEvent;
    }
}
