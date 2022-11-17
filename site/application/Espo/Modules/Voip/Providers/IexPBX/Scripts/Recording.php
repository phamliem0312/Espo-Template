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

namespace Espo\Modules\Voip\Providers\IexPBX\Scripts;

use Espo\Modules\Voip\{
    Entities\VoipEvent as VoipEventEntity,
    Bases\Recording as BaseRecording,
    Core\Utils\Voip as VoipUtils,
};

class Recording extends BaseRecording
{
    private $recordingUrl = '{serverUrl}/?username={apiUser}&password={apiSecret}&action=record&chanid={uniqueid}&type=mp3';

    public function generateUrl(VoipEventEntity $voipEvent)
    {
        $connectorData = $this->getConnectorData($voipEvent);

        $recordingUrl = $this->bindData($this->recordingUrl, [
            'serverUrl' => VoipUtils::normalizerUrl($connectorData->serverUrl),
            'apiUser' => $connectorData->apiUser,
            'apiSecret' => $connectorData->apiSecret,
            'uniqueid' => urlencode($voipEvent->get('uniqueid')),
        ]);

        if (!filter_var($recordingUrl, \FILTER_VALIDATE_URL) === false) {
            return $recordingUrl;
        }
    }
}
