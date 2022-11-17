<?php
namespace Starface\Api;


class GroupRequests extends Api
{
    const METHOD_GET_GROUP_SETTINGS = 'ucp.v22.requests.group.getGroupSettings';
    const METHOD_SET_GROUP_SETTING = 'ucp.v22.requests.group.setGroupSetting';
    const METHOD_SET_GROUP_SETTINGS = 'ucp.v22.requests.group.setGroupSettings';

    /**
     * @return mixed
     */
    public function getGroupSettings()
    {
        return $this->rpcCall(self::METHOD_GET_GROUP_SETTINGS);
    }

}