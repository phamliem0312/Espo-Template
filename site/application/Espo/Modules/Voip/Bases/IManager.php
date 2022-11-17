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

namespace Espo\Modules\Voip\Bases;

interface IManager
{
    /**
     * Event listener for crontab
     *
     * @return void
     */
    public function startEventListener();

    /**
     * Event listener service
     *
     * @return void
     */
    public function startServiceEventListener();

    /**
     * Test connection
     *
     * @param  array  $data
     *
     * @return boolean
     */
    public function testConnection(array $data);

    /**
     * Click to call action
     *
     * @param  array   $data
     *
     * @return string  external ID
     */
    public function dial(array $data);

    /**
     * Handle request from VoIP server / service
     *
     * @param  array  $data
     * @param  array  $requestData
     *
     * @return void | mixed
     */
    public function runWebhook(array $data, $request);

    /**
     * Handle webhook event
     *
     * @param  array  $eventData
     *
     * @return \Espo\Modules\Voip\Entities\VoipEvent
     */
    public function handleEvent(array $eventData = null);

    /**
     * Handle a message
     *
     * @param  array  $messageData
     *
     * @return \Espo\Modules\Voip\Entities\VoipMessage
     */
    public function handleMessage(array $messageData = null);

    /**
     * Send a message (SMS / MMS)
     *
     * @param  string       $fromNumber
     * @param  string       $toNumber
     * @param  string       $text
     * @param  array | null $options
     *
     * @return string       external ID
     */
    public function sendMessage($fromNumber, $toNumber, $text = null, array $options = null);
}
