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

namespace Espo\Modules\Voip\Providers\Asterisk;

use PAMI\Message\Event\EventMessage;
use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtil;

abstract class EventListener extends \Espo\Modules\Voip\Bases\EventListener
{
    protected $activeQueueNumbers;

    protected $queueIdentificator = 'queue';

    protected $systemIdentificators = array(
        '<unknown>',
        's',
    );

    protected $permittedEvents = array(
        /*
        //asterisk event name
        'Dial' => array(
            //asterisk key name (getKey()) => asterisk permitted statuses
            'subevent' => array('Begin', 'End'),
        ),
        */
    );

    abstract public function handle(EventMessage $event);

    protected function saveEntity(VoipEventEntity $voipEvent)
    {
        $entityManager = $this->getEntityManager();
        $voipRepository = $this->getVoipEventRepository();

        if ($voipRepository->isNeedToSave($voipEvent)) {
            $entityManager->saveEntity($voipEvent);
        }
    }

    protected function isReturn(EventMessage $event)
    {
        foreach ($this->permittedEvents as $eventName => $subevents) {

            if ($eventName == $event->getName()) {
                if (!empty($subevents)) {
                    foreach ($subevents as $subeventKey => $subeventValues) {
                        $value = $event->getKey($subeventKey);
                        if (in_array($value, $subeventValues)) {
                            return false;
                        }
                    }
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Check if the imconing event is the the queue
     *
     * @param  string $calleridname
     *
     * @return boolean
     */
    protected function isAsteriskQueue($phoneNumber)
    {
        $queueNumbers = $this->getActiveQueueNumbers();

        if (in_array($phoneNumber, $queueNumbers)) {
            return true;
        }

        return false;
    }

    protected function getActiveQueueNumbers()
    {
        if (!isset($this->activeQueueNumbers)) {
            $connectorData = $this->getConnectorData();

            $this->activeQueueNumbers = array();
            if (!empty($connectorData->activeQueueNumbers)) {
                $this->activeQueueNumbers = $connectorData->activeQueueNumbers;
            }
        }

        return $this->activeQueueNumbers;
    }

    protected function isSystemNumber($phoneNumber)
    {
        if (empty($phoneNumber) || in_array($phoneNumber, $this->systemIdentificators)) {
            return true;
        }

        return false;
    }

    protected function markReady(VoipEventEntity $voipEvent)
    {
        if (!$voipEvent->get('userExtension') || !$voipEvent->get('phoneNumber') || $voipEvent->get('userExtension') == $voipEvent->get('phoneNumber')) {
            return;
        }

        switch ($voipEvent->get('type')) {
            case VoipEventEntity::INCOMING_CALL:

                if ($voipEvent->get('status') == VoipEventEntity::ACTIVE) {
                    $voipEvent->set('ready', true);
                    break;
                }

                $displayPopupAfterAnswer = $this->getConnectorData()->displayPopupAfterAnswer ?? false;

                if (!$displayPopupAfterAnswer && !$voipEvent->get('isQueue')) {
                    $voipEvent->set('ready', true);
                }
                break;

            default:
                $voipEvent->set('ready', true);
                break;
        }
    }

    protected function checkMarkQueue($phoneNumber, VoipEventEntity $voipEvent, $checkPhoneNumber = false)
    {
        if ($checkPhoneNumber) {
            if ($this->isSystemNumber($phoneNumber) || !$this->isAsteriskQueue($phoneNumber)) {
                return false;
            }
        }

        $isSave = false;

        if ($voipEvent->get('isQueue') != true) {
            $voipEvent->set('isQueue', true);
            $isSave = true;
        }

        if (isset($phoneNumber) && $voipEvent->get('queueNumber') != $phoneNumber) {
            $voipEvent->set('queueNumber', $phoneNumber);
            $isSave = true;
        }

        return $isSave;
    }

    protected function updateVoipEvent($uniqueid, $userExtension = null, $phoneNumber = null, $setData = array(), $setIfNotIssetData = array())
    {
        $connector = $this->getConnector();
        $voipRepository = $this->getVoipEventRepository();

        $searchParams = array();
        if (isset($userExtension)) {
            $searchParams['userExtension'] = $userExtension;
        }

        if (isset($phoneNumber) && !$this->isSystemNumber($phoneNumber)) {
            $searchParams['phoneNumber'] = $phoneNumber;
        }

        $voipEvent = $voipRepository->createEvent($uniqueid, $searchParams, $connector);

        if (!empty($setData)) {
            foreach ($setData as $fieldName => $fieldValue) {
                if ($voipEvent->get($fieldName) != $fieldValue) {
                    $voipEvent->set($fieldName, $fieldValue);
                }
            }
        }

        if (!empty($setIfNotIssetData)) {
            foreach ($setIfNotIssetData as $fieldName => $fieldValue) {
                if ($voipEvent->get($fieldName) != $fieldValue) {
                    $this->setIfNotIsset($voipEvent, $fieldName, $fieldValue);
                }
            }
        }

        $this->markReady($voipEvent);

        return $voipEvent;
    }

    protected function findParentQueueNumber($phoneNumber1, $phoneNumber2)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $sql = "SELECT DISTINCT `queue_number` FROM `voip_event`
                    WHERE `status` = '" . VoipEventEntity::ACTIVE . "'
                    AND `queue_number` IS NOT NULL
                    AND `deleted` = 0
                    AND (`user_extension` IN (".$pdo->quote($phoneNumber1).", ".$pdo->quote($phoneNumber2).") OR `phone_number` IN (".$pdo->quote($phoneNumber1).", ".$pdo->quote($phoneNumber2)."))";

        $sth = $pdo->prepare($sql);
        $sth->execute();

        $data = $sth->fetch(\PDO::FETCH_ASSOC);
        if (!empty($data['queue_number'])) {
            return $data['queue_number'];
        }
    }
}
