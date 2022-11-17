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

namespace Espo\Modules\Voip\Providers\Binotel;

class CidManager extends \Espo\Modules\Voip\Bases\CidManager
{
    public function getCallerName($phoneNumber, array $params = [])
    {
        $entityList = $this->findEntitiesByPhone($phoneNumber);

        if (!empty($entityList)) {
            $entity = $this->getMainEntity($entityList);

            $assignedUser = null;

            if ($entity->get('assignedUserId')) {
                $assignedUser = $this->getEntityManager()->getEntity('User', $entity->get('assignedUserId'));
            }

            $labelName = !empty($entityList['Account']) ? 'cidNameWithAccount' : 'cidName';
            $cidName = $this->getVoipEventHelper()->parseLabel(
                $phoneNumber,
                $assignedUser,
                $entityList,
                $labelName,
                'callNames',
                'VoipEvent'
            );

            $returnData = array(
                'name' => $cidName,
                'linkToCrmUrl' => $this->normalizeLink('#' . $entity->getEntityName() . '/view/' . $entity->get('id')),
                'linkToCrmTitle' => $this->getLanguage()->translate('goToCrm', 'labels', 'VoipEvent'),
            );

            if ($assignedUser) {
                if ($assignedUser->get('voipUser')) {
                    $returnData['assignedToEmployeeNumber'] = $assignedUser->get('voipUser');
                } else if ($assignedUser->get('emailAddress')) {
                    $returnData['assignedToEmployeeEmail'] = $assignedUser->get('emailAddress');
                }
            }

            return array(
                'customerData' => $returnData,
            );
        }

        $this->getLanguage()->setLanguage($this->getConfig()->get('language'));

        $entityList = $this->getConnectorData()->permittedEntities;
        $entityName = reset($entityList);

        return array(
            'customerData' => array(
                'linkToCrmUrl' => $this->normalizeLink('#'.$entityName.'/create'),  //todo: specify the phone number
                'linkToCrmTitle' => $this->getLanguage()->translate('Create ' . $entityName, 'labels', $entityName),
            )
        );
    }
}
