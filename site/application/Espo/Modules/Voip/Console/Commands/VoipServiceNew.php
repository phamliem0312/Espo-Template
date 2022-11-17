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

namespace Espo\Modules\Voip\Console\Commands;

use Espo\Modules\Voip\Core\VoipManagerNew;
use Espo\Core\Console\Commands\Command;
use Espo\Core\Exceptions\Error;

class VoipServiceNew implements Command
{
    protected $voipManager;

    public function __construct(VoipManagerNew $voipRecordingNew)
    {
        $this->voipManager = $voipRecordingNew;
    }

    public function run(array $options, array $flagList, array $argumentList)
    {
        $connector = $argumentList[0] ?? null;

        if (empty($connector)) {
            throw new Error('Invalid VoIP connector. Please check your command.');
        }

        $connectorManager = $this->voipManager->getConnectorManager($connector);
        $connectorManager->startServiceEventListener();
    }
}
