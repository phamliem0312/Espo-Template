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

namespace Espo\Modules\Voip\Core\Wrappers;

class Request
{
    private $slimRequest;

    public function __construct($slimRequest)
    {
        $this->slimRequest = $slimRequest;
    }

    public function getQueryParam(string $name)
    {
        return $this->slimRequest->get($name);
    }

    public function getServerParam(string $name)
    {
        if (isset($_SERVER[$name])) {
            return $_SERVER[$name];
        }
    }

    public function getContentType()
    {
        return $this->slimRequest->getContentType();
    }
}
