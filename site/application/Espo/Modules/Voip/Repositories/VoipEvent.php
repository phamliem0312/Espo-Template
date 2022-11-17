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

namespace Espo\Modules\Voip\Repositories;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Utils\Util;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtil;
use Espo\Modules\Voip\Core\Utils\PhoneNumber;

class VoipEvent extends \Espo\Core\ORM\Repositories\RDB
{
    private $options = array();

    private $defaults = array();
    /**
     * Permitted modified fields for VoipEvent, pair of [input name]:[VoipEvent field name]
     *
     * @var array
     */
    protected $permittedModifiedFields = array(
        'type' => 'type',
        'callId' => 'callId',
        'status' => 'status',
        'dateStart' => 'dateStart',
        'dateEnd' => 'dateEnd',
    );

    protected $endedCallStatusMap = array(
        VoipEventEntity::ACTIVE => VoipEventEntity::ANSWERED,
        VoipEventEntity::DIALING => VoipEventEntity::NO_ANSWER,
        VoipEventEntity::RINGING => VoipEventEntity::MISSED,
    );

    /**
     * Voip settings
     *
     * @var array
     */
    private $voipSettings = array();

    protected $currentEntity;

    protected function init()
    {
        $this->addDependency('user');
        $this->addDependency('metadata');
        $this->addDependency('language');
        $this->addDependency('voipEventHelper');
        $this->addDependency('voipHelper');
        $this->addDependency('voipManager');
        $this->addDependency('VoipRecordingManager');
    }

    protected function getUser()
    {
        return $this->getInjection('user');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    protected function getLanaguage()
    {
        return $this->getInjection('language');
    }

    protected function getVoipEventHelper()
    {
        return $this->getInjection('voipEventHelper');
    }

    protected function getVoipHelper()
    {
        return $this->getInjection('voipHelper');
    }

    protected function getVoipRecordingManager()
    {
        return $this->getInjection('VoipRecordingManager');
    }

    protected function getConnectorManager($connector = null)
    {
        if (!isset($connector)) {
            $connector = $this->getCurrentConnector();
        }

        if (!isset($this->options['connectorManager'][$connector])) {
            $this->options['connectorManager'][$connector] = $this->getInjection('voipManager')->getConnectorManager($connector);
        }

        return $this->options['connectorManager'][$connector];
    }

    protected function getCurrentEntity()
    {
        return $this->currentEntity;
    }

    protected function getDefaultValue($option)
    {
        if (!isset($this->defaults[$option])) {
            $this->defaults[$option] = $this->getMetadata()->get('app.voip.defaults.' . $option);
        }

        return $this->defaults[$option];
    }

    protected function getVoipParam($name, $connector = null, $returns = null)
    {
        $currentEntity = $this->getCurrentEntity();

        if (!isset($returns)) {
            $returns = $this->getDefaultValue($name);
        }

        if (empty($currentEntity) && empty($connector)) {
            return $returns;
        }

        if (empty($connector)) {
            $connector = $this->getCurrentConnector();
            if (empty($connector)) {
                return $returns;
            }
        }

        if (!isset($this->voipSettings[$connector])) {
            $this->voipSettings[$connector] = $this->getEntityManager()->getEntity('Integration', $connector)->get('data');
        }

        if (isset($this->voipSettings[$connector]->$name)) {
            return $this->voipSettings[$connector]->$name;
        }

        return $returns;
    }

    protected function getCurrentConnector()
    {
        $currentEntity = $this->getCurrentEntity();
        if (!empty($currentEntity)) {
            $connector = $currentEntity->get('connector');
            if (!empty($connector)) {
                return $connector;
            }

            $assignedUserId = $currentEntity->get('assignedUserId');
            if (!empty($assignedUserId)) {
                $assignedUser = $this->getEntityManager()->getEntity('User', $assignedUserId);
                if (!empty($assignedUser)) {
                    return $assignedUser->get('voipConnector');
                }
            }
        }

        $user = $this->getUser();
        return $user->get('voipConnector');
    }

    protected function beforeSave(Entity $entity, array $options = array())
    {
        $this->currentEntity = $entity;

        $assignedUserId = $entity->get('assignedUserId');
        if ((empty($assignedUserId) || $entity->isAttributeChanged('userExtension')) && $entity->get('userExtension')) {

            $assignedUserId = $this->findUser($entity->get('userExtension'));
            $entity->set('assignedUserId', $assignedUserId);
        }

        if (!$entity->get('connector') && $entity->isAttributeChanged('assignedUserId')) {
            $assignedUser = $this->getEntityManager()->getEntity('User', $entity->get('assignedUserId'));
            if (!empty($assignedUser)) {
                $entity->set('connector', $assignedUser->get('voipConnector'));
            }
        }

        if ($entity->isNew()) {
            $assignedUserId = $entity->get('assignedUserId');
            if (!empty($assignedUserId) && $this->isDisabledCallNotification($assignedUserId)) {
                $entity->set('processed', true);
            }

            $entity->set('originalPhoneNumber', $entity->get('phoneNumber'));
        }

        if ($entity->isAttributeChanged('phoneNumber')) {
            $entity->set('originalPhoneNumber', $entity->get('phoneNumber'));

            $connectorManager = $this->getConnectorManager();

            $line = $entity->get('line');
            if (empty($line)) {
                $entity->set('line', $connectorManager->formatPhoneNumber($entity->get('phoneNumber'), PhoneNumber::LINE_FORMAT));
            }

            $entity->set('phoneNumber', $connectorManager->formatPhoneNumber($entity->get('phoneNumber'), PhoneNumber::DISPLAY_FORMAT));
            $entity->set('entities', $this->findEntities($entity->get('phoneNumber')));
        }

        if (
            $entity->isAttributeChanged('ready')
            || $entity->isAttributeChanged('hidden')
            || $entity->isAttributeChanged('assignedUserId')
        ) {
            $this->getVoipEventHelper()->autoSaveCall($entity, $this->getVoipParam('autoSaveCall'));
        }

        parent::beforeSave($entity, $options);
    }

    protected function afterSave(Entity $entity, array $options = array())
    {
        $this->currentEntity = $entity;

        if ($entity->isAttributeChanged('status')) {
            $this->handleRelatedCall($entity);
        }

        parent::afterSave($entity, $options);
    }

    protected function handleRelatedCall(Entity $entity)
    {
        if (!$entity->get('callId')) {
            return;
        }

        $call = $this->getEntityManager()->getEntity('Call', $entity->get('callId'));
        if (!$call) {
            return;
        }

        $save = false;

        if (!$call->get('voipUniqueid')) {
            $call->set('voipUniqueid', $entity->get('uniqueid'));
            $save = true;
        }

        switch ($entity->get('status')) {
            case VoipEventEntity::ANSWERED:
                $call->set('dateEnd', $entity->getCallDateEnd());
                if (!$call->get('voipRecording')) {
                    $recording = $this->getVoipRecordingManager()->getClass($entity->get('connector'));
                    if (!empty($recording)) {
                        $recordingUrl = $recording->generateUrl($entity);
                        if (!empty($recordingUrl)) {
                            $call->set('voipRecording', $recordingUrl);
                        }
                    }
                }
                $save = true;
                break;
        }

        if ($entity->getCallStatus() != $call->get('status')) {
            $call->set('status', $entity->getCallStatus());
            $save = true;
        }

        if ($save) {
            $this->getEntityManager()->saveEntity($call);
        }
    }

    /**
     * Get Active Notification Events
     *
     * @return array
     */
    public function getNotificationList(VoipEventEntity $voipEvent = null)
    {
        $dataList = [];

        if ($voipEvent) {

            if (!$voipEvent->get('assignedUserId')) {
                return $dataList;
            }

            $assignedUser = $this->getEntityManager()->getEntity('User', $voipEvent->get('assignedUserId'));

            if ($assignedUser
                && $assignedUser->get('voipNotifications')
                && $voipEvent->get('processed') == false
                && $voipEvent->get('ready') == true
                && $voipEvent->get('hidden') == false
                && $voipEvent->get('system') == false
                && $voipEvent->get('deleted') == false
            ) {
                $list = (object) [
                    $voipEvent
                ];
            } else {
                return $dataList;
            }

        } else {

            $currentUser = $this->getUser();
            if (!$currentUser->get('voipNotifications')) {
                return $dataList;
            }

            $list = $this->where([
                'processed' => false,
                'ready' => true,
                'hidden' => false,
                'system' => false,
                'deleted' => false,
                'assignedUserId' => $currentUser->get('id'),
                //'isSubmitted' => false,
            ])->order('dateStart', 'ASC')->find();

        }

        foreach ($list as $voipEvent) {
            $entityNotificationData = $this->normalizeEntityNotificationData($voipEvent);
            if (!empty($entityNotificationData)) {
                $dataList[] = $entityNotificationData;
            }
        }

        return $dataList;
    }

    protected function normalizeEntityNotificationData(VoipEventEntity $voipEvent)
    {
        $returnFieldList = array(
            'id',
            'uniqueid',
            'type',
            'status',
            'phoneNumber',
            'dateStart',
            'dateEnd',
            'entities',
            'callId',
            'connector',
            'line',
            'assignedUserId',
            'data',
        );

        if (!$voipEvent->get('connector')) {
            return [];
        }

        $connector = $voipEvent->get('connector');

        $returnData = array_intersect_key($voipEvent->toArray(), array_flip($returnFieldList));

        $returnData['quickCreateEntities'] = $this->getVoipParam('quickCreateEntities', $connector);
        $returnData['lineId'] = VoipUtil::combineFieldValue($returnData['line'], $connector);

        if (empty($returnData['entities'])) {
            $defaultList = $this->getVoipParam('permittedEntities', $connector);
            $returnData['entities'] = (object) array_fill_keys($defaultList, (object) []);
        }

        $returnData = array_merge($returnData, $this->getVoipEventHelper()->populateAdditionalFields($returnData));

        return [
            'id' => $returnData['id'],
            'data' => $returnData,
        ];
    }

    /**
     * Create or retrieve an VoipEvent entity
     *
     * @param  string     $uniqueid
     * @param  array|null $searchParams
     *
     * @return \Espo\Modules\Voip\Repositories\VoipEvent
     */
    public function createEvent($uniqueid = null, array $searchParams = null, $connector = null)
    {
        if (isset($uniqueid)) {
            $entityId = $this->getVoipEventId($uniqueid);
            if (!empty($entityId)) {
                $voipEvent = $this->getEntityManager()->getEntity('VoipEvent', $entityId);
                $this->currentEntity = $voipEvent;
                return $voipEvent;
            }
        }

        return $this->createEventEntity($uniqueid, $searchParams, $connector);
    }

    /**
     * Create or retrieve an VoipEvent entity
     *
     * @param  string     $uniqueid
     * @param  array|null $searchParams
     *
     * @return array of \Espo\Modules\Voip\Repositories\VoipEvent objects
     */
    public function createEvents($uniqueid = null, array $searchParams = null, $connector = null)
    {
        $eventEntities = array();

        if (isset($uniqueid)) {
            $entityIds = $this->getVoipEventId($uniqueid, false);
            if (!empty($entityIds) && is_array($entityIds)) {
                foreach ($entityIds as $entityId) {
                    $eventEntities[$entityId] = $this->getEntityManager()->getEntity('VoipEvent', $entityId);
                }

                $this->currentEntity = reset($eventEntities);
                return $eventEntities;
            }
        }

        $eventEntities[] = $this->createEventEntity($uniqueid, $searchParams, $connector);

        return $eventEntities;
    }

    /**
     * Create VoipEvent entity
     *
     * @param  string     $uniqueid
     * @param  array|null $searchParams
     *
     * @return \Espo\Modules\Voip\Repositories\VoipEvent
     */
    protected function createEventEntity($uniqueid = null, array $searchParams = null, $connector = null)
    {
        $entityManager = $this->getEntityManager();
        $pdo = $entityManager->getPDO();

        if (isset($searchParams)) {
            if (!isset($searchParams['processed'])) {
                $searchParams['processed'] = '0';
            }

            if (!isset($searchParams['ready'])) {
                $searchParams['ready'] = '0';
            }

            if (isset($searchParams['phoneNumber'])) {
                $connectorManager = $this->getConnectorManager($connector);
                $searchParams['phoneNumber'] = $connectorManager->formatPhoneNumber($searchParams['phoneNumber'], PhoneNumber::SEARCH_FORMAT);
            }

            if (isset($searchParams['userExtension'])) {
                $assignedUserId = $this->findUser($searchParams['userExtension'], $connector);
                if (isset($assignedUserId)) {
                    $searchParams['assignedUserId'] = $assignedUserId;
                    unset($searchParams['userExtension']);
                }
            }

            $uSearchParams = array();
            foreach ($searchParams as $name => $value) {
                $uSearchParams[] = "`" . \Espo\Core\Utils\Util::toUnderScore($name) . "` = " . $pdo->quote($value);
            }

            if (!isset($searchParams['uniqueid'])) {
                $uSearchParams[] = 'uniqueid IS NULL';
            }

            $query = "SELECT `id` FROM `voip_event` WHERE
                        ".implode(" AND ", $uSearchParams)."
                        ORDER BY `created_at` DESC";

            $sth = $pdo->prepare($query);
            $sth->execute();

            $row = $sth->fetch(\PDO::FETCH_ASSOC);
            if (!empty($row['id'])) {
                $voipEvent = $entityManager->getEntity('VoipEvent', $row['id']);
            }
        }

        if (!isset($voipEvent)) {
           $voipEvent = $entityManager->getEntity('VoipEvent');
        }

        if (isset($uniqueid)) {
            $voipEvent->set('uniqueid', $uniqueid);
        }

        if (isset($connector)) {
            $voipEvent->set('connector', $connector);
        }

        $this->currentEntity = $voipEvent;

        return $voipEvent;
    }

    /**
     * Normalize entities
     *
     * @param  array $entityList
     * @return array
     */
    protected function normalizeEntities($initEntityList, $connector = null)
    {
        if (empty($initEntityList) || !is_object($initEntityList)) {
            $initEntityList = new \stdClass;
        }

        $entityList = new \stdClass;
        foreach ($this->getVoipParam('permittedEntities', $connector) as $entityName) {
            $entityList->$entityName = new \stdClass;
            if (isset($initEntityList->$entityName)) {
                $entityList->$entityName = $initEntityList->$entityName;
            }
        }

        if ($this->getVoipParam('hideLead', $connector)) {
            if (isset($initEntityList->Contact) || isset($initEntityList->Account)) {
                unset($entityList->Lead);
            }
        }

        return $entityList;
    }

    /**
     * Find userId by $userExtension
     *
     * @param  string $userExtension
     * @return string
     */
    protected function findUser($userExtension, $connector = null)
    {
        $connector = isset($connector) ? $connector : $this->getCurrentConnector();
        return $this->getVoipHelper()->findUser($userExtension, $connector);
    }

    /**
     * Check if a user has enabled/disabled receiving call notifications
     *
     * @param  string  $userId
     *
     * @return boolean
     */
    protected function isDisabledCallNotification($userId)
    {
        if (!empty($userId)) {
            $user = $this->getEntityManager()->getEntity('User', $userId);
            if (isset($user)) {
                $callNotification = $user->get('voipNotifications');
                if (isset($callNotification) && !$callNotification) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Find entities by the $phoneNumber
     *
     * @param  string $phoneNumber
     * @return array
     */
    public function findEntities($phoneNumber, $connector = null)
    {
        $connector = isset($connector) ? $connector : $this->getCurrentConnector();
        $entities = $this->getVoipHelper()->findEntitiesByPhone($phoneNumber, $connector);

        if (!empty($entities)) {
            return $this->normalizeEntities(Util::arrayToObject($entities), $connector);
        }
    }

    /**
     * Get VoipEvent ID by $uniqueid
     *
     * @param  string $uniqueid
     *
     * @return string | null
     */
    protected function getVoipEventId($uniqueid, $findOne = true)
    {
        $pdo = $this->getEntityManager()->getPDO();

        $sql = "
            SELECT DISTINCT `id`
            FROM `voip_event`
            WHERE
            `deleted` = 0 AND
            `uniqueid` = ".$pdo->quote($uniqueid)."
        ";

        if ($findOne) {
            $sql .= " LIMIT 0, 1";
        }

        $sth = $pdo->prepare($sql);
        $sth->execute();

        if ($rows = $sth->fetchAll()) {
            $ids = array();
            foreach ($rows as $row) {
                if ($findOne) {
                   return $row['id'];
                }
                $ids[] = $row['id'];
            }
            return $ids;
        }
    }

    /**
     * Get a status for ended call
     *
     * @param  string $currentStatus
     *
     * @return string
     */
    public function getEndedCallStatus($currentStatus)
    {
        if (isset($this->endedCallStatusMap[$currentStatus])) {
            return $this->endedCallStatusMap[$currentStatus];
        }

        return $currentStatus;
    }

    /**
     * Check if number is voicemail number
     *
     * @param  array | string  $data
     *
     * @return boolean
     */
    public function isIgnoredNumber($data)
    {
        $phoneNumber = $data;
        if (is_array($data)) {
            $phoneNumber = isset($data['phoneNumber']) ? $data['phoneNumber'] : null;
        }

        $ignoredNumberList = $this->getVoipParam('ignoredNumberList');
        if (!empty($phoneNumber) && !empty($ignoredNumberList)) {
            foreach ($ignoredNumberList as $ignoredNumber) {

                $phoneNumber = trim($phoneNumber);
                $ignoredNumber = trim($ignoredNumber);

                /*** BEGIN REGEX ***/
                if (@preg_match($ignoredNumber, null) !== false) {
                    if (preg_match($ignoredNumber, $phoneNumber) == 1) {
                        return true;
                    } else {
                        continue;
                    }
                }
                /*** END REGEX ***/

                if (substr($phoneNumber, 0, strlen($ignoredNumber)) === $ignoredNumber) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Relate Voip Event to just created Account/Lead/Contact
     *
     * @param  string           $uniqueid
     * @param  \Espo\ORM\Entity $relatedEntity
     *
     * @return void
     */
    public function relateVoipEvent($uniqueid, \Espo\ORM\Entity $relatedEntity)
    {
        $voipEvent = $this->get($uniqueid);
        if (!isset($voipEvent)) {
            return;
        }

        $relatedEntityName = $relatedEntity->getEntityName();

        $entities = Util::objectToArray($voipEvent->get('entities'));
        if (!isset($entities[$relatedEntityName][$relatedEntity->get('id')])) {
            $entities[$relatedEntityName][$relatedEntity->get('id')]['name'] = $relatedEntity->get('name');
            $voipEvent->set('entities', Util::arrayToObject($entities));
            $this->save($voipEvent);
        }

        if ($voipEvent->get('processed')) { //voip event is processed
            $callId = $voipEvent->get('callId');
            if (isset($callId)) {
                $call = $this->getEntityManager()->getEntity('Call', $callId);
                if (isset($call)) {
                    $this->getVoipEventHelper()->addCallRelations($voipEvent, $call);
                }
            }
        }
    }

    /**
     * Normalize data for dial action
     *
     */
    public function normalizeDialData(array $data)
    {
        if (empty($data['connector'])) {
            $data['connector'] = $this->getUser()->get('voipConnector');
        }

        if (empty($data['phoneNumber'])) {
            $data['phoneNumber'] = $data['extension'];
        }

        $connectorManager = $this->getConnectorManager($data['connector']);
        $toPhoneNumber = $connectorManager->formatPhoneNumber($data['phoneNumber'], PhoneNumber::DIAL_FORMAT);

        return [
            'extension' => $toPhoneNumber, //DEPRECATED
            'toPhoneNumber' => $toPhoneNumber,
            'callerId' => $this->getUser()->get('voipUser'),
            'userPhoneNumber' => $this->getUser()->get('phoneNumber'),
        ];
    }

    /**
     * Is need to save VoipEvent entity
     *
     * @param  VoipEventEntity $voipEvent
     *
     * @return boolean
     */
    public function isNeedToSave(VoipEventEntity $voipEvent)
    {
        if (!$voipEvent->isNew()) {
            return true;
        }

        $isSave = true;
        $isSave &= !$this->isIgnoredNumber($voipEvent->get('phoneNumber'));

        if ($voipEvent->get('queueNumber') && $this->getVoipParam('activeQueueNumbers')) {
            $isSave &= VoipUtil::isNumberExists($voipEvent->get('queueNumber'), (array) $this->getVoipParam('activeQueueNumbers'));
        }

        return (bool) $isSave;
    }
}
