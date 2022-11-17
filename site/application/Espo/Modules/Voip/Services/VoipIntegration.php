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

namespace Espo\Modules\Voip\Services;

class VoipIntegration extends \Espo\Core\Services\Base
{
    protected function init()
    {
        parent::init();
        $this->addDependency('container');
    }

    protected function getContainer()
    {
        return $this->getInjection('container');
    }

    public function voipIntegrationJob($jobData)
    {
        $helper = new \Espo\Modules\Voip\Core\Helper($this->getContainer());
        $info = $helper->getInfo();

        if (!empty($info)) {
            $data = array(
                'id' => @$info['lid'],
                'name' => @$info['name'],
                'site' => $this->getConfig()->get('siteUrl'),
                'version' => @$info['version'],
                'installedAt' => @$info['installedAt'],
                'updatedAt' => @$info['created_at'],
                'applicationName' => $this->getConfig()->get('applicationName'),
                'espoVersion' => $this->getConfig()->get('version'),
            );

            $result = $this->validate($data);
        }
    }

    protected function validate(array $data)
    {
        if (function_exists('curl_version')) {
            $ch = curl_init();

            $payload = json_encode($data);
            curl_setopt($ch, CURLOPT_URL, base64_decode('aHR0cHM6Ly9zLmVzcG9jcm0uY29tL2xpY2Vuc2Uv'));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 60);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen($payload)
            ]);

            $result = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode === 200) {
                return $result;
            }
        }
    }
}