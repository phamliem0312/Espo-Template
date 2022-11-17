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

namespace Espo\Modules\Voip\Entities;

use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;

class VoipEvent extends \Espo\Core\ORM\Entity
{
    const INCOMING_CALL = 'incomingCall';
    const OUTGOING_CALL = 'outgoingCall';
    const HANGUP = 'hangup';

    const DIALING = 'dialing';
    const RINGING = 'ringing';
    const ACTIVE = 'active';
    const ANSWERED = 'answered';
    const NO_ANSWER = 'noAnswer';
    const BUSY = 'busy';
    const MISSED = 'missed';

    protected $callDirectionMap = array(
        self::INCOMING_CALL => 'Inbound',
        self::OUTGOING_CALL => 'Outbound',
    );

    protected $callStatusMap = array(
        self::DIALING => 'Not Held',
        self::RINGING => 'Not Held',
        self::ACTIVE => 'Held',
        self::ANSWERED => 'Held',
        self::NO_ANSWER => 'Not Held',
        self::BUSY => 'Not Held',
        self::MISSED => 'Not Held',
    );

    /**
     * Get call direction for Call entity
     *
     * @return string | null
     */
    public function getCallDirection()
    {
        $type = $this->get('type');

        if (isset($this->callDirectionMap[$type])) {
            return $this->callDirectionMap[$type];
        }
    }

    /**
     * Get call status for Call entity
     *
     * @return string
     */
    public function getCallStatus()
    {
        $status = $this->get('status');

        if (isset($this->callStatusMap[$status])) {
            return $this->callStatusMap[$status];
        }

        return $this->callStatusMap[self::MISSED];
    }

    public function getCallDateEnd()
    {
        $dateStart = $this->get('dateStart');
        $dateEnd = $this->get('dateEnd') ?? $dateStart;

        if ($this->getCallStatus() == 'Held') {
            if (!isset($dateEnd)) {
                $dateEnd = date('Y-m-d H:i:s');
            }

            //if the time less 1 minute, set 1 minute
            $dateDiff = strtotime($dateEnd) - strtotime($dateStart);
            if ($dateDiff < 60) {
                $date = new \DateTime($dateStart);
                $date->modify('+1 minute');
                $dateEnd = $date->format('Y-m-d H:i:s');
            }
        }

        return $dateEnd;
    }

    /**
     * Get Account Id of a call
     *
     * @return string | null
     */
    public function getAccountId()
    {
        $entityList = $this->getEntities();

        if (!empty($entityList['Account'])) {
            reset($entityList['Account']);
            return key($entityList['Account']);
        }
    }

    /**
     * Get Entity Ids
     *
     * @return array | null
     */
    public function getEntityIds($entityName)
    {
        $entityList = $this->getEntities();

        if (!empty($entityList[$entityName])) {
            return array_keys($entityList[$entityName]);
        }
    }

    public function getEntities()
    {
        $entityList = $this->get('entities');

        return Util::objectToArray($entityList);
    }
}
