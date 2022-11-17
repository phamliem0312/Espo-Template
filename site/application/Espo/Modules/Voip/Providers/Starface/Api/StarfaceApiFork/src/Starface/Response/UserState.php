<?php


namespace Starface\Response;


class UserState
{
    private $avatarHash;
    private $chatPresence;
    private $telephonyState;
    private $chatPresenceMessage;

    public function __construct($avatarHash, $chatPresence, $chatPresenceMessage, $telephonyState)
    {
        $this->avatarHash = $avatarHash;
        $this->chatPresence = $chatPresence;
        $this->chatPresenceMessage = $chatPresenceMessage;
        $this->telephonyState = $telephonyState;
    }

    /**
     * @return mixed
     */
    public function getChatPresenceMessage()
    {
        return $this->chatPresenceMessage;
    }

    /**
     * @param mixed $chatPresenceMessage
     */
    public function setChatPresenceMessage($chatPresenceMessage)
    {
        $this->chatPresenceMessage = $chatPresenceMessage;
    }

    /**
     * @return mixed
     */
    public function getTelephonyState()
    {
        return $this->telephonyState;
    }

    /**
     * @param mixed $telephonyState
     */
    public function setTelephonyState($telephonyState)
    {
        $this->telephonyState = $telephonyState;
    }

    /**
     * @return mixed
     */
    public function getAvatarHash()
    {
        return $this->avatarHash;
    }

    /**
     * @param mixed $avatarHash
     */
    public function setAvatarHash($avatarHash)
    {
        $this->avatarHash = $avatarHash;
    }


}