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

abstract class CidManager extends Base
{
    protected function getConfig()
    {
        return $this->getContainer()->get('config');
    }

    protected function getVoipHelper()
    {
        return $this->getContainer()->get('voipHelper');
    }

    protected function getVoipEventHelper()
    {
        return $this->getContainer()->get('voipEventHelper');
    }

    protected function getLanguage()
    {
        return $this->getContainer()->get('language');
    }

    /**
     * Get Caller ID for incoming call
     *
     * @param  string $phoneNumber
     * @param  array $params
     *
     * @return mixed
     */
    public function getCallerName($phoneNumber, array $params = [])
    {
        $entityList = $this->findEntitiesByPhone($phoneNumber);

        if (!empty($entityList)) {

            $labelName = !empty($entityList['Account']) ? 'cidNameWithAccount' : 'cidName';

            return $this->getVoipEventHelper()->parseLabel(
                $phoneNumber,
                null,
                $entityList,
                $labelName,
                'callNames',
                'VoipEvent'
            );
        }

        $this->getLanguage()->setLanguage($this->getConfig()->get('language'));
        return $this->getLanguage()->translate('Unknown', 'labels', 'VoipEvent');
    }

    protected function findEntitiesByPhone($phoneNumber)
    {
        $connector = $this->getConnector();
        return $this->getVoipHelper()->findEntitiesByPhone($phoneNumber, $connector);
    }

    protected function getMainEntity(array $entityList)
    {
        foreach ($entityList as $entityName => $recordlist) {
            $entityId = key($recordlist);
            if ($entityId) {
                return $this->getEntityManager()->getEntity($entityName, $entityId);
            }
        }
    }

    protected function normalizeLink($link)
    {
        return $this->getConfig()->get('siteUrl') . $link;
    }
}
