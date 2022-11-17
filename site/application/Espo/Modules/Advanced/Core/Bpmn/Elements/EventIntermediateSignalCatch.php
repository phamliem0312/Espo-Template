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

use \Espo\Core\Exceptions\Error;

class EventIntermediateSignalCatch extends EventSignal
{
    public function process()
    {
        $signal = $this->getSignal();

        if (!$signal) {
            $this->fail();
            $GLOBALS['log']->warning("BPM: No signal for EventIntermediateSignalCatch");
            return;
        }

        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Pending',
        ]);
        $this->getEntityManager()->saveEntity($flowNode);

        $this->getSignalManager()->subscribe($signal, $flowNode->id);
    }

    public function proceedPending()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set('status', 'In Process');
        $this->getEntityManager()->saveEntity($flowNode);

        $this->rejectConcurrentPendingFlows();
        $this->processNextElement();
    }
}
