<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Core\Bpmn\Elements;

class EventEndSignal extends EventSignal
{
    public function process()
    {
        $this->setProcessed();

        $signal = $this->getSignal();

        if ($signal && is_string($signal)) {
            if (mb_substr($signal, 0, 1) !== '@') {
                $this->getManager()->broadcastSignal($signal);
            }
        } else {
            $GLOBALS['log']->warning("BPM: eventEndSignal, no signal");
        }

        $this->refreshProcess();
        $this->refreshTarget();

        $this->endProcessFlow();

        if ($signal && is_string($signal)) {
            if (mb_substr($signal, 0, 1) !== '@') {
                $this->getSignalManager()->trigger($signal);
            }
            else {
                $this->getSignalManager()->trigger($signal, $this->getTarget());
            }
        }
    }
}
