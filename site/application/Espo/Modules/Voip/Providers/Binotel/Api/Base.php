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

abstract class Base
{
    protected $options;

    private $client;

    protected $clientClassName = 'BinotelApi4';

    /* defined in clientClass */
    protected $binotelApiUrl = null;

    /* defined in clientClass */
    protected $binotelApiVersion = null;

    public function __construct($options)
    {
        $this->options = $options;
    }

    protected function getOptions()
    {
        return $this->options;
    }

    public function getClient()
    {
        if (!isset($this->client)) {
            $this->loadApiClient();
        }

        return $this->client;
    }

    protected function loadApiClient()
    {
        $options = $this->getOptions();

        $classPath = 'application/Espo/Modules/Voip/Providers/Binotel/Api/Clients/'. $this->clientClassName .'.php';
        if (!file_exists($classPath)) {
            throw new Error('VoIP[Binotel]: API client is not found.');
        }

        require_once($classPath);
        $this->client = new \BinotelApi($options['key'], $options['secret'], $this->binotelApiUrl, $this->binotelApiVersion);
        if (!$options['useSsl']) {
            $this->client->disableSSLChecks();
        }
    }
}
