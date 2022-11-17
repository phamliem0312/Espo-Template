<?php

namespace Starface\Api;


class PhoneRequests extends Api
{
    const METHOD_GET_AVAILABLE_DISPLAY_NUMBERS = 'ucp.v22.requests.phone.getAvailableDisplayNumbers';
    const METHOD_GET_DISPLAY_NUMBER_ID = 'ucp.v22.requests.phone.getDisplayNumberId';
    const METHOD_SET_DISPLAY_NUMBER_ID = 'ucp.v22.requests.phone.setDisplayNumberId';
    const METHOD_GET_WAITING_INDICATION = 'ucp.v22.requests.phone.getCallWaitingIndication';
    const METHOD_SET_WAITING_INDICATION = 'ucp.v22.requests.phone.setCallWaitingIndication';
    const METHOD_GET_PHONE_IDS = 'ucp.v22.requests.phone.getPhoneIds';
    const METHOD_GET_PRIMARY_PHONE_ID = 'ucp.v22.requests.phone.getPrimaryPhoneId';
    const METHOD_SET_PRIMARY_PHONE_ID = 'ucp.v22.requests.phone.setPrimaryPhoneId';

    /**
     * @return string[]
     */
    public function getPhoneIds()
    {
        return $this->rpcCall(self::METHOD_GET_PHONE_IDS);
    }

    /**
     * @return string|null
     */
    public function getPrimaryPhoneId()
    {
        return $this->rpcCall(self::METHOD_GET_PRIMARY_PHONE_ID);
    }

    //espocrm
    public function getAvailableDisplayNumbers()
    {
        return $this->rpcCall(self::METHOD_GET_AVAILABLE_DISPLAY_NUMBERS);
    }
    //END: espocrm
}
