<?php

namespace Espo\Modules\Voip\Providers\IexPBX;

use Espo\Core\Exceptions\Error;

class Manager extends \Espo\Modules\Voip\Bases\Manager
{
    protected function normalizeOptions(array $options, $connector = null)
    {
        return [
            'serverUrl' => trim($options['serverUrl']),
            'apiUser' => trim($options['apiUser']),
            'apiSecret' => trim($options['apiSecret']),
        ];
    }

    /**
     * Handle event (incoming / outgoing call)
     */
    public function handleEvent(?array $eventData = null)
    {
        $this->getEventListener()->handle($eventData);
    }

    /**
     * Test connection
     */
    public function testConnection(array $options)
    {
        $apiClient = $this->createApiClient(
            $this->normalizeOptions($options)
        );

        return $apiClient->testConnection();
    }

    /**
     * Click-to-call action
     */
    public function dial(array $data)
    {
        return $this->getApiClient()->actionDial($data['callerId'], $data['toPhoneNumber']);
    }
}
