<?php

namespace Starface\Api;

use Starface\Response\UserState;

class UserStateRequests extends Api
{
    const METHOD_GET_USER_STATE = 'ucp.v22.requests.userState.getUserState';
    const METHOD_SET_DND_SETTING = 'ucp.v22.requests.userState.setDoNotDisturbSetting';
    const METHOD_SET_CHAT_PRESENCE_MESSAGE = 'ucp.v22.requests.userState.setChatPresenceMessage';
    const METHOD_GET_AVATAR_IMAGE = 'ucp.v22.requests.userState.getAvatarImage';
    const METHOD_SET_AVATAR_IMAGE_FILE = 'ucp.v22.requests.userState.setAvatarImageFile';


    /**
     * @param bool $dnd
     * @return bool
     */
    public function setDND($dnd = true)
    {
        return (bool) $this->rpcCall(self::METHOD_SET_DND_SETTING, [$dnd]);
    }

    /**
     * @return UserState
     */
    public function getUserState()
    {
        $d = $this->rpcCall(self::METHOD_GET_USER_STATE);
        return new UserState(
            $d['avatarHash'],
            $d['chatPresence'],
            $d['telephonyState'],
            $d['chatPresenceMessage'],
            $d['redirectAlwaysActive']
        );
    }
}