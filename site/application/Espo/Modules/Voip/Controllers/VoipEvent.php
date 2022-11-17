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

namespace Espo\Modules\Voip\Controllers;

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Utils\Util;

class VoipEvent extends \Espo\Core\Controllers\Record
{
    public function actionCheckCall($params, $data)
    {
        $voipUser = $this->getUser()->get('voipUser');
        if (!empty($voipUser)) {
            $this->getEntityManager()->getRepository('VoipUser')->keepOnline();
        }

        return $this->getEntityManager()->getRepository('VoipEvent')->getNotificationList();
    }

    public function actionCancel($params, $data)
    {
        if (is_object($data)) {
            $data = Util::objectToArray($data);
        }

        $voipHelper = $this->getContainer()->get('voipEventHelper');

        return $voipHelper->closeVoipEvent($data);
    }

    public function actionSave($params, $data)
    {
        if (!$this->getAcl()->checkScope('Call', 'edit')) {
            throw new Forbidden();
        }

        if (is_object($data)) {
            $data = Util::objectToArray($data);
        }

        $voipHelper = $this->getContainer()->get('voipEventHelper');

        return $voipHelper->addCallFromVoipEvent($data, true, true);
    }

    public function actionDial($params, $data)
    {
        if (is_object($data)) {
            $data = Util::objectToArray($data);
        }

        $user = $this->getContainer()->get('user');
        $connector = $user->get('voipConnector');
        if (empty($connector)) {
            throw new Error('VoIP: Empty connector for user ['.$user->get('id').'].');
        }

        $voipManager = $this->getContainer()->get('voipManager');
        $connectorManager = $voipManager->getConnectorManager($connector);

        $voipEventRepository = $this->getEntityManager()->getRepository('VoipEvent');
        $data['connector'] = $connector;

        $normalizedData = $voipEventRepository->normalizeDialData($data);
        $result = $connectorManager->actionDial($normalizedData);

        if (!$result) {
            throw new Error('VoIP: Dial connection failed. Please see log file for details.');
        }

        return true;
    }

    public function actionDialFromCall($params, $data)
    {
        if (is_object($data)) {
            $data = Util::objectToArray($data);
        }

        $user = $this->getContainer()->get('user');
        $connector = $user->get('voipConnector');
        if (empty($connector)) {
            throw new Error('VoIP: Empty connector for a user['.$user->get('id').'].');
        }

        $voipHelper = $this->getContainer()->get('voipEventHelper');

        //get a phone number and an entity
        $data = array_merge($data, (array) $voipHelper->getDialDataFromCall($data['callId'], $connector));
        $data['connector'] = $connector;

        $voipManager = $this->getContainer()->get('voipManager');
        $connectorManager = $voipManager->getConnectorManager($connector);
        $voipEventRepository = $this->getEntityManager()->getRepository('VoipEvent');

        $dialData = $voipEventRepository->normalizeDialData($data);

        $voipHelper->createVoipEventFromCall(array_merge($data, $dialData), $connector);

        $result = $connectorManager->dial($dialData);

        if (!$result) {
            throw new Error('VoIP: Dial connection failed. Please see log file for details.');
        }

        return true;
    }

    public function actionTestConnection($params, $data)
    {
        if (is_object($data)) {
            $data = Util::objectToArray($data);
        }

        if (empty($data['connector'])) {
            throw new Error('VoIP: Empty connector.');
        }

        $connector = $data['connector'];
        unset($data['connector']);

        $voipManager = $this->getContainer()->get('voipManager');
        $connectorManager = $voipManager->getConnectorManager($connector);

        $result = $connectorManager->testConnection($data);

        if ($result || is_null($result)) {
            return true;
        }

        return false;
    }
}
