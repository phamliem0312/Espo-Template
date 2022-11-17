<?php

namespace Starface\Response;

class CallState
{
    protected $callerNumber;
    protected $calledNumber;
    protected $timestamp;
    protected $id;
    protected $groupID;
    protected $calledName;
    protected $state;
    protected $callerName;

    public function __construct(array $data = null)
    {
        if (isset($data)) {
            foreach ($data as $name => $value) {
                $this->$name = $value;
            }
        }
    }

    /**
     * @return mixed
     */
    public function getCallerNumber()
    {
        return $this->callerNumber;
    }

    /**
     * @param mixed $callerNumber
     * @return $this
     */
    public function setCallerNumber($callerNumber)
    {
        $this->callerNumber = $callerNumber;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCalledNumber()
    {
        return $this->calledNumber;
    }

    /**
     * @param mixed $calledNumber
     * @return $this
     */
    public function setCalledNumber($calledNumber)
    {
        $this->calledNumber = $calledNumber;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getTimestamp()
    {
        return $this->timestamp;
    }

    /**
     * @param mixed $timestamp
     * @return $this
     */
    public function setTimestamp($timestamp)
    {
        $this->timestamp = $timestamp;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param mixed $id
     * @return $this
     */
    public function setId($id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getGroupID()
    {
        return $this->groupID;
    }

    /**
     * @param mixed $groupID
     * @return $this
     */
    public function setGroupID($groupID)
    {
        $this->groupID = $groupID;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCalledName()
    {
        return $this->calledName;
    }

    /**
     * @param mixed $calledName
     * @return $this
     */
    public function setCalledName($calledName)
    {
        $this->calledName = $calledName;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getState()
    {
        return $this->state;
    }

    /**
     * @param mixed $state
     * @return $this
     */
    public function setState($state)
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return mixed
     */
    public function getCallerName()
    {
        return $this->callerName;
    }

    /**
     * @param mixed $callerName
     * @return $this
     */
    public function setCallerName($callerName)
    {
        $this->callerName = $callerName;
        return $this;
    }
}
