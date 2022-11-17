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

namespace Espo\Modules\Voip\Core\Helpers;

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtil;
use Espo\Modules\Voip\Core\Utils\PhoneNumber;
use Espo\Core\Utils\Util;

class Voip
{
    private $container;

    private $connectors = array();

    private $connectorDefaults = array();

    public function __construct(\Espo\Core\Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    protected function getEntityManager()
    {
        return $this->container->get('entityManager');
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    protected function getLanguage()
    {
        return $this->container->get('language');
    }

    protected function getConnectorManager($connector)
    {
        return $this->getContainer()->get('voipManager')->getConnectorManager($connector);
    }

    /**
     * Get the list of all voip lines
     *
     * @return array
     */
    public function getLineList($firstEmpty = true)
    {
        return $this->getListForField('lines', $firstEmpty);
    }

    /**
     * Get the list of all voip activeQueues
     *
     * @return array
     */
    public function getQueueList($firstEmpty = true)
    {
        return $this->getListForField('activeQueues', $firstEmpty);
    }

    /**
     * Get the list of all voip activeQueueNumbers
     *
     * @return array
     */
    public function getQueueNumberList($firstEmpty = true)
    {
        return $this->getListForField('activeQueueNumbers', $firstEmpty);
    }

    /**
     * Get the list of the $fieldName from all active connectors
     *
     * @param  string  $fieldName
     * @param  boolean $firstEmpty
     *
     * @return array
     */
    protected function getListForField($fieldName, $firstEmpty = true)
    {
        $metadata = $this->getMetadata();
        $language = $this->getLanguage();
        $connectorList = $metadata->get('app.voip.connectorList');

        $entityManager = $this->getEntityManager();

        $list = array();

        if ($firstEmpty) {
            $list[''] = '';
        }

        if (is_array($connectorList)) {
            foreach ($connectorList as $connector) {
                $entity = $entityManager->getEntity('Integration', ucfirst($connector));
                if (!empty($entity) && $entity->get('enabled')) {
                    $id = $entity->get('id');
                    $data = $entity->get('data');

                    $name = !empty($data->connectorName) ? $data->connectorName : $data->host;
                    if (isset($data->$fieldName)) {
                        foreach ($data->$fieldName as $itemName) {
                            $itemId = VoipUtil::combineFieldValue($itemName, $id);
                            $list[$itemId] = $name . " » " . $language->translate($itemId, 'voip'.ucfirst($fieldName), 'Integration');
                        }
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Get connector name for an entity
     *
     * @param  Entity $entity
     *
     * @return string|null
     */
    public function getConnectorByEntity(Entity $entity)
    {
        if (!empty($entity)) {
            $connector = $entity->get('connector');
            if (!empty($connector)) {
                return $connector;
            }

            $assignedUserId = $entity->get('assignedUserId');
            if (!empty($assignedUserId)) {
                $assignedUser = $this->getEntityManager()->getEntity('User', $assignedUserId);
                if (!empty($assignedUser)) {
                    return $assignedUser->get('voipConnector');
                }
            }
        }

        return $this->getUser()->get('voipConnector');
    }

    /**
     * Get connector entity by name
     *
     * @param  string $connector
     *
     * @return \Espo\Entitites\Integration|null
     */
    protected function getConnectorEntity($connector)
    {
        if (!isset($this->connectors[$connector])) {
            $this->connectors[$connector] = $this->getEntityManager()->getEntity('Integration', $connector);
        }

        return $this->connectors[$connector];
    }

    /**
     * Get connector default data
     *
     * @param  string $optionName
     *
     * @return mixed
     */
    protected function getConnectorDefaultValue($optionName)
    {
        if (!isset($this->connectorDefaults[$optionName])) {
            $this->connectorDefaults[$optionName] = $this->getMetadata()->get('app.voip.defaults.' . $optionName);
        }

        return $this->connectorDefaults[$optionName];
    }

    /**
     * Get connector option by name
     *
     * @param  string $optionName
     * @param  string $connector
     *
     * @return mixed
     */
    protected function getConnectorOption($optionName, $connector = null)
    {
        if (isset($connector)) {
            $connectorEntity = $this->getConnectorEntity($connector);
            if (isset($connectorEntity)) {
                $data = $connectorEntity->get('data');
                if (isset($data->$optionName)) {
                    return $data->$optionName;
                }
            }
        }

        return $this->getConnectorDefaultValue($optionName);
    }

    /**
     * Find entities by a $phoneNumber
     *
     * @param  string $phoneNumber
     * @param  string $connector
     *
     * @return array
     */
    public function findEntitiesByPhone($phoneNumber, $connector, $searchInNationalFormat = false)
    {
        $entityManager = $this->getEntityManager();
        $pdo = $entityManager->getPDO();

        $originalPhoneNumber = $phoneNumber;

        $connectorManager = $this->getConnectorManager($connector);

        $searchFormat = $searchInNationalFormat ? PhoneNumber::NATIONAL_SEARCH_FORMAT : PhoneNumber::SEARCH_FORMAT;
        $phoneNumber = $connectorManager->formatPhoneNumber($phoneNumber, $searchFormat);

        $sqlLength = "";
        if (strlen($phoneNumber) < $this->getConnectorDefaultValue('phoneNumberLength')) {
            $sqlLength = " AND LENGTH(phone_number.numeric) = " . strlen($phoneNumber);
        }

        $entitiesToSearch = $this->getConnectorOption('permittedEntities', $connector);
        $displayRelatedAccount = $this->getConnectorOption('displayRelatedAccount', $connector);

        $sqlLeftJoin = '';
        $deletedEntitiesSql = array();
        if (!empty($entitiesToSearch)) {
            foreach ($entitiesToSearch as $entityName) {
                $lowerEntityName = Util::toUnderScore($entityName);
                $sqlLeftJoin .= " LEFT JOIN `".$lowerEntityName."` ON ".$lowerEntityName.".id = entity_phone_number.entity_id AND entity_phone_number.entity_type = '".$entityName."'";
                $deletedEntitiesSql[] = " ($lowerEntityName.deleted = 0 AND entity_phone_number.entity_type = ".$pdo->quote($entityName).") ";
            }
        }

        $sqlEntityType = "";
        if (!empty($entitiesToSearch)) {
            $entitiesToSearchQuoted = [];
            foreach ($entitiesToSearch as $entityName) {
                $entitiesToSearchQuoted[] = $pdo->quote($entityName);
            }

            $sqlEntityType = " AND entity_phone_number.entity_type IN (".implode(", ", $entitiesToSearchQuoted).") ";
        }

        $sqlDeletedEntities = empty($deletedEntitiesSql) ? "" : " AND (".implode(" OR ", $deletedEntitiesSql).") ";

        /* phone number search sql */
        $sqlPhoneNumberSearch = "phone_number.numeric LIKE ". $pdo->quote('%' . $phoneNumber);
        if ($phoneNumber !== ltrim($phoneNumber, '0')) {
            $sqlPhoneNumberSearch = "(". $sqlPhoneNumberSearch ." OR phone_number.numeric LIKE ". $pdo->quote('%' . ltrim($phoneNumber, '0')) ." )";
        }

        $sql = "
            SELECT entity_phone_number.entity_id, entity_phone_number.entity_type
            FROM `entity_phone_number`
            JOIN `phone_number` ON phone_number.id = entity_phone_number.phone_number_id AND phone_number.deleted = 0
            ".$sqlLeftJoin."
            WHERE
            entity_phone_number.deleted = 0 AND
            ". $sqlPhoneNumberSearch ."
            ". $sqlEntityType ."
            ". $sqlLength ."
            ". $sqlDeletedEntities ."
            ORDER BY entity_phone_number.primary DESC
        ";

        $sth = $pdo->prepare($sql);
        $sth->execute();

        $entities = array();
        if ($rows = $sth->fetchAll()) {
            foreach ($rows as $row) {
                $entity = $entityManager->getEntity($row['entity_type'], $row['entity_id']);
                if (isset($entity)) {
                    $entities[ $row['entity_type'] ] [ $row['entity_id'] ] = array(
                        'name' => $entity->get('name'),
                    );

                    if ($displayRelatedAccount) {
                        switch ($entity->getEntityType()) {
                            case 'Contact':
                                $accountIds = [];

                                if ($entity->hasField('accountId') && $entity->get('accountId')) {
                                    $accountIds[] = $entity->get('accountId');
                                }

                                if ($entity->hasRelation('accounts')) {
                                    $entity->loadLinkMultipleField('accounts');
                                    if ($entity->get('accountsIds')) {
                                        $accountIds = array_merge($accountIds, $entity->get('accountsIds'));
                                    }
                                }

                                if (!empty($accountIds)) {
                                    $accountEntities = [];

                                    foreach (array_unique($accountIds) as $accountId) {
                                        $account = $entityManager->getEntity('Account', $accountId);
                                        if ($account) {
                                            $accountEntities[$account->get('id')] = array(
                                                'name' => $account->get('name'),
                                            );
                                        }
                                    }

                                    $entities['Account'] = $accountEntities;
                                }
                                break;
                        }
                    }

                }
            }
        }

        if (!$searchInNationalFormat && empty($entities)) {
            $entities = $this->findEntitiesByPhone($originalPhoneNumber, $connector, true);
        }

        $normalizedEntities = [];
        foreach ($this->getConnectorOption('permittedEntities', $connector) as $entityName) {
            if (isset($entities[$entityName])) {
                $normalizedEntities[$entityName] = $entities[$entityName];
            }
        }

        return $normalizedEntities;
    }

    /**
     * Find userId by $userExtension
     *
     * @param  string $userExtension
     * @param  string $connector
     * @return string
     */
    public function findUser($userExtension, $connector)
    {
        $entityManager = $this->getEntityManager();
        $pdo = $entityManager->getPDO();

        $connectorManager = $this->getConnectorManager($connector);

        $phoneSearchList = [
            $userExtension,
            ltrim($userExtension, '0'),
            $connectorManager->doPhoneNumberReplacement($userExtension),
        ];

        $quotedPhoneSearchList = $this->getQuotedList($phoneSearchList, true);
        $phoneSearchListSql = (count($quotedPhoneSearchList) > 1) ? "IN (". implode(", ", $quotedPhoneSearchList) .")" : "= " . $quotedPhoneSearchList[0];

        $sql = "
            SELECT user.id
            FROM `user`
            WHERE
            user.deleted = 0 AND user.voip_user ". $phoneSearchListSql ."
            LIMIT 0, 1
        ";

        $sth = $pdo->prepare($sql);
        $sth->execute();

        if ($row = $sth->fetch()) {
            return $row['id'];
        }

        $phoneSearchList[] = $connectorManager->formatPhoneNumber($userExtension, PhoneNumber::SEARCH_FORMAT);
        $phoneSearchList[] = $connectorManager->formatPhoneNumber($userExtension, PhoneNumber::NATIONAL_SEARCH_FORMAT);

        $quotedPhoneSearchList = $this->getQuotedList($phoneSearchList, true);
        $phoneSearchListSql = (count($quotedPhoneSearchList) > 1) ? "IN (". implode(", ", $quotedPhoneSearchList) .")" : "= " . $quotedPhoneSearchList[0];

        $GLOBALS['log']->debug('VoIP: User identification by the phone numbers: ' . print_r($quotedPhoneSearchList, true) . '.');

        $searchQuery = "phone_number.numeric ". $phoneSearchListSql;
        if ($this->isSipPhoneNumberExists($phoneSearchList, $connector)) {
            $searchQuery = "(phone_number.name ". $phoneSearchListSql . " OR phone_number.numeric ". $phoneSearchListSql . ")";
        }

        //find by user phone number
        $sql = "
            SELECT entity_phone_number.entity_id
            FROM `phone_number`
            JOIN `entity_phone_number` on entity_phone_number.phone_number_id = phone_number.id AND entity_phone_number.deleted = 0 AND entity_phone_number.entity_type = 'User'
            WHERE
            ". $searchQuery ."
            AND phone_number.deleted = 0
            LIMIT 0, 1
        ";

        $sth = $pdo->prepare($sql);
        $sth->execute();

        if ($row = $sth->fetch()) {
            return $row['entity_id'];
        }
    }

    /**
     * Get prefered user list based on entities
     *
     * @param  Entity $entity
     * @param  array  $returns
     *
     * @return array
     */
    public function getPreferedUserList(Entity $entity, $returns = array())
    {
        if (!empty($entity) && $entity->hasField('entities')) {
            $entities = $entity->get('entities');
            if (empty($entities)) {
                return $returns;
            }

            $preferedUserList = array();

            foreach ($entities as $entityName => $entityList) {
                foreach ($entityList as $recordId => $recordData) {
                    $record = $this->getEntityManager()->getEntity($entityName, $recordId);
                    if (isset($record)) {
                        $assignedUserId = $record->get('assignedUserId');
                        if (isset($assignedUserId) && !in_array($assignedUserId, $preferedUserList)) {
                            $preferedUserList[] = $assignedUserId;
                        }
                    }
                }
            }

            return $preferedUserList;
        }

        return $returns;
    }

    public function getQuotedList($list, $onlyUnique = false)
    {
        $pdo = $this->getEntityManager()->getPDO();

        if ($onlyUnique) {
            $list = array_unique($list);
        }

        foreach ($list as &$value) {
            $value = $pdo->quote($value);
        }

        return $list;
    }

    protected function isSipPhoneNumberExists(array $phoneNumberList, $connector)
    {
        $connectorManager = $this->getConnectorManager($connector);

        foreach ($phoneNumberList as $phoneNumber) {
            if ($connectorManager->isSipPhoneNumber($phoneNumber)) {
                return true;
            }
        }

        return false;
    }
}
