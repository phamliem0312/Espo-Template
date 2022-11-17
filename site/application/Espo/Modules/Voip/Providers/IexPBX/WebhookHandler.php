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

namespace Espo\Modules\Voip\Providers\IexPBX;

use Espo\Core\Exceptions\{
    Forbidden,
    BadRequest,
};

class WebhookHandler extends \Espo\Modules\Voip\Bases\WebhookHandler
{
    public function run(array $data, $request)
    {
        $requestType = $request->getQueryParam('type');
        $status = $request->getQueryParam('status');

        if (empty($requestType) && !empty($status)) {
            $requestType = 'status';
        }

        switch ($requestType) {
            case 'incoming':
            case 'outgoing':
            case 'status':
                $fromNumber = $request->getQueryParam('src');
                $toNumber = $request->getQueryParam('dst');
                $callId = $request->getQueryParam('chanid');

                if (empty($callId)) {
                    $this->printJson([
                        'success' => false,
                        'errorCode' => 101,
                        'errorMessage' => 'Undefined "chanid" option',
                    ]);
                    return;
                }

                if ($requestType != 'status' && empty($fromNumber)) {
                    $this->printJson([
                        'success' => false,
                        'errorCode' => 102,
                        'errorMessage' => 'Undefined "src" option',
                    ]);
                    return;
                }

                $this->getConnectorManager()->handleEvent([
                    'requestType' => $requestType,
                    'callId' => $callId,
                    'fromNumber' => $fromNumber ?? null,
                    'toNumber' => $toNumber ?? null,
                    'status' => !empty($status) ? strtoupper($status) : null,
                ]);
                break;

            default:
                throw new BadRequest('Unknown request type.');
                break;
        }

        $this->printJson([
            'success' => true,
        ]);
    }
}
