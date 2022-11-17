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

use Espo\Modules\Voip\Entities\VoipEvent;
use Espo\Core\Exceptions\{
    Forbidden,
    BadRequest,
};

class WebhookHandler extends \Espo\Modules\Voip\Bases\WebhookHandler
{
    public function run(array $data, $request)
    {
        $voipEventType = $request->getQueryParam('type');
        $userExtension = $data['user'] ?? $request->getQueryParam('user');
        $uniqueid = $data['uniqueid'] ?? $request->getQueryParam('uniqueid');
        $phoneNumber = $data['number'] ?? $request->getQueryParam('number');

        switch ($voipEventType) {
            case 'cidlookup':
                if (empty($phoneNumber)) {
                    throw new BadRequest();
                }

                $this->printHtml(
                    $this->getConnectorManager()->getCidName($phoneNumber)
                );
                break;

            default:
                if (empty($phoneNumber) || empty($userExtension)) {
                    throw new BadRequest();
                }

                return $this->handleCall($phoneNumber, $userExtension, [
                    'uniqueid' => $uniqueid,
                    'voipEventType' => $voipEventType,
                ]);
                break;
        }
    }

    protected function handleCall($phoneNumber, $userExtension, array $params = [])
    {
        if (empty($phoneNumber) || empty($userExtension)) {
            throw new BadRequest();
        }

        $connector = $this->getConnector();
        $entityManager = $this->getEntityManager();
        $voipRepository = $entityManager->getRepository('VoipEvent');

        $voipEvent = $voipRepository->createEvent($params['uniqueid'], [
            'userExtension' => $userExtension,
            'phoneNumber' => $phoneNumber,
        ], $connector);

        $data = array(
            'type' => VoipEvent::INCOMING_CALL,
            'dateStart' => date('Y-m-d H:i:s'),
            'userExtension' => $userExtension,
            'phoneNumber' => $phoneNumber,
        );

        if (empty($params['uniqueid'])) {
            $data['uniqueid'] = uniqid();
            $data['status'] = VoipEvent::ANSWERED;
        }

        if (!empty($params['voipEventType'])) {
            $data['type'] = $params['voipEventType'];
        }

        $voipEvent->set($data);

        if ($voipRepository->isNeedToSave($voipEvent)) {
            return $entityManager->saveEntity($voipEvent);
        }
    }
}
