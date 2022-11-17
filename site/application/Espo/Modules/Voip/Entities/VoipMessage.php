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

class VoipMessage extends \Espo\Core\ORM\Entity
{
    const SMS = 'sms';
    const MMS = 'mms';

    const ACCEPTED = 'accepted';
    const QUEUED = 'queued';
    const SENDING = 'sending';
    const SENT = 'sent';
    const RECEIVING = 'receiving';
    const RECEIVED = 'received';
    const DELIVERED = 'delivered';
    const UNDELIVERED = 'undelivered';
    const FAILED = 'failed';

    public function determineType()
    {
        $mediaUrls = $this->get('mediaUrls');
        if (!empty($mediaUrls)) {
            return self::MMS;
        }

        return self::SMS;
    }

    public function getStatusList()
    {
        return [
            self::ACCEPTED,
            self::QUEUED,
            self::SENDING,
            self::SENT,
            self::RECEIVING,
            self::RECEIVED,
            self::DELIVERED,
            self::UNDELIVERED,
            self::FAILED,
        ];
    }

    protected function _setIsRead($value)
    {
        $this->setValue('isRead', $value !== false);
        if ($value === true || $value === false) {
            $this->setValue('isUsers', true);
        } else {
            $this->setValue('isUsers', false);
        }
    }
}
