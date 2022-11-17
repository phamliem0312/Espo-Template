<?php

namespace Starface\Api;


class Service extends Api
{
    const METHOD_SUBSCRIBE_EVENT = 'ucp.v22.requests.service.subscribeEvents';
    const METHOD_GET_PROVIDED_EVENTS = 'ucp.v22.requests.service.getProvidedEvents';
    const METHOD_GET_PROVIDED_REQUESTS = 'ucp.v22.requests.service.getProvidedRequests';
    const METHOD_GET_PROVIDES_EVENTS = 'ucp.v22.requests.service.providesEvents';
    const METHOD_GET_PROVIDES_REQUESTS = 'ucp.v22.requests.service.providesRequests';
    const METHOD_UNSUBSCRIBE_EVENT  = 'ucp.v22.requests.service.unsubscribeEvents';


    /**
     * @param $event
     * @return mixed|null|string
     */
    public function subscribeEvent($event)
    {
        return $this->rpcCall(self::METHOD_SUBSCRIBE_EVENT, [$event]);
    }

    /**
     * @return string[]
     */
    public function getProvidedEvents()
    {
        return $this->rpcCall(self::METHOD_GET_PROVIDED_EVENTS);
    }
}