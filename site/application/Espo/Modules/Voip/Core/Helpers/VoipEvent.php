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

use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Error;
use Espo\Modules\Voip\Entities\VoipEvent as VoipEventEntity;
use Espo\ORM\Entity;
use Espo\Core\Utils\Util;

class VoipEvent
{
    private $container;

    protected $callRelatedEntities = array(
        //link name => entity name
        'contacts' => 'Contact',
        'leads' => 'Lead',
    );

    public function __construct(\Espo\Core\Container $container)
    {
        $this->container = $container;
    }

    protected function getContainer()
    {
        return $this->container;
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

    protected function getVoipRecordingManager()
    {
        return $this->container->get('voipRecordingManager');
    }

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    /**
     * Get a title for a call
     *
     * @param  VoipEventEntity $voipEventEntity
     * @param  string $categoryName
     *
     * @return string
     */
    public function getCallTitle(VoipEventEntity $voipEventEntity, $labelCategory = 'callNames', $labelEntity = 'VoipEvent')
    {
        $language = $this->getLanguage();

        $callType = $voipEventEntity->get('type');
        $phoneNumber = $voipEventEntity->get('phoneNumber');
        if ($voipEventEntity->get('assignedUserId')) {
            $user = $this->getEntityManager()->getEntity('User', $voipEventEntity->get('assignedUserId'));
        }

        $labelName = $callType;

        switch ($callType) {
            case 'incomingCall':
                $labelName = 'incomingCallTitle';
                if (!isset($user)) {
                    $labelName = 'incomingCallTitleWithoutRecipient';
                }
                break;

            case 'outgoingCall':
                $labelName = 'outgoingCallTitle';
                if (!$phoneNumber) {
                    $labelName = 'outgoingCallTitleWithoutRecipient';
                }
                break;

            default:
                $labelName = 'defaultCallTitle';
        }

        return $this->parseLabel(
            $phoneNumber,
            $user ?? null,
            $voipEventEntity->get('entities'),
            $labelName,
            $labelCategory,
            $labelEntity
        );
    }

    public function parseLabel($phoneNumber, $user = null, $entityList, $labelName, $labelCategory = 'callNames', $labelEntity = 'VoipEvent')
    {
        $language = $this->getLanguage();

        $person = $this->getEntityByType($entityList, 'Person');
        $account = $this->getEntityByType($entityList, 'Company');

        if (!$user) {
            if ($person && $person->get('assignedUserId')) {
                $user = $this->getEntityManager()->getEntity('User', $person->get('assignedUserId'));
            } else if ($account && $account->get('assignedUserId')) {
                $user = $this->getEntityManager()->getEntity('User', $account->get('assignedUserId'));
            }
        }

        if (isset($user)) {
            $preferences = $this->getEntityManager()->getEntity('Preferences', $user->get('id'));
            if ($preferences) {
                $userLanguage = $preferences->get('language');
                if (!empty($userLanguage)) {
                    $language->setLanguage($preferences->get('language'));
                }
            }
        }

        $label = $language->translate($labelName, $labelCategory, $labelEntity);

        if (!empty($phoneNumber)) {
            $label = str_replace('{phoneNumber}', $phoneNumber, $label);
        }

        $labelList = $this->getLabelListByName('person', $label);
        if ($person) {
            foreach ($labelList as $labelItem) {
                $label = str_replace('{person.'.$labelItem.'}', $person->get($labelItem), $label);
            }
        }

        $labelList = $this->getLabelListByName('account', $label);
        if (!empty($account)) {
            foreach ($labelList as $labelItem) {
                $label = str_replace('{account.'.$labelItem.'}', $account->get($labelItem), $label);
            }
        }

        $labelList = $this->getLabelListByName('user', $label);
        if (!empty($user)) {
            foreach ($labelList as $labelItem) {
                $label = str_replace('{user.'.$labelItem.'}', $user->get($labelItem), $label);
            }
        }

        /* translate other labels */
        preg_match_all('/\{(.+?)\}/i', $label, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $labelItem) {
                $translated = $language->get([$labelEntity, $labelCategory, $labelItem]);
                if ($translated) {
                    $label = str_replace('{'.$labelItem.'}', $translated, $label);
                }
            }
        }

        /* clear unused labels  */
        preg_match_all('/\{(.+?)\}/i', $label, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $labelItem) {
                $label = str_replace('{'.$labelItem.'}', '', $label);
            }
        }

        return trim($label, ' ,');
    }

    /**
     * [Get person entity, 'Contact', 'Lead' by default. List order is mportant
     * @param  object | array $entityList
     * @param  string $type       Scope type [Person, Company]
     * @return Entity
     */
    protected function getEntityByType($entityList, $type = 'Person')
    {
        if (empty($entityList)) {
            return;
        }

        if (is_array($entityList)) {
            $entityList = Util::arrayToObject($entityList);
        }

        /* entities from VoipEvent */
        foreach ($entityList as $entityName => $entityItems) {

            if ($this->getScopeType($entityName) != $type) {
                continue;
            }

            if (!empty($entityItems)) {

                $entity = $entityItems;
                if ($entity instanceof Entity) {
                    return $entity;
                }

                if (is_object($entityItems)) {
                    $entityId = key($entityItems);

                    if ($entityId) {
                        $entity = $this->getEntityManager()->getEntity($entityName, $entityId);
                    }

                    if ($entity instanceof Entity) {
                        return $entity;
                    }
                }

            }

        }
    }

    protected function getLabelListByName($name, $content)
    {
        $labels = array();
        preg_match_all('/\{'.$name.'\.(.+?)\}/', $content, $matches);
        if (isset($matches[1])) {
            foreach ($matches[1] as $value) {
                $labels[] = $value;
            }
        }

        return $labels;
    }

    /**
     * Create a call from VoipEvent
     *
     * @param array $data
     */
    public function addCallFromVoipEvent(array $data, $closeVoipEvent = true, $addHistoryRecord = false)
    {
        $entityManager = $this->getEntityManager();

        $voipEvent = $this->getVoipEventEntity($data);

        $voipEventInitData = [
            'entities' => $voipEvent->get('entities'),
        ];

        if (isset($data['entities'])) {
            $voipEvent->set('entities', $data['entities']);
        }

        $entities = array();

        //create/edit the call
        $existingCallId = $voipEvent->get('callId');
        if (isset($existingCallId)) {
            $entities['Call'] = $entityManager->getEntity('Call', $existingCallId);
        }

        $callData = array();

        if (!isset($entities['Call'])) {
            $entities['Call'] = $entityManager->getEntity('Call');

            $language = $this->getLanguage();

            $callData = array(
                'name' => $this->getCallTitle($voipEvent),
            );
        }

        if (!$entities['Call']->get('assignedUserId') && $voipEvent->get('assignedUserId')) {
            $callData['assignedUserId'] = $voipEvent->get('assignedUserId');
        }

        if (!$entities['Call']->get('voipRouterId') && $voipEvent->get('voipRouterId')) {
            $voipRouter = $entityManager->getEntity('VoipRouter', $voipEvent->get('voipRouterId'));
            if ($voipRouter) {
                $callData['voipRouterId'] = $voipRouter->get('id');

                if (!$entities['Call']->isNew()) {
                    $entities['Call']->loadLinkMultipleField('teams');
                }

                $teamsIds = $entities['Call']->get('teamsIds', []);
                $teamsIds[] = $voipRouter->get('teamId');
                $callData['teamsIds'] = $teamsIds;
            }
        }

        $callData['dateStart'] = $voipEvent->get('dateStart');
        $callData['dateEnd'] = $voipEvent->getCallDateEnd();
        $callData['direction'] = $voipEvent->getCallDirection();
        $callData['status'] = $voipEvent->getCallStatus();
        $callData['voipUniqueid'] = $voipEvent->get('uniqueid');
        $callData['voipPhoneNumber'] = $voipEvent->get('phoneNumber');
        $callData['voipLine'] = $voipEvent->get('line');

        $recording = $this->getVoipRecordingManager()->getClass($voipEvent->get('connector'));
        if (!empty($recording)) {
            $recordingUrl = $recording->generateUrl($voipEvent);
            if (!empty($recordingUrl)) {
                $callData['voipRecording'] = $recordingUrl;
            }
        }

        $entities['Call']->set($callData);
        $entities['Call']->needToSave = true;

        $this->addCallRelations($voipEvent, $entities['Call']);

        //save additional fields
        $additionalFieldDefs = $this->getMetadata()->get('app.popupNotifications.voipNotification.additionalFields');
        if (isset($additionalFieldDefs)) {
            foreach ($additionalFieldDefs as $fieldName => $fieldDefs) {
                if (isset($data[$fieldName])) {
                    $currentEntity = $fieldDefs['entity'];

                    if (!isset($entities[$currentEntity])) {
                        if (empty($voipEvent->get('entities')->$currentEntity)) {
                            continue;
                        }

                        $currentId = key((array) $voipEvent->get('entities')->$currentEntity);
                        $entities[$currentEntity] = $entityManager->getEntity($currentEntity, $currentId);
                        if (!isset($entities[$currentEntity])) {
                            continue;
                        }
                    }

                    if ($entities[$currentEntity]->getFetched($fieldDefs['field']) != $data[$fieldName]) {
                        $entities[$currentEntity]->set($fieldDefs['field'], $data[$fieldName]);
                        $entities[$currentEntity]->needToSave = true;
                    }
                }
            }
        }
        //End

        foreach ($entities as $entityName => $entity) {
            if (isset($entity->needToSave) && $entity->needToSave && $entity instanceof Entity) {
                if (!$entityManager->saveEntity($entity, ['noNotifications' => true])) {
                    $GLOBALS['log']->error('VoIP: Error saving a '.$entityName.' for VoipEvent ['.$voipEvent->get('id').'].');
                }

                if ($addHistoryRecord) {
                    $this->addHistoryRecord($entity);
                }

                $GLOBALS['log']->debug('VoIP: Saved entity['.$entityName.', '.$entity->get('id').'] from VoipEvent ['.$voipEvent->get('id').'].');
            }
        }

        $voipEvent->set('callId', $entities['Call']->get('id'));

        if (!$closeVoipEvent) {
            return true;
        }

        // Add a phone number for selected entities
        if (isset($data['entities']) && !Util::areValuesEqual($data['entities'], $voipEventInitData['entities'])) {
            $phoneNumber = $voipEvent->get('phoneNumber');
            $this->addEntitiesPhoneNumber($phoneNumber, $data['entities'], $voipEventInitData['entities']);
        }

        if ($this->closeVoipEvent($voipEvent)) {
            return true;
        }
        return false;
    }

    /**
     * Add relations (Account, Contact, Lead) to Call
     *
     * @param \Espo\Modules\Voip\Entities\VoipEvent $voipEvent
     * @param \Espo\ORM\Entity $call
     */
    public function addCallRelations(VoipEventEntity $voipEvent, \Espo\Modules\Crm\Entities\Call $call)
    {
        $accountId = $voipEvent->getAccountId();
        $parentId = $call->get('parentId');
        if (isset($accountId) && (empty($parentId) || $accountId != $parentId)) {
            $call->set('parentType', 'Account');
            $call->set('parentId', $accountId);
        }

        if (!$call->get('parentId')) {
            $contacts = $voipEvent->getEntityIds('Contact');
            if (isset($contacts) && !empty($contacts[0])) {
                $call->set('parentType', 'Contact');
                $call->set('parentId', $contacts[0]);
            }
        }

        if (!$call->get('parentId')) {
            $leads = $voipEvent->getEntityIds('Lead');
            if (isset($leads) && !empty($leads[0])) {
                $call->set('parentType', 'Lead');
                $call->set('parentId', $leads[0]);
            }
        }

        if ($voipEvent->getEntities()) {
            $entityList = array_diff( array_keys($voipEvent->getEntities()), ['Account'] );
            foreach ($entityList as $entityName) {

                $linkName = lcfirst($entityName) . 's';
                if (in_array($entityName, $this->callRelatedEntities)) {
                    $linkName = array_search($entityName, $this->callRelatedEntities);
                }

                if ($call->hasRelation($linkName)) {
                    $ids = $voipEvent->getEntityIds($entityName);
                    if (!empty($ids)) {
                        $call->set($linkName . 'Ids', $ids);
                    }
                }
            }
        }
    }

    protected function addEntitiesPhoneNumber($phoneNumber, $newEntities, $entities)
    {
        $entityList = [];

        foreach ($newEntities as $entityName => $entityItems) {
            foreach ($entityItems as $itemId => $itemData) {
                if (!isset($entities->$entityName->$itemId)) {
                    $entityList[] = [
                        'type' => $entityName,
                        'id' => $itemId,
                    ];
                }
            }
        }

        $entityManager = $this->getEntityManager();
        $phoneNumberRepository = $entityManager->getRepository('PhoneNumber');

        foreach ($entityList as $entityData) {
            $entity = $entityManager->getEntity($entityData['type'], $entityData['id']);

            if ($entity && $entity->hasAttribute('phoneNumber') && $entity->getAttributeParam('phoneNumber', 'fieldType') == 'phone') {
                $phoneNumberData = $phoneNumberRepository->getPhoneNumberData($entity);

                $primary = empty($phoneNumberData) ? true : false;
                $defaultType = $this->getMetadata()->get('entityDefs.' .  $entity->getEntityType() . '.fields.phoneNumber.defaultType', 'Mobile');

                $o = new \StdClass();
                $o->phoneNumber = $phoneNumber;
                $o->primary = $primary;
                $o->type = $defaultType;
                $phoneNumberData[] = $o;

                $entity->set('phoneNumberData', $phoneNumberData);

                if (method_exists($phoneNumberRepository, 'storeEntityPhoneNumber')) {
                    $phoneNumberRepository->storeEntityPhoneNumber($entity);
                }

                $entityManager->saveEntity($entity, ['noNotifications' => true]);
            }
        }
    }

    /**
     * Add call reminders
     *
     * @param \Espo\Modules\Crm\Entities\Call $call
     * @param array                           $types
     */
    public function addCallReminders(\Espo\Modules\Crm\Entities\Call $call, array $types = ['Popup', 'Email'])
    {
        if (empty($types)) {
            return;
        }

        $dateStart = $call->get('dateStart');

        $remindAt = new \DateTime($dateStart);
        if (!$remindAt) {
            return;
        }

        $pdo = $this->getEntityManager()->getPDO();

        foreach ($types as $type) {
            $id = uniqid();
            $seconds = 0;

            $sql = "
                INSERT
                INTO `reminder`
                (`id`, `entity_id`, `entity_type`, `type`, `user_id`, `remind_at`, `start_at`, `seconds`)
                VALUES (
                    ".$pdo->quote($id).",
                    ".$pdo->quote($call->get('id')).",
                    ".$pdo->quote($call->getEntityType()).",
                    ".$pdo->quote($type).",
                    ".$pdo->quote($call->get('assignedUserId')).",
                    ".$pdo->quote($remindAt->format('Y-m-d H:i:s')).",
                    ".$pdo->quote($remindAt->format('Y-m-d H:i:s')).",
                    ".$pdo->quote($seconds)."
                )
            ";

            $pdo->query($sql);
        }
    }

    /**
     * Close VoipEvent record (after "save" or "cancel")
     *
     * @param \Espo\Modules\Voip\Entities\VoipEvent | array $data
     *
     * @return string
     */
    public function closeVoipEvent($data)
    {
        $entityManager = $this->getEntityManager();

        $voipEvent = $this->getVoipEventEntity($data);

        $callId = $voipEvent->get('callId');
        if ($callId) {
            $call = $entityManager->getEntity('Call', $callId);
            if ($call) {
                $endDate = $voipEvent->getCallDateEnd();
                if ($call->get('dateEnd') != $endDate) {
                    $call->set('dateEnd', $endDate);
                    $entityManager->saveEntity($call);
                }
            }
        }

        $voipEvent->set('processed', true);
        $saved = $entityManager->saveEntity($voipEvent);

        return $saved ? true : false;
    }

    /**
     * Get VoipEvent entity from input $data
     *
     * @param  array | \Espo\Modules\Voip\Entities\VoipEvent  $data
     *
     * @return \Espo\Modules\Voip\Entities\VoipEvent
     */
    protected function getVoipEventEntity($data)
    {
        if ($data instanceof VoipEventEntity) {
            return $data;
        }

        if (isset($data['event']) && $data['event'] instanceof VoipEventEntity) {
            return $data['event'];
        }

        if (empty($data['eventId'])) {
            throw new Error('Astersik: Bad input data for creating a call from VoipEvent.');
        }

        $entityManager = $this->getEntityManager();

        $voipEvent = $entityManager->getEntity('VoipEvent', $data['eventId']);

        if (!isset($voipEvent)) {
            throw new NotFound('Astersik: Entity['.$data['eventId'].'] of VoipEvent is not found.');
        }

        return $voipEvent;
    }

    public function populateAdditionalFields(array $row)
    {
        $additionalFieldDefs = $this->getMetadata()->get('app.popupNotifications.voipNotification.additionalFields');

        $values = array();

        if (empty($additionalFieldDefs)) {
            return $values;
        }

        $definedEntities = Util::objectToArray($row['entities']);

        if (!empty($row['callId']) && !isset($definedEntities['Call'])) {
            $definedEntities['Call'] = array($row['callId'] => $row['callId']);
        }

        $entities = array();
        foreach ($additionalFieldDefs as $fieldName => $fieldDefs) {
            $values[$fieldName] = null;

            $entityName = $fieldDefs['entity'];

            if (isset($definedEntities[$entityName])) {

                if (!isset($entities[$entityName])) {
                    $entities[$entityName] = $this->getEntityManager()->getEntity($entityName, key($definedEntities[$entityName]));

                    if (!isset($entities[$entityName])) {
                        continue;
                    }
                }

                $values[$fieldName] = $entities[$entityName]->get($fieldDefs['field']);
            }
        }

        return $values;
    }

    /**
     * Get data to make a call (dial)
     *
     * @param  string $callId
     * @param  string $connector
     *
     * @return array
     */
    public function getDialDataFromCall($callId, $connector)
    {
        $entityManager = $this->getEntityManager();

        $connectorData = $entityManager->getEntity('Integration', $connector)->get('data');
        $call = $entityManager->getEntity('Call', $callId);
        if (!isset($call)) {
            throw new Error('VoipEventHelper: Entity Call['.$callId.'] is not found.');
        }

        //for incoming call use VoipEvent phone number
        if ($call->get('direction') == 'Inbound' && $call->get('voipUniqueid')) {
            $entity = $entityManager->getRepository('VoipEvent')->where(array('uniqueid' => $call->get('voipUniqueid')))->findOne();
        }

        if (!isset($entity) || !$entity->get('phoneNumber')) {
            $entityList = $connectorData->permittedEntities;

            $entity = null;

            foreach ($entityList as $entityName) {
                switch ($entityName) {
                    case 'Contact':
                        $call->loadLinkMultipleField('contacts');
                        $contactsIds = $call->get('contactsIds');
                        foreach ($contactsIds as $contactId) {
                            $contact = $entityManager->getEntity('Contact', $contactId);
                            if ($contact && $contact->get('phoneNumber')) {
                                $entity = $contact;
                                break;
                            }
                        }
                        break;

                    case 'Lead':
                        if ($call->get('parentType') == 'Lead') {
                            $parent = $entityManager->getEntity($call->get('parentType'), $call->get('parentId'));
                            if ($parent->get('phoneNumber')) {
                                $entity = $parent;
                                break;
                            }
                        }

                        $call->loadLinkMultipleField('leads');
                        $leads = $call->get('leads');
                        foreach ($leads as $key => $lead) {
                            if ($lead->get('phoneNumber')) {
                                $entity = $lead;
                                break;
                            }
                        }
                        break;

                    case 'Account':
                        $account = $call->get('account');
                        if ($account && $account->get('phoneNumber')) {
                            $entity = $account;
                        }
                        break;
                }

                if (isset($entity)) {
                    break;
                }
            }
        }

        if (!isset($entity)) {
            $GLOBALS['log']->error('VoipEventHelper: Could not find a phone number for a call ['.$callId.'].');
            throw new Error('Could not find a phone number.');
        }

        return array(
            'entityId' => $entity->get('id'),
            'entityName' => $entity->getEntityName(),
            'phoneNumber' => $entity->get('phoneNumber'),
        );
    }

    /**
     * Create VoIP Event record based on $data
     *
     * @param  array  $data
     * @param  string $connector
     *
     * @return string
     */
    public function createVoipEventFromCall(array $data, $connector)
    {
        $entityManager = $this->getEntityManager();
        $voipRepository = $entityManager->getRepository('VoipEvent');

        $voipEvent = $voipRepository->createEvent(null, null, $connector);

        $voipEvent->set(array(
            'type' => VoipEventEntity::OUTGOING_CALL,
            'userExtension' => $data['callerId'],
            'phoneNumber' => $data['extension'],
            'ready' => false,
        ));

        $call = $entityManager->getEntity('Call', $data['callId']);
        if (isset($call) && $call->get('status') == 'Planned') {
            $voipEvent->set('callId', $data['callId']);
        }

        return $entityManager->saveEntity($voipEvent);
    }

    public function autoSaveCall(\Espo\Modules\Voip\Entities\VoipEvent $voipEvent, $savingType)
    {
        if ($voipEvent->get('hidden') == true) {
            return;
        }

        if ($voipEvent->get('system') == true) {
            return;
        }

        if ($voipEvent->get('ready') == false) {
            return;
        }

        if ($voipEvent->get('isQueue') && $voipEvent->getCallStatus() != 'Held') {
            return;
        }

        switch ($savingType) {
            case 'yes':
                $this->addCallFromVoipEvent(['event' => $voipEvent], false);
                break;

            case 'incomingCall':
                if ($voipEvent->get('type') == VoipEventEntity::INCOMING_CALL) {
                    $this->addCallFromVoipEvent(['event' => $voipEvent], false);
                }
                break;

            case 'outgoingCall':
                if ($voipEvent->get('type') == VoipEventEntity::OUTGOING_CALL) {
                    $this->addCallFromVoipEvent(['event' => $voipEvent], false);
                }
                break;
        }

        return $voipEvent->get('callId');
    }

    protected function getScopeType($scope)
    {
        if (in_array($scope, ['Contact', 'Lead', 'User'])) {
            return 'Person';
        }

        if (in_array($scope, ['Account'])) {
            return 'Company';
        }

        return $this->getMetadata()->get(['scopes', $scope, 'type']);
    }

    protected function addHistoryRecord(Entity $entity)
    {
        $user = $this->getUser();

        if (!$user || $user->id == 'system') {
            return;
        }

        $service = $this->container->get('serviceFactory')->create($entity->getEntityType());

        /** Remove from v7+ */
        if (!$this->isProcessActionHistoryRecordAccesible($service)) {
            return $this->processActionHistoryRecordDeprecated('update', $entity);
        }

        return $service->processActionHistoryRecord('update', $entity);
    }

    /** Remove from v7+ */
    private function isProcessActionHistoryRecordAccesible($service)
    {
        if (!method_exists($service, 'processActionHistoryRecord')) {
            return false;
        }

        $reflection = new \ReflectionMethod($service, 'processActionHistoryRecord');
        if (!$reflection->isPublic()) {
            return false;
        }

        return true;
    }

    /** Remove from v7+ */
    private function processActionHistoryRecordDeprecated(string $action, Entity $entity): void
    {
        $config = $this->container->get('config');
        $user = $this->getUser();

        if ($config->get('actionHistoryDisabled')) {
            return;
        }

        $historyRecord = $this->getEntityManager()->getEntity('ActionHistoryRecord');

        $historyRecord->set('action', $action);
        $historyRecord->set('userId', $user->id);
        $historyRecord->set('authTokenId', $user->get('authTokenId'));
        $historyRecord->set('ipAddress', $user->get('ipAddress'));
        $historyRecord->set('authLogRecordId', $user->get('authLogRecordId'));

        if ($entity) {
            $historyRecord->set([
                'targetType' => $entity->getEntityType(),
                'targetId' => $entity->id
            ]);
        }

        $this->getEntityManager()->saveEntity($historyRecord);
    }
}
