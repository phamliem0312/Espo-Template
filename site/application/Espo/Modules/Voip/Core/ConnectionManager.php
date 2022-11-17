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

use Espo\Core\Exceptions\Forbidden;

class ConnectionManager extends BaseManager
{
    protected function getLanaguage()
    {
        return $this->getContainer()->get('language');
    }

    protected function getFileManager()
    {
        return $this->getContainer()->get('fileManager');
    }

    protected function getEntityManager()
    {
        return $this->getContainer()->get('entityManager');
    }

    /**
     * Add a new connector based on $parentConnector
     *
     * @param string $parentConnector
     *
     * @return string|null Connector mame
     */
    public function addConnector($parentConnector)
    {
        $parentConnector = ucfirst($parentConnector);

        $metadata = $this->getMetadata();
        $language = $this->getLanaguage();

        $data = $metadata->get('integrations.' . $parentConnector);
        $data['parent'] = $parentConnector;
        $data['isCustom'] = true;

        $nameIndex = $this->generateNameIndex($parentConnector);
        $connectorName = $parentConnector . $nameIndex;

        //save integrations metadata
        $metadata->set('integrations', $connectorName, $data);

        //add this coonnector to the connectorList
        $this->addToConnectorList($connectorName, false);
        $this->addToPhoneNumberReplacement($connectorName, $parentConnector);

        $result = $metadata->save();

        //save label translation
        $label = $language->translate($parentConnector, 'titles', 'Integration');
        $connectorLabel = $label . ' ' .$nameIndex;
        $language->set('Integration', 'titles', $connectorName, $connectorLabel);
        $result &= $language->save();

        if ($result) {
            $this->getContainer()->get('dataManager')->clearCache();
            return $connectorName;
        }
    }

    /**
     * Remove connector
     *
     * @param  string $name
     *
     * @return string|null Parent connector mame
     */
    public function removeConnector($connectorName)
    {
        $connectorName = ucfirst($connectorName);

        $metadata = $this->getMetadata();
        $language = $this->getLanaguage();
        $fileManager = $this->getFileManager();

        $connectorData = $metadata->get('integrations.' . $connectorName);

        if (!isset($connectorData['parent'])) {
            throw new Forbidden('You cannot delete the primary connector.');
        }

        //delete integrations metadata
        $metadata->delete('integrations', $connectorName, array_keys($connectorData));

        //remove this coonnector from the connectorList
        $this->deleteFromConnectorList($connectorName, false);
        $this->deleteFromPhoneNumberReplacement($connectorName);

        $result = $metadata->save();

        //delete label translation
        $language->delete('Integration', 'titles', $connectorName);
        $result &= $language->save();

        if ($result) {
            $this->getContainer()->get('dataManager')->clearCache();
            return $connectorData['parent'];
        }
    }

    /**
     * Generate a new index for connector
     *
     * @param  string $connectorName
     *
     * @return string
     */
    protected function generateNameIndex($connectorName)
    {
        $integrationList = $this->getMetadata()->get('integrations');
        for ($i=2; $i < 1000; $i++) {
            $generatedName = $connectorName . $i;
            if (!isset($integrationList[$generatedName])) {
                return $i;
            }
        }

        return uniqid();
    }

    /**
     * Add a new connector to the app.void.connectorList
     *
     * @param string $connectorName
     *
     * @return void
     */
    public function addToConnectorList($connectorName, $isSave = true)
    {
        $metadata = $this->getMetadata();

        $connectorList = $metadata->get('app.voip.connectorList', []);
        if (in_array($connectorName, $connectorList)) {
            return;
        }

        $connectorList[] = $connectorName;
        $metadata->set('app', 'voip', [
            'connectorList' => array_values($connectorList),
        ]);

        if ($isSave) {
            $metadata->save();
        }
    }

    /**
     * Remove the connector from the app.void.connectorList
     *
     * @param string $connectorName
     *
     * @return void
     */
    public function deleteFromConnectorList($connectorName, $isSave = true)
    {
        $metadata = $this->getMetadata();

        $connectorList = $metadata->get('app.voip.connectorList', []);

        $isChanged = false;

        foreach ($connectorList as $key => $value) {
            if ($connectorName == $value) {
                unset($connectorList[$key]);
                $isChanged = true;
                break;
            }
        }

        if (!$isChanged) {
            return;
        }

        $metadata->set('app', 'voip', [
            'connectorList' => array_values($connectorList),
        ]);

        if ($isSave) {
            $metadata->save();
        }
    }

    protected function addToPhoneNumberReplacement($connectorName, $parentConnector)
    {
        $metadata = $this->getMetadata();

        $phoneNumberReplacement = $metadata->get('app.voip.phoneNumberReplacement');
        if (!isset($phoneNumberReplacement[$parentConnector])) {
            return;
        }

        $phoneNumberReplacement[$connectorName] = $phoneNumberReplacement[$parentConnector];
        $metadata->set('app', 'voip', [
            'phoneNumberReplacement' => $phoneNumberReplacement,
        ]);
    }

    protected function deleteFromPhoneNumberReplacement($connectorName)
    {
        $metadata = $this->getMetadata();

        $phoneNumberReplacement = $metadata->get('app.voip.phoneNumberReplacement');
        if (!isset($phoneNumberReplacement[$connectorName])) {
            return;
        }

        $metadata->delete('app', 'voip', 'phoneNumberReplacement.' . $connectorName);
    }

    /**
     * Get active connector name list
     *
     * @return array
     */
    public function getActiveList()
    {
        $metadata = $this->getMetadata();
        $connectorList = $metadata->get('app.voip.connectorList');

        $entityManager = $this->getEntityManager();

        $list = array();
        if (is_array($connectorList)) {
            foreach ($connectorList as $connector) {
                $entity = $entityManager->getEntity('Integration', ucfirst($connector));
                if (!empty($entity) && $entity->get('enabled')) {
                    $id = $entity->get('id');
                    $data = $entity->get('data');
                    $list[$id] = !empty($data->connectorName) ? $data->connectorName : $data->host;
                }
            }
        }

        return $list;
    }

    /**
     * Get active connector list searched by name
     *
     * @param  string $connectorName
     *
     * @return array
     */
    public function getActiveListByProviderName($connectorName)
    {
        $metadata = $this->getMetadata();
        $activeList = $this->getActiveList();

        $list = array();
        foreach ($activeList as $connectorId => $connector) {
            $connectorData = $metadata->get('integrations.' . $connectorId);
            if ($connectorId == $connectorName || (isset($connectorData['parent']) && $connectorData['parent'] == $connectorName)) {
                $list[$connectorId] = $connector;
            }
        }

        return $list;
    }

    /* DEPRECATED: use getActiveListByProviderName() */
    public function getActiveListByConnectorName($connectorName)
    {
        return $this->getActiveListByProviderName($connectorName);
    }
}
