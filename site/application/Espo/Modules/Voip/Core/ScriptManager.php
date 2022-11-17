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

namespace Espo\Modules\Voip\Core;

class ScriptManager extends BaseManager
{
    protected function getFileManager()
    {
        return $this->container->get('fileManager');
    }

    protected function getProviderList()
    {
        $fileManager = $this->getFileManager();

        return $fileManager->getFileList('application/Espo/Modules/Voip/Providers', false, '', false);
    }

    protected function runScripts($type)
    {
        $providerList = $this->getProviderList();

        foreach ($providerList as $provider) {
            $className = '\\Espo\\Modules\\Voip\\Providers\\'.$provider.'\\Scripts\\' . $type;
            if (class_exists($className)) {
                $class = new $className();
                $class->run($this->getContainer());
            }
        }
    }

    public function runAfterInstall()
    {
        $this->runScripts('AfterInstall');
    }

    public function runBeforeUninstall()
    {
        $this->runScripts('BeforeUninstall');
    }
}
