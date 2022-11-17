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

namespace Espo\Modules\Voip\Providers\Binotel\Api;

use Espo\Core\Exceptions\Error;

class Binotel3 extends Base
{
    protected $clientClassName = 'BinotelApi3';

    /**
     * Инициирование двустороннего звонка с внутренней линией и внешним номером
     *
     * @param  string $callerId - внутренний номер сотрудника (первый участник разговора)
     * @param  string $phoneNumber - телефонный номер куда нужно позвонить (второй участник разговора)
     *
     * @return string
     */
    public function callsExtToPhone($callerId, $phoneNumber)
    {
        $client = $this->getClient();

        $result = $client->sendRequest('calls/internal-number-to-external-number', array(
            'internalNumber' => $callerId,
            'externalNumber' => $phoneNumber,
        ));

        if (isset($result) && $result['status'] === 'success') {
            return $result['generalCallID'];
        }

        $GLOBALS['log']->error('VoIP ['.$this->clientClassName.']: Error for the action [calls/internal-number-to-external-number], response: '. var_export($result, true));
        throw new Error('Error response from Binotel server');
    }
}
