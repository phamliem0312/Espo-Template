<?php
namespace Starface;

use fXmlRpc\Transport\Guzzle4Bridge;
use GuzzleHttp\Client;
use Starface\Api\CallRequests;
use Starface\Api\Connection;
use Starface\Api\GroupRequests;
use Starface\Api\PhoneRequests;
use Starface\Api\Service;
use Starface\Api\UserStateRequests;

class StarFace
{
    private $id;
    private $authToken;
    private $baseUrl;
    /** @var bool */
    private $isLoggedIn = false;
    /** @var  \fXmlRpc\Client */
    private $client;
    /** @var \GuzzleHttp\Client */
    private $guzzle;

    private $data = array();

    private $lastConnectionTime = 0;

    public function __construct($id, $authToken /*espocrm*/, $baseUrl/*espocrm*/, $callback = null /*espocrm*/)
    {
        $this->authToken = $authToken;
        $this->id = $id;
        $this->baseUrl = $baseUrl;
        $this->url = $this->baseUrl.'/xml-rpc?de.vertico.starface.user='.$this->id.'&de.vertico.starface.auth='.$this->authToken . $this->getCallbackParams($callback);

        $this->guzzle = new Client();
        $this->client = new \fXmlRpc\Client(
            $this->url,
            new Guzzle4Bridge($this->guzzle)
        );
    }

    /** @return \fXmlRpc\Client */
    private function getClient()
    {
        return $this->client;
    }

    /**
     * @return bool
     */
    public function login()
    {
        $this->isLoggedIn = (bool) $this->getConnectionApi()->login();

        /*espocrm*/
        $GLOBALS['log']->debug('Starface: Callback URL [' . $this->url . '].');
        /*END: espocrm*/

        return $this->isLoggedIn;

    }

    /**
     * @return bool
     */
    public function logout()
    {
        $this->isLoggedIn = !$this->getConnectionApi()->logout();

        return $this->isLoggedIn;
    }

    /**
     * @return bool
     */
    public function keepAlive()
    {
        $this->isLoggedIn = $this->getConnectionApi()->keepAlive();

        return (bool) $this->isLoggedIn;
    }

    /**
     * @return bool
     */
    public function isLoggedIn()
    {
        $timeDiff = time() - $this->getLastConnectionTime();

        if (!$this->isLoggedIn || $timeDiff >= 50) {
            $this->isLoggedIn = true;
            $this->isLoggedIn = (bool) $this->getConnectionApi()->keepAlive();
        }

        return (bool) $this->isLoggedIn;
    }

    protected function get($name)
    {
        $name = ucfirst($name);

        if (!isset($this->data[$name])) {
            $className = '\\Starface\\Api\\' . $name;
            if (!class_exists($className)) {
                throw new Exception('StarFace: Class ['.$className.'] does not exists.');
            }

            $this->data[$name] = new $className($this->getClient(), $this);
        }

        return $this->data[$name];
    }

    /** @return Connection */
    protected function getConnectionApi()
    {
        return $this->get('Connection');
    }

    /** @return CallRequests */
    public function getCallApi()
    {
        return $this->get('CallRequests');
    }

    /** @return PhoneRequests */
    public function getPhoneApi()
    {
        return $this->get('PhoneRequests');
    }

    /** @return Service */
    public function getServiceApi()
    {
        return $this->get('Service');
    }

    /** @return UserStateRequests */
    public function getUserStateApi()
    {
        return $this->get('UserStateRequests');
    }

    /** @return GroupRequests */
    public function getGroupApi()
    {
        return $this->get('GroupRequests');
    }

    /*espocrm*/
    protected function getCallbackParams($params)
    {
        $url = '';
        if (!empty($params)) {
            foreach ($params as $name => $value) {
                $url .= '&de.vertico.starface.callback.' . $name . '=' . urlencode($value);
            }
        }

        return $url;
    }

    protected function getLastConnectionTime()
    {
        return $this->lastConnectionTime;
    }

    public function updateConnectionTime()
    {
        $this->lastConnectionTime = time();
    }
}
