<?php

namespace Starface\Api;

use fXmlRpc\Client;
use Starface\Exception\NotLoggedInException;
use Starface\StarFace;

class Api
{
    /** @var Client */
    private $client;
    /** @var StarFace */
    private $starFace;

    public function __construct(Client $client, StarFace $starFace)
    {
        $this->client = $client;
        $this->starFace = $starFace;
    }
    /**
     * @return \Starface\StarFace
     */
    protected function getStarFace()
    {
        return $this->starFace;
    }

    /**
     * @return \fXmlRpc\Client
     */
    protected function getClient()
    {
        return $this->client;
    }


    /**
     * This is the main request function to use!!
     * the others are deprecated
     * @param $method
     * @param array $params
     * @param bool $loginRequired
     * @throws NotLoggedInException
     * @return mixed|null|string
     */
    protected function rpcCall($method, $params = [], $loginRequired = true) #that will make ecery think shorter
    {
        if ($loginRequired && !$this->getStarFace()->isLoggedIn()) {
            throw new NotLoggedInException();
        }

        $response = $this->getClient()->call($method, $params);
        $this->getStarFace()->updateConnectionTime();

        return $response;
    }
}