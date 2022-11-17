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

namespace Espo\Modules\Voip\Providers\Binotel;

use Espo\Modules\Voip\Entities\VoipEvent;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtils;
use Espo\Core\Exceptions\{
    Forbidden,
    BadRequest,
};

class WebhookHandler extends \Espo\Modules\Voip\Bases\WebhookHandler
{
    public function run(array $data, $request)
    {
        $connectorManager = $this->getConnectorManager();
        $postData = $this->normalizePostData($data);

        if (empty($postData['requestType'])) {
            throw new BadRequest();
        }

        $returnData = [
            'status' => 'success',
        ];

        switch ($postData['requestType']) {
            case 'gettingCallSettings':
            case 'apiCallSettings':

                if (!isset($postData['externalNumber'])) {
                    throw new BadRequest();
                }

                $params = [];
                if (isset($postData['pbxNumber'])) {
                    $params['pbxNumber'] = $postData['pbxNumber'];
                }
                if (isset($postData['companyID'])) {
                    $params['companyID'] = $postData['companyID'];
                }

                $returnData = $connectorManager->getCidName($postData['externalNumber'], $params);
                break;

            case 'apiCallCompleted':

                if (isset($postData['callDetails'])) {
                    $eventData = $postData['callDetails'];
                    $eventData['requestType'] = 'hangupTheCall';

                    $connectorManager->handleEvent($eventData);
                }
                break;

            default:
                $connectorManager->handleEvent($postData);
                break;
        }

        $this->printJson($returnData);
    }

    /**
     * Normalize POST data due to new API 3.0
     * OLD key      NEW key
     * didNumber    pbxNumber
     * srcNumber    externalNumber
     * extNumber    internalNumber
     * @param  array  $data
     * @return array
     */
    protected function normalizePostData(array $data)
    {
        $pbxNumber = VoipUtils::getFirstValueByKeys($data, ['pbxNumber', 'didNumber']);
        if ($pbxNumber) {
            $data['pbxNumber'] = $pbxNumber;
        }

        $externalNumber = VoipUtils::getFirstValueByKeys($data, ['externalNumber', 'srcNumber']);
        if ($externalNumber) {
            $data['externalNumber'] = $externalNumber;
        }

        $internalNumber = VoipUtils::getFirstValueByKeys($data, ['internalNumber', 'extNumber']);
        if ($internalNumber) {
            $data['internalNumber'] = $internalNumber;
        }

        return $data;
    }
}
