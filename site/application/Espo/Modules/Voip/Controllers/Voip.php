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

use Espo\Core\Utils\Util;
use Espo\Core\Exceptions\{
    Error,
    NotFound,
    Forbidden,
    BadRequest,
};

class Voip extends \Espo\Core\Controllers\Base
{
    protected $permittedFields = array(
        'voipUser',
        'voipPassword',
        'voipMute',
        'voipNotifications',
        'voipInternalCall',
        'voipConnector',
        'voipDoNotDisturb',
        'voipContext',
        'voipDoNotDisturbUntil',
    );

    public function actionChangeUserSettings($params, $data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if ($this->getUser()->get('type') == 'portal') {
            throw new Forbidden();
        }

        $userId = empty($data['userId']) ? $this->getUser()->get('id') : $data['userId'];

        if (!in_array($this->getUser()->get('type'), ['admin', 'super-admin']) && $this->getUser()->get('id') != $userId) {
            throw new Forbidden();
        }

        $fieldData = array_intersect_key($data, array_flip($this->permittedFields));

        $entityManager = $this->getContainer()->get('entityManager');

        $user = $entityManager->getEntity('User', $userId);
        if (empty($user)) {
            throw new NotFound();
        }

        $user->set($fieldData);

        if (!$entityManager->saveEntity($user)) {
            throw new Error();
        }

        return $user->toArray();
    }

    public function actionStarface($params, $data, $request)
    {
        $connector = 'Starface';

        $connectorManager = $this->getContainer()->get('voipManager')->getConnectorManager($connector);

        if (!$connectorManager) {
            throw new BadRequest('Unknown "connector".');
        }

        if (!$request instanceof \Espo\Core\Api\Request) {
            $request = new \Espo\Modules\Voip\Core\Wrappers\Request($request);
        }

        $GLOBALS['log']->debug('Voip Request URL: ' . $request->getServerParam('HTTP_HOST') . $request->getServerParam('REQUEST_URI'));
        $GLOBALS['log']->debug('Voip Content Type: ' . $request->getContentType());
        $GLOBALS['log']->debug('Voip Input Data: ' . var_export($data, true));

        return $connectorManager->actionRunWebhook([], $request);
    }

    public function actionWebhook($params, $data, $request)
    {
        if (empty($params['connector'])) {
            throw new BadRequest('Unknown "connector".');
        }

        if (empty($params['accessKey'])) {
            throw new BadRequest('Unknown "accessKey".');
        }

        $connectorManager = $this->getContainer()->get('voipManager')->getConnectorManager($params['connector']);

        if (!$connectorManager) {
            throw new BadRequest('Unknown "connector".');
        }

        if (!$connectorManager->checkAccessKey($params['accessKey'])) {
            throw new Forbidden();
        }

        if (!$request instanceof \Espo\Core\Api\Request) {
            $request = new \Espo\Modules\Voip\Core\Wrappers\Request($request);
        }

        $GLOBALS['log']->debug('Voip Request URL: ' . $request->getServerParam('HTTP_HOST') . $request->getServerParam('REQUEST_URI'));
        $GLOBALS['log']->debug('Voip Content Type: ' . $request->getContentType());
        $GLOBALS['log']->debug('Voip Input Data: ' . var_export($data, true));

        $nornalizedData = $this->normalizeData($data, $request);

        return $connectorManager->actionRunWebhook($nornalizedData, $request);
    }

    private function normalizeData($data, $request)
    {
        if (is_string($data) && preg_match('/application\/x-www-form-urlencoded/i', $request->getContentType())) {
            parse_str($data, $nornalizedData);

            if (is_array($nornalizedData)) {
                $data = $nornalizedData;
            }
        }

        if (is_object($data)) {
            $data = Util::objectToArray($data);
        }

        if ($request->isGet()) {
            return [];
        }

        if (empty($data) || !is_array($data)) {
            throw new BadRequest('Incorrect POST data.');
        }

        return $data;
    }

    public function actionAddConnector($params, $data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!in_array($this->getUser()->get('type'), ['admin', 'super-admin'])) {
            throw new Forbidden();
        }

        $connectionManager = $this->getContainer()->get('voipConnectionManager');
        $result = $connectionManager->addConnector($data['parent']);

        return json_encode($result);
    }

    public function actionRemoveConnector($params, $data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!in_array($this->getUser()->get('type'), ['admin', 'super-admin'])) {
            throw new Forbidden();
        }

        $connectionManager = $this->getContainer()->get('voipConnectionManager');
        $result = $connectionManager->removeConnector($data['name']);

        return json_encode($result);
    }

    public function actionGetConnectors($params, $data)
    {
        $connectionManager = $this->getContainer()->get('voipConnectionManager');
        $result = $connectionManager->getActiveList();

        return json_encode($result);
    }

    public function actionGetLines($params, $data)
    {
        $voipHelper = $this->getContainer()->get('voipHelper');
        $result = $voipHelper->getLineList();

        return json_encode($result);
    }

    public function actionGetQueues($params, $data)
    {
        $voipHelper = $this->getContainer()->get('voipHelper');
        $result = $voipHelper->getQueueList();

        return json_encode($result);
    }

    public function actionGetQueueNumbers($params, $data)
    {
        $voipHelper = $this->getContainer()->get('voipHelper');
        $result = $voipHelper->getQueueNumberList();

        return json_encode($result);
    }

    public function postActionTestConnection($params, $data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        if (!in_array($this->getUser()->get('type'), ['admin', 'super-admin']) && $this->getUser()->get('id') !== $data['id']) {
            throw new Forbidden();
        }

        $voipManager = $this->getContainer()->get('voipManager');
        $connectorManager = $voipManager->getConnectorManager($data['connector']);

        return $connectorManager->testConnection($data);
    }

    public function postActionTwilioSipDomainList($params, $data)
    {
        if (is_object($data)) {
            $data = (array) $data;
        }

        $voipManager = $this->getContainer()->get('voipManager');
        $connectorManager = $voipManager->getConnectorManager($data['connector']);

        if (isset($data['twilioAccountSid']) && isset($data['twilioAuthToken'])) {
            return $connectorManager->getSipDomainsByParams($data);
        }

        return $connectorManager->getSipDomainlist();
    }
}
