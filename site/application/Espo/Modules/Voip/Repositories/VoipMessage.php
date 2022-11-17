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

use Espo\ORM\Entity;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Voip\Core\Utils\Voip as VoipUtils;
use Espo\Core\Utils\Util;
use Espo\Core\Utils\Json;
use Espo\Modules\Voip\Core\Utils\PhoneNumber;
use Espo\Modules\Voip\Entities\VoipMessage as VoipMessageEntity;

class VoipMessage extends \Espo\Core\ORM\Repositories\RDB
{
    private $connectorManagers = array();

    protected function init()
    {
        $this->addDependency('user');
        $this->addDependency('config');
        $this->addDependency('voipManager');
        $this->addDependency('voipHelper');
        $this->addDependency('serviceFactory');
        $this->addDependency('language');
    }

    protected function getUser()
    {
        return $this->getInjection('user');
    }

    protected function getLanguage()
    {
        return $this->getInjection('language');
    }

    protected function getVoipHelper()
    {
        return $this->getInjection('voipHelper');
    }

    protected function getServiceFactory()
    {
        return $this->getInjection('serviceFactory');
    }

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function getConnectorManager($connector)
    {
        if (!isset($this->connectorManager[$connector])) {
            $this->connectorManager[$connector] = $this->getInjection('voipManager')->getConnectorManager($connector);
        }

        return $this->connectorManager[$connector];
    }

    public function getInboundMessageList(VoipMessageEntity $voipMessage = null)
    {
        $dataList = [];

        if ($voipMessage) {

            if (!$voipMessage->get('assignedUserId')) {
                return $dataList;
            }

            $assignedUser = $this->getEntityManager()->getEntity('User', $voipMessage->get('assignedUserId'));

            if ($assignedUser
                && $assignedUser->get('voipNotifications')
                && $voipMessage->get('processed') == false
                && $voipMessage->get('hidden') == false
                && $voipMessage->get('deleted') == false
                && $voipMessage->get('direction') == 'incoming'
            ) {
                $list = (object) [
                    $voipMessage
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
                'hidden' => false,
                'deleted' => false,
                'direction' => 'incoming',
                'assignedUserId' => $currentUser->get('id'),
                //'isSubmitted' => false,
            ])->order('dateSent', 'DESC')->find();

        }

        foreach ($list as $voipMessage) {
            $entityNotificationData = $this->normalizeEntityNotificationData($voipMessage);
            if (!empty($entityNotificationData)) {
                $dataList[] = $entityNotificationData;
            }
        }

        return $dataList;
    }

    protected function normalizeEntityNotificationData(VoipMessageEntity $voipMessage)
    {
        $returnFieldList = array(
            'id',
            'type',
            'direction',
            'status',
            'from',
            'to',
            'body',
            'connector',
            'dateSent',
            'assignedUserId',
            'externalId',
            'entities',
            'parentId',
            'parentType',
            'parentName',
            'accountId',
            'data',
        );

        if (!$voipMessage->get('connector')) {
            return [];
        }

        $connector = $voipMessage->get('connector');

        $entityService = $this->getServiceFactory()->create('VoipMessage');
        $entityService->loadAdditionalFields($voipMessage);

        $connectorManager = $this->getConnectorManager($connector);
        $voipMessage->set('from', $connectorManager->formatPhoneNumber($voipMessage->get('from'), PhoneNumber::DISPLAY_FORMAT));

        $returnData = array_intersect_key($voipMessage->toArray(), array_flip($returnFieldList));

        return [
            'id' => $returnData['id'],
            'data' => $returnData,
        ];
    }

    protected function beforeSave(Entity $entity, array $options = array())
    {
        if (!$entity->get('connector') && $entity->get('direction') == 'outgoing' && $entity->get('from')) {
            $voipRouter = $this->getEntityManager()->getRepository('VoipRouter')->getByName($entity->get('from'));
            if ($voipRouter) {
                $entity->set('connector', $voipRouter->get('connector'));
                $entity->set('voipRouterId', $voipRouter->get('id'));
            }
        }

        $connector = $this->getVoipHelper()->getConnectorByEntity($entity);
        $entityType = $entity->getEntityName();

        if ($entity->isNew() && !$entity->get('name')) {
            $name = substr($entity->get('body'), 0, 20);
            if (empty($name)) {
                $name = $this->getLanguage()->translateOption($entity->get('direction'), 'direction', $entityType) . ' ' . $this->getLanguage()->translateOption($entity->get('type'), 'type', $entityType);
            } else {
                if (strlen($name) < strlen($entity->get('body')) ) {
                    $name .= '...';
                }
            }
            $entity->set('name', $name);
        }

        if ($entity->isNew() && $entity->get('status') == 'draft' && !$entity->get('assignedUserId')) {
            $entity->set('assignedUserId', $this->getUser()->get('id'));
        }

        if ($entity->get('direction') == 'outgoing' && $entity->isAttributeChanged('from')) {
            $voipRouter = $this->getEntityManager()->getRepository('VoipRouter')->getByName($entity->get('from'), $connector);
            if ($voipRouter) {
                $entity->set('voipRouterId', $voipRouter->get('id'));
            }
        }

        if ($entity->isNew() && $entity->get('direction') == 'incoming' && $entity->get('mediaUrls')) {
            $attachments = $this->uploadContentIntoEspo($entity);
            if (!empty($attachments)) {
                $entity->set('hasAttachment', true);
            }
        }

        if ($entity->isAttributeChanged('status') && $entity->get('status') == 'sent') {
            $entity->set('dateSent', date('Y-m-d H:i:s'));

            if (!$entity->has('usersIds')) {
                $entity->loadLinkMultipleField('users');
            }

            $entity->addLinkMultipleId('users', $entity->get('createdById'));
            $entity->setLinkMultipleColumn('users', 'isRead', $entity->get('createdById'), true);
        }

        if ($entity->get('direction') == 'incoming' && $entity->isAttributeChanged('from')) {
            $entities = $this->getVoipHelper()->findEntitiesByPhone($entity->get('from'), $connector);
        }

        if ($entity->get('direction') == 'outgoing' && $entity->isAttributeChanged('to')) {
            $entities = $this->getVoipHelper()->findEntitiesByPhone($entity->get('to'), $connector);
        }

        if (isset($entities)) {
            $entity->set('entities', $entities);

            if (!empty($entities['Account'])) {
                $entity->set('accountId', key($entities['Account']));
            }

            if (!$entity->get('parentId')) {
                foreach ($entities as $entityName => $records) {
                    $entity->set(array(
                        'parentType' => $entityName,
                        'parentId' => key($records),
                    ));
                    break;
                }
            }
        }

        //add a voipRouter (phone number) team
        if (($entity->get('direction') == 'incoming' && $entity->isAttributeChanged('to')) || ($entity->get('direction') == 'outgoing' && $entity->isAttributeChanged('from'))) {
            $this->addVoipRouterTeam($entity);
        }

        $assignedUserId = $entity->get('assignedUserId');
        if ($assignedUserId) {
            $entity->addLinkMultipleId('users', $assignedUserId);
        }

        //execute parent method and hooks
        parent::beforeSave($entity, $options);

        if ($entity->isAttributeChanged('status')) {
            $status = $entity->get('status');

            if (in_array($status, array(VoipMessageEntity::ACCEPTED, VoipMessageEntity::QUEUED))) {
                $this->sendMessage($entity);
            }

            if ($entity->get('direction') == 'outgoing' && in_array($status, array(VoipMessageEntity::DELIVERED, VoipMessageEntity::UNDELIVERED, VoipMessageEntity::FAILED))) {
                $this->clearAttachmentTemps($entity);
            }
        }
    }

    protected function uploadContentIntoEspo(Entity $entity)
    {
        $mediaUrls = $entity->get('mediaUrls', array());
        $attachmentIdList = [];

        foreach ($mediaUrls as $mediaUrl) {
            $fileData = VoipUtils::getFileDataByUrl($mediaUrl);

            if (isset($fileData['type']) && isset($fileData['contents'])) {

                $maxSize = $this->getMetadata()->get('entityDefs.VoipMessage.fields.attachments.maxFileSize');
                if (!$maxSize) {
                    $maxSize = $this->getConfig()->get('attachmentUploadMaxSize');
                }
                if ($maxSize) {
                    $size = mb_strlen($fileData['contents'], '8bit');
                    if ($size > $maxSize * 1024 * 1024) {
                        $GLOBALS['log']->error('VoipMessage: File size should not exceed {$maxSize}Mb.');
                        continue;
                    }
                }

                $attachment = $this->getEntityManager()->getEntity('Attachment');
                $attachment->set([
                    'name' => $this->getLanguage()->translate('Download', 'labels', 'VoipMessage'),
                    'type' => $fileData['type'],
                    'contents' => $fileData['contents'],
                    'role' => 'Attachment',
                    'field' => 'attachments',
                    'parentType' => $entity->getEntityName(),
                    'parentId' => $entity->get('id'),
                ]);
                $this->getEntityManager()->saveEntity($attachment);
                $attachment->clear('contents');

                $attachmentIdList[] = $attachment->id;
            }
        }

        return $attachmentIdList;
    }

    /**
     * Send a message
     *
     * @param  Entity $entity
     *
     * @return void
     */
    protected function sendMessage(Entity $entity)
    {
        $connector = $this->getVoipHelper()->getConnectorByEntity($entity);
        $connectorManager = $this->getConnectorManager($connector);

        $fromNumber = $entity->get('from') ? $entity->get('from') : $this->getUser()->get('voipUser');
        $toNumber = $connectorManager->formatPhoneNumber($entity->get('to'), PhoneNumber::DIAL_FORMAT);

        if (!empty($fromNumber) && !empty($toNumber)) {
            $body = $entity->get('body');
            $mediaUrls = $this->attachmentsToLinks($entity);
            $entity->set('mediaUrls', $mediaUrls);
            $externalId = $connectorManager->sendMessage($fromNumber, $toNumber, $body, array(
                'mediaUrls' => $mediaUrls
            ));

            if (empty($externalId)) {
                $entity->set(array(
                    'status' => VoipMessageEntity::FAILED,
                ));
                $this->getEntityManager()->saveEntity($entity);
                throw new Error('Message is not sent.');
            }

            $entity->set(array(
                'externalId' => $externalId,
                'connector' => $connectorManager->getConnector(),
                'status' => VoipMessageEntity::SENT,
                'dateSent' => date('Y-m-d H:i:s'),
            ));
            $this->getEntityManager()->saveEntity($entity);
        }
    }

    /**
     * Add VoipRouter Team to VoipMessage entity
     *
     * @param Entity $entity
     */
    protected function addVoipRouterTeam(Entity $entity)
    {
        $connector = $this->getVoipHelper()->getConnectorByEntity($entity);

        $fromNumber = $entity->get('direction') == 'incoming' ? $entity->get('to') : $entity->get('from');

        $teamsIds = $entity->get('teamsIds');
        if (!$teamsIds && !$entity->isNew()) {
            $entity->loadLinkMultipleField('teams');
            $teamsIds = $entity->get('teamsIds');
        }

        $teamsIds = $teamsIds ?? [];

        $voipRouterRepository = $this->getEntityManager()->getRepository('VoipRouter');
        $voipRouter = $voipRouterRepository->getByName($fromNumber, $connector);
        if ($voipRouter) {
            $routerTeamId = $voipRouter->get('teamId');
            if (!in_array($routerTeamId, $teamsIds)) {
                $entity->addLinkMultipleId('teams', $routerTeamId);
                $teamsNames = (array) $entity->get('teamsNames');
                $teamsNames[$routerTeamId] = $voipRouter->get('teamName');
                $entity->set('teamsNames', $teamsNames);
            }
        }
    }

    protected function getCopiedAttachmentsIds(Entity $entity)
    {
        $ids = array();
        $attachmentIds = $entity->get('attachmentsIds');
        if (!$attachmentIds || count($attachmentIds) == 0) {
            $entity->loadLinkMultipleField('attachments');
            $attachmentIds = $entity->get('attachmentsIds');
        }
        $attachmentRepo = $this->getEntityManager()->getRepository('Attachment');
        foreach ($attachmentIds as $attachmentId) {
            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            if (!$attachment) {
                continue;
            }
            $newAttachment = $attachmentRepo->getCopiedAttachment($attachment, 'Twilio');
            if ($newAttachment) {
                $ids[] = $newAttachment->get('id');
            }
        }
        return $ids;
    }

    protected function attachmentIdAsLink(Entity $entity, $id)
    {
        return rtrim($this->getConfig()->get('siteUrl'), '/') . '/?entryPoint=TwilioMedia&id=' . $id . '&messageId=' . $entity->get('id');
    }

    protected function attachmentsToLinks(Entity $entity)
    {
        $links = array();
        $ids = $this->getCopiedAttachmentsIds($entity);
        foreach ($ids as $id) {
            $links[] = $this->attachmentIdAsLink($entity, $id);
        }
        return $links;
    }

    protected function clearAttachmentTemps(Entity $entity)
    {
        $attachments = $this->getRelation($entity, 'attachments')->find();
        if (!$attachments) return;

        $attachmentRepository = $this->getEntityManager()->getRepository('Attachment');
        foreach ($attachments as $attachment) {
            $relatedAttachments = $attachmentRepository->where(array(
                'sourceId' => $attachment->get('id'),
                'role' => 'Twilio'
            ))->find();
            foreach ($relatedAttachments as $relatedAttachment) {
                $this->getEntityManager()->removeEntity($relatedAttachment);
            }
        }
    }

    public function getByExternalId($externalId)
    {
        return $this->where(array(
            'externalId' => $externalId,
        ))->findOne();
    }

    /**
     * Create/get a new VoipMessage by $externalId or $searchParams
     *
     * @param  string     $externalId
     * @param  array|null $searchParams
     * @param  string     $connector
     *
     * @return \Espo\Modules\Voip\Entities\VoipMessage
     */
    public function createMessage($externalId = null, array $searchParams = null, $connector = null)
    {
        //find existing VoipMessage by $externalId
        if (isset($externalId)) {
            $voipMessage = $this->getByExternalId($externalId);
            if (!empty($voipMessage)) {
                return $voipMessage;
            }
        }

        if (isset($searchParams)) {
            //find existing VoipMessage by $searchParams
            if (!isset($searchParams['connector']) && isset($connector)) {
                $searchParams['connector'] = $connector;
            }

            $voipMessage = $this->where($searchParams)->findOne();
            if (!empty($voipMessage)) {
                return $voipMessage;
            }
        }

        //create a new VoipMessage
        $voipMessage = $this->get();

        if (isset($externalId)) {
            $voipMessage->set('externalId', $externalId);
        }

        if (isset($connector)) {
            $voipMessage->set('connector', $connector);
        }

        return $voipMessage;
    }

    /**
     * Mark VoipMessage to processed
     *
     * @param  Entity $entity
     *
     * @return void
     */
    public function markProcessed(Entity $entity)
    {
        $entity->set('processed', true);
        $this->save($entity);
    }

    protected function getConnectorByEntity(Entity $entity)
    {


    }
}
