<?php

namespace Espo\Modules\Voip\Providers\IexPBX;

use Espo\Modules\Voip\Entities\VoipEvent;

class EventListener extends \Espo\Modules\Voip\Bases\EventListener
{
    protected $permittedEventList = [
        'incoming',
        'outgoing',
        'status',
    ];

    public function handle(array $eventData)
    {
        $state = $eventData['requestType'];

        if (!in_array($state, $this->permittedEventList)) {
            return;
        }

        $entityManager = $this->getEntityManager();
        $voipRepository = $this->getVoipEventRepository();

        $connector = $this->getConnector();

        $voipEvent = $voipRepository->createEvent($eventData['callId'], null, $connector);

        $isSave = false;

        switch ($state) {
            case 'incoming': /* Ringing (incoming call) */
                $userExtension = $eventData['fromNumber'] ?? null;
                $phoneNumber = $eventData['toNumber'];
                $queueNumber = $eventData['queueNumber'] ?? null;

                $searchData = [
                    'phoneNumber' => $phoneNumber,
                    'queueNumber' => $queueNumber,
                ];

                if ($userExtension) {
                    $searchData['userExtension'] = $userExtension;
                }

                $voipEvent = $voipRepository->createEvent($eventData['callId'], $searchData, $connector);

                $voipEvent->set([
                    'type' => VoipEvent::INCOMING_CALL,
                    'status' => VoipEvent::RINGING,
                    'dateStart' => date('Y-m-d H:i:s'),
                    'phoneNumber' => $phoneNumber,
                    'queueNumber' => $queueNumber,
                    'ready' => true,
                ]);

                if (isset($userExtension)) {
                    $voipEvent->set('userExtension', $userExtension);
                }

                $isSave = true;
                break;

            case 'outgoing': /* Ringing (outgoing call) */
                $userExtension = $eventData['fromNumber'];
                $phoneNumber = $eventData['toNumber'];
                $queueNumber = $eventData['queueNumber'] ?? null;

                $voipEvent = $voipRepository->createEvent($eventData['callId'], [
                    'userExtension' => $userExtension,
                    'phoneNumber' => $phoneNumber,
                ], $connector);

                $voipEvent->set([
                    'type' => VoipEvent::OUTGOING_CALL,
                    'status' => VoipEvent::DIALING,
                    'dateStart' => date('Y-m-d H:i:s'),
                    'userExtension' => $userExtension,
                    'phoneNumber' => $phoneNumber,
                    'queueNumber' => $queueNumber,
                    'ready' => true,
                ]);

                $isSave = true;
                break;

            case 'status':
                if ($voipEvent->isNew()) break;

                switch ($eventData['status']) {
                    case 'ANSWERED': /* Call is answered & finished */
                        $voipEvent->set([
                            'status' => VoipEvent::ACTIVE,
                        ]);
                        break;

                    case 'BUSY': /* Call is NOT answered & finished */
                    case 'FAILED':
                    case 'NO ANSWER':
                    case 'CONGESTION':
                        // todo if needs
                        break;
                }

                $voipEvent->set([
                    'status' => $voipRepository->getEndedCallStatus($voipEvent->get('status')),
                    'dateEnd' => date('Y-m-d H:i:s'),
                ]);

                $isSave = true;
                break;
        }

        if ($isSave && $voipRepository->isNeedToSave($voipEvent)) {
            $entityManager->saveEntity($voipEvent);
        }

        return $voipEvent;
    }
}
