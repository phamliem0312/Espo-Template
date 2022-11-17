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

namespace Espo\Modules\Voip\Providers\Starface;

class Manager extends \Espo\Modules\Voip\Bases\Manager
{
    protected $webhookUrl = '/api/v1/Voip/webhook/Starface';

    protected $onlineTime = '1 minute';

    protected function createApiClient($normalizedOptions)
    {
        return new Api\Starface($normalizedOptions);
    }

    protected function normalizeOptions(array $options, $connector = null)
    {
        $webhookUrl = $this->getContainer()->get('config')->get('siteUrl') . $this->webhookUrl;
        $webhookUrl = str_replace('{CONNECTOR}', $connector, $webhookUrl);
        $parsedUrl = parse_url($webhookUrl);

        $user = $this->getUser();

        return [
            'starfaceVersion' => $options['starfaceVersion'] ?? null,
            'baseUrl' => $options['starfaceProtocol'] . '://' . $options['starfaceHost'] . ':' . $options['starfacePort'],
            'user' => $user->get('voipUser'),
            'password' => $user->get('voipPassword'),
            'callback' => [
                'type' => $parsedUrl['scheme'],
                'host' => $parsedUrl['host'],
                'port' => $this->getCallbackPort($parsedUrl),
                'path' => $parsedUrl['path'],
            ],
        ];
    }

    protected function getCallbackPort(array $parsedUrl)
    {
        if (!empty($parsedUrl['port'])) {
            return $parsedUrl['port'];
        }

        if ($parsedUrl['scheme'] == 'https') {
            return 443;
        }

        return 80;
    }

    public function isAuthorized($starfaceAuthToken)
    {
        $client = $this->getApiClient();

        if ($starfaceAuthToken === $client->getAuthToken()) {
            return true;
        }

        return false;
    }

    /**
     * Real event listener for entry point
     *
     * @return void
     */
    public function handleEvent(array $eventData = null)
    {
        $eventData = $this->getApiClient()->receiveCallState();

        $listener = $this->getEventListener();
        $listener->setConnectorManager($this);

        $listener->setUser(
            $this->getUser()
        );

        return $listener->handle($eventData);
    }

    /**
     *  Sending the online user status for starface server to accept incoming events
     *
     * @return void
     */
    public function startEventListener()
    {
        $this->logoutOfflineUsers();
        $this->keepAliveForAllUsers();
    }

    /**
     * Service version of sending the online user status for starface server to accept incoming events
     *
     * @return void
     */
    public function startServiceEventListener()
    {
        while (!connection_aborted()) {
            $this->logoutOfflineUsers();
            $this->keepAliveForAllUsers();
            sleep(30);
        }
    }

    /**
     * Send keepAlive action for Starface server
     *
     * @return voire
     */
    protected function keepAliveForAllUsers()
    {
        $entityManager = $this->getContainer()->get('entityManager');

        $onlineTime = date('Y-m-d H:i:s', strtotime('-' . $this->onlineTime));

        $pdo = $entityManager->getPDO();

        $query = "SELECT DISTINCT user.* FROM `voip_user`
                    LEFT JOIN `user` ON user.id = user_id AND user.deleted = 0
                    WHERE user.voip_connector LIKE 'Starface%' AND voip_user.last_online_time >= " . $pdo->quote($onlineTime);

        $sth = $pdo->prepare($query);
        $sth->execute();

        $rows = $sth->fetchAll(\PDO::FETCH_ASSOC);

        $scriptStart = time();

        $i=0;
        while ($i < 2) {
            foreach ($rows as $user) {
                $starfaceOptions = $this->getOptions($user['voip_connector']);

                $starfaceOptions['user'] = $user['voip_user'];
                $starfaceOptions['password'] = $user['voip_password'];

                $starface = $this->createApiClient($starfaceOptions);
                try {
                    $isOnline = $starface->keepAlive(false);
                    if (!$isOnline) {
                        $starface->keepAlive();
                        $additionalNumbers = $starface->getAvailablePhoneNumbers();
                        if (!empty($additionalNumbers)) {
                            $userEntity = $entityManager->getEntity('User', $user['id']);
                            if (isset($userEntity)) {
                                $additionalNumbersObj = (object) array(
                                    'starface' => (object) array_values($additionalNumbers)
                                );
                                $userEntity->set('voipAdditionalNumbers', $additionalNumbersObj);
                                $entityManager->saveEntity($userEntity);
                            }
                        }
                    }
                } catch(\Exception $e) {
                    $GLOBALS['log']->error('Starface: Connection problem  for the user ['.$user['voip_user'].'], details: ['.$e->getCode().'] '.$e->getMessage().'.');
                }
            }

            if ($i++ == 0) {
                $sleepTime = 30 - (time() - $scriptStart);
                if ($sleepTime > 0) {
                    sleep($sleepTime);
                }
            }
        }
    }

    public function logoutOfflineUsers()
    {
        $entityManager = $this->getContainer()->get('entityManager');

        $datetime = new \DateTime();
        $datetime->modify('-' . $this->onlineTime);
        $toTime = $datetime->format('Y-m-d H:i:00');

        $datetime->modify('-1 minute');
        $fromTime = $datetime->format('Y-m-d H:i:00');

        $pdo = $entityManager->getPDO();

        $query = "SELECT DISTINCT user.* FROM `voip_user`
                    LEFT JOIN `user` ON user.id = user_id AND user.deleted = 0
                    WHERE user.voip_connector LIKE 'Starface%'
                        AND voip_user.last_online_time BETWEEN " . $pdo->quote($fromTime) . " AND " . $pdo->quote($toTime);

        $sth = $pdo->prepare($query);
        $sth->execute();

        $rows = $sth->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($rows as $user) {
            $starfaceOptions = $this->getOptions($user['voip_connector']);

            $starfaceOptions['user'] = $user['voip_user'];
            $starfaceOptions['password'] = $user['voip_password'];

            $starface = $this->createApiClient($starfaceOptions);
            try {
                $starface->logout();
                $GLOBALS['log']->debug('Starface: User['.$user['voip_user'].'] logged out.');
            } catch(\Exception $e) {
                $GLOBALS['log']->error('Starface: Cannot logout offline user ['.$user['voip_user'].'], details: ['.$e->getCode().'] '.$e->getMessage().'.');
            }
        }
    }

    public function testConnection(array $data)
    {
        $starfaceOptions = $this->getOptions($data['connector']);

        if (!array_key_exists('password', $data) && !empty($data['id'])) {
            $userEntity = $this->getContainer()->get('entityManager')->getEntity('User', $data['id']);
            if ($userEntity) {
                $data['password'] = $userEntity->get('voipPassword');
            }
        }

        if (empty($data['user']) || empty($data['password'])) {
            return false;
        }

        $starfaceOptions['user'] = $data['user'];
        $starfaceOptions['password'] = $data['password'];

        $starface = $this->createApiClient($starfaceOptions);

        try {
            $starface->login();
        } catch (\Exception $e) {
            $GLOBALS['log']->warning('Starface: connection error for a user [' . $data['user'] . '], details: ' . $e->getMessage());
            return false;
        }

        return true;
    }

    public function dial(array $data)
    {
        return $this->getApiClient()->placeCallWithPhone($data['toPhoneNumber']);
    }

    protected function addStarfaceInternalNumber()
    {
    }
}
