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

namespace Espo\Modules\Voip\Bases;

use Espo\Modules\Voip\Entities\VoipEvent;
use Espo\ORM\Entity;

abstract class EventListener extends Base
{
    private $voipRepository;

    protected $user = null;

    protected $dialEndStatusMap = array(
        'NOANSWER' => VoipEvent::NO_ANSWER,
        'BUSY' => VoipEvent::BUSY,
        'ANSWER' => VoipEvent::ANSWERED,
    );

    protected $dialEndStatusDefault = VoipEvent::NO_ANSWER;

    protected function getVoipEventRepository()
    {
        if (!isset($this->voipRepository)) {
            $this->voipRepository = $this->getEntityManager()->getRepository('VoipEvent');
        }

        return $this->voipRepository;
    }

    public function setUser(Entity $user)
    {
        $this->user = $user;
    }

    protected function getUser()
    {
        if ($this->user) {
            return $this->user;
        }

        return $this->getContainer()->get('user');
    }
}
