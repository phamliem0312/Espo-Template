<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Services;

use Espo\Core\Exceptions\Error;
use Espo\ORM\Entity;

use Exception;

class Workflow extends \Espo\Services\Record
{
    protected function init()
    {
        parent::init();

        $this->addDependency('mailSender');
        $this->addDependency('workflowHelper');
        $this->addDependency('container');
        $this->addDependency('config');
        $this->addDependency('crypt');
    }

    protected $forceSelectAllAttributes = true;

    protected function getMailSender()
    {
        return $this->getInjection('mailSender');
    }

    protected function getWorkflowHelper()
    {
        return $this->getInjection('workflowHelper');
    }

    protected function getCrypt()
    {
        return $this->injections['crypt'];
    }

    protected function getLanguage()
    {
        return $this->getInjection('container')->get('defaultLanguage');
    }

    protected function getHasher()
    {
        return $this->getInjection('container')->get('hasher');
    }

    protected $readOnlyAttributeList = [
        'isInternal'
    ];

    /**
     * @todo Remove.
     */
    protected $exportSkipAttributeList = [
        'lastRun', 'flowchartId', 'flowchartName'
    ];

    /**
     * Send email defined in workflow
     *
     * @param  array $data  See validateSendEmailData method
     * @return bool
     */
    public function sendEmail($data)
    {
        if (is_array($data)) {
            $data = json_decode(json_encode($data));
        }

        if (empty($data->entityType)) {
            $data->entityType = $data->entityName;
        }

        if (!$this->validateSendEmailData($data)) {
            throw new Error('Workflow['.$data->workflowId.'][sendEmail]: Email data is broken.');
        }

        $entityManager = $this->getEntityManager();

        $workflow = $entityManager->getEntity('Workflow', $data->workflowId);

        if (!$workflow) {
            return;
        }

        if (!$workflow->get('isActive')) {
            return;
        }

        $entity = null;

        if (!empty($data->entityType) && !empty($data->entityId)) {
            $entity = $entityManager->getEntity($data->entityType, $data->entityId);
        }

        if (!$entity) {
            throw new Error('Workflow['.$data->workflowId.'][sendEmail]: Target Entity is not found.');
        }

        $entityService = $this->getServiceFactory()->create($entity->getEntityType());
        $entityService->loadAdditionalFields($entity);

        $toEmailAddress = $this->getEmailAddress($data->to);
        $fromEmailAddress = $this->getEmailAddress($data->from);

        $replyToEmail = null;
        if (!empty($data->replyTo)) {
            $replyToEmail = $this->getEmailAddress($data->replyTo);
        }

        if (empty($toEmailAddress) || empty($fromEmailAddress)) {
            throw new Error('Workflow['.$data->workflowId.'][sendEmail]: To or From email address is empty.');
        }

        $entityHash = [
            $data->entityType => $entity
        ];

        if (
            isset($data->to->entityName) &&
            isset($data->to->entityId) &&
            $data->to->entityName != $data->entityType
        ) {
            $toEntityType = $data->to->entityName;

            $entityHash[$toEntityType] = $entityManager->getEntity($toEntityType, $data->to->entityId);
        }

        if (isset($data->from->entityName) && $data->from->entityName == 'User') {
            $entityHash['User'] = $entityManager->getEntity('User', $data->from->entityId);
            $fromName = $entityHash['User']->get('name');
        }

        $emailTemplateParams = [
            'entityHash' => $entityHash,
            'emailAddress' => $toEmailAddress,
            'parentId' => $entity->id,
            'parentType' => $entity->getEntityType(),
        ];

        if ($entity->hasAttribute('parentId') && $entity->hasAttribute('parentType')) {
            $emailTemplateParams['relatedId'] = $entity->get('parentId');
            $emailTemplateParams['relatedType'] = $entity->get('parentType');
        }

        $emailTemplateService = $this->getServiceFactory()->create('EmailTemplate');
        $emailTemplateData = $emailTemplateService->parse($data->emailTemplateId, $emailTemplateParams, true);

        $subject = $emailTemplateData['subject'];
        $body = $emailTemplateData['body'] ?? '';

        if (isset($data->variables)) {
            foreach (get_object_vars($data->variables) as $key => $value) {
                if (is_string($value) || is_int($value) || is_float($value)) {
                    if (is_int($value) || is_float($value)) {
                        $value = strval($value);
                    }
                    else {
                        if (!$value) {
                            continue;
                        }
                    }

                    $subject = str_replace('{$$' . $key . '}', $value, $subject);
                    $body = str_replace('{$$' . $key . '}', $value, $body);
                }
            }
        }

        $siteUrl = $this->getConfig()->get('siteUrl');

        $body = $this->applyTrackingUrlsToEmailBody($body, $toEmailAddress);

        $hasOptOutLink = $data->optOutLink ?? false;

        $message = new \Zend\Mail\Message();

        if ($hasOptOutLink) {
            $hasher = $this->getHasher();

            if ($hasher) {
                $hash = $hasher->hash($toEmailAddress);

                $optOutUrl = $siteUrl .
                    '?entryPoint=unsubscribe&emailAddress=' . $toEmailAddress . '&hash=' . $hash;

                $optOutLink = '<a href="'.$optOutUrl.'">' .
                    $this->getLanguage()->translate('Unsubscribe', 'labels', 'Campaign').'</a>';

                $body = str_replace('{optOutUrl}', $optOutUrl, $body);
                $body = str_replace('{optOutLink}', $optOutLink, $body);

                if (stripos($body, '?entryPoint=unsubscribe') === false) {
                    if ($emailTemplateData['isHtml']) {
                        $body .= "<br><br>" . $optOutLink;
                    } else {
                        $body .= "\n\n" . $optOutUrl;
                    }
                }

                $message->getHeaders()->addHeaderLine('List-Unsubscribe', '<' . $optOutUrl . '>');
            }
        }

        $emailData = [
            'from' => $fromEmailAddress,
            'to' => $toEmailAddress,
            'subject' => $subject,
            'body' => $body,
            'isHtml' => $emailTemplateData['isHtml'],
            'parentId' => $entity->id,
            'parentType' => $entity->getEntityType(),
            'createdById' => 'system',
        ];

        if ($replyToEmail) {
            $emailData['replyTo'] = $replyToEmail;
        }

        if (isset($fromName)) {
            $emailData['fromName'] = $fromName;
        }

        $email = $entityManager->getEntity('Email');
        $email->set($emailData);

        $attachmentList = [];
        if (!empty($emailTemplateData['attachmentsIds'])) {
            foreach ($emailTemplateData['attachmentsIds'] as $attachmentId) {
                $attachment = $entityManager->getEntity('Attachment', $attachmentId);
                if (isset($attachment)) {
                    $attachmentList[] = $attachment;
                }
            }

            if (!$data->doNotStore) {
                $email->set('attachmentsIds', $emailTemplateData['attachmentsIds']);
            }
        }

        $isSent = false;

        $sender = $this->getMailSender();
        $smtpParams = null;

        if (isset($data->from->entityType) && $data->from->entityType == 'User' && isset($data->from->entityId)) {
            $smtpParams = $this->getUserSmtpParams($fromEmailAddress, $data->from->entityId);
        } else {
            if (isset($data->from->email)) {
                $smtpParams = $this->getGroupSmtpParams($fromEmailAddress);
            }
        }

        if ($smtpParams) {
            $sender->useSmtp($smtpParams);
        }

        $sendExceptionMessage = null;

        try {
            $sender->send($email, [], $message, $attachmentList);
        }
        catch (Exception $e) {
            $sendExceptionMessage = $e->getMessage();
        }

        if (isset($sendExceptionMessage)) {
            throw new Error('Workflow['.$data->workflowId.'][sendEmail]: '.$sendExceptionMessage.'.');
        }

        if (!$data->doNotStore) {
            $processId = $data->processId ?? null;

            $teamsIds = [];
            if ($processId) {
                $process = $this->getEntityManager()->getEntity('BpmnProcess', $processId);

                if ($process) {
                    $teamsIds = $process->getLinkMultipleIdList('teams');
                }
            } else {
                $emailTemplate = $this->getEntityManager()->getEntity('EmailTemplate', $data->emailTemplateId);

                if ($emailTemplate) {
                    $teamsIds = $emailTemplate->getLinkMultipleIdList('teams');
                }
            }
            if (count($teamsIds)) {
                $email->set('teamsIds', $teamsIds);
            }

            $entityManager->saveEntity($email, ['skipCreatedBy' => true]);

            if (!empty($data->returnEmailId)) {
                return $email->id;
            }
        }

        return true;
    }

    protected function applyTrackingUrlsToEmailBody(string $body, string $toEmailAddress) : string
    {
        $siteUrl = $this->getConfig()->get('siteUrl');

        $hasher = $this->getInjection('container')->get('hasher');

        if (!$hasher) {
            return $body;
        }

        if (strpos($body, '{trackingUrl:') === false) {
            return $body;
        }

        $hash = $hasher->hash($toEmailAddress);

        preg_match_all('/\{trackingUrl:(.*?)\}/', $body, $matches);

        if (!$matches || !count($matches)) {
            return $body;
        }

        foreach ($matches[0] as $item) {
            $id = explode(':', trim($item, '{}'), 2)[1] ?? null;

            if (!$id) {
                continue;
            }

            if (strpos($id, '.')) {
                list($id, $uid) = explode('.', $id);

                $uidHash = $hasher->hash($uid);

                $url = $siteUrl .
                    '?entryPoint=campaignUrl&id=' . $id . '&uid=' . $uid . '&hash=' . $uidHash;

            } else {
                $url = $siteUrl .
                    '?entryPoint=campaignUrl&id=' . $id . '&emailAddress=' . $toEmailAddress . '&hash=' . $hash;
            }

            $body = str_replace($item, $url, $body);
        }

        return $body;
    }

    public function jobTriggerWorkflow($data)
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        if (empty($data->entityId) || empty($data->entityType) || empty($data->nextWorkflowId)) {
            throw new Error('Workflow['.$data->workflowId.'][triggerWorkflow]: Not sufficient job data.');
        }

        $entityId = $data->entityId;
        $entityType = $data->entityType;

        $entity = $this->getEntityManager()->getEntity($entityType, $entityId);

        if (!$entity) {
            throw new Error('Workflow['.$data->workflowId.'][triggerWorkflow]: Empty job data.');
        }

        if (is_array($data->values)) {
            $data->values = (object) $data->values;
        }

        if (is_object($data->values)) {
            $values = get_object_vars($data->values);
            foreach ($values as $attribute => $value) {
                $entity->setFetched($attribute, $value);
            }
        }

        $this->triggerWorkflow($entity, $data->nextWorkflowId);

        return true;
    }

    public function triggerWorkflow($entity, $workflowId)
    {
        $workflow = $this->getEntityManager()->getEntity('Workflow', $workflowId);
        if (!$workflow) return;

        if (!$workflow->get('isActive')) {
            return;
        }

        $workflowManager = $this->getInjection('container')->get('workflowManager');

        if ($workflowManager->checkConditions($workflow, $entity)) {
            $workflowLogRecord = $this->getEntityManager()->getEntity('WorkflowLogRecord');
            $workflowLogRecord->set(array(
                'workflowId' => $workflowId,
                'targetId' => $entity->id,
                'targetType' => $entity->getEntityType()
            ));
            $this->getEntityManager()->saveEntity($workflowLogRecord);

            $workflowManager->runActions($workflow, $entity);
        }
    }

    /**
     * Validate sendEmail data
     *
     * @param  object  $data
     * @return bool
     */
    protected function validateSendEmailData($data)
    {
        if (!isset($data->entityId) || !(isset($data->entityType)) ||
         !isset($data->emailTemplateId) || !isset($data->from) || !isset($data->to)
            ) {
            return false;
        }

        return true;
    }

    /**
     * Get email address depends on inputs
     * @param  object $data
     * @return string
     */
    protected function getEmailAddress($data)
    {
        if (isset($data->email)) {
            return $data->email;
        }

        if (isset($data->entityType)) {
            $entityType = $data->entityType;
        } else if (isset($data->entityName)) {
            $entityType = $data->entityName;
        }

        $entity = null;
        if (isset($entityType) && isset($data->entityId)) {
            $entity = $this->getEntityManager()->getEntity($entityType, $data->entityId);
        }

        if (isset($data->type)) {
            $workflowHelper = $this->getWorkflowHelper();

            switch ($data->type) {
                case 'specifiedTeams':
                    $userIds = $workflowHelper->getUserIdsByTeamIds($data->entityIds);
                    return implode('; ', $workflowHelper->getUsersEmailAddress($userIds));
                    break;

                case 'teamUsers':
                    if (!$entity) return;
                    $entity->loadLinkMultipleField('teams');
                    $userIds = $workflowHelper->getUserIdsByTeamIds($entity->get('teamsIds'));
                    return implode('; ', $workflowHelper->getUsersEmailAddress($userIds));
                    break;

                case 'followers':
                    if (!$entity) return;
                    $userIds = $workflowHelper->getFollowerUserIds($entity);
                    return implode('; ', $workflowHelper->getUsersEmailAddress($userIds));
                    break;

                case 'followersExcludingAssignedUser':
                    if (!$entity) return;
                    $userIds = $workflowHelper->getFollowerUserIdsExcludingAssignedUser($entity);
                    return implode('; ', $workflowHelper->getUsersEmailAddress($userIds));
                    break;

                case 'system':
                    return $this->getInjection('config')->get('outboundEmailFromAddress');
                    break;

                case 'specifiedUsers':
                    return implode('; ', $workflowHelper->getUsersEmailAddress($data->entityIds));
                    break;

                case 'specifiedContacts':
                    return implode('; ', $workflowHelper->getEmailAddressesForEntity('Contact', $data->entityIds));
                    break;
            }
        }

        if ($entity && $entity instanceof Entity && $entity->hasAttribute('emailAddress')) {
            return $entity->get('emailAddress');
        }

        if (
            isset($data->type) && isset($entityType) &&
            isset($data->entityIds) && is_array($data->entityIds)
        ) {
            return implode('; ', $workflowHelper->getEmailAddressesForEntity($entityType, $data->entityIds));
        }
    }

    protected function getGroupSmtpParams($emailAddress)
    {
        $inboundEmail = $this->getEntityManager()->getRepository('InboundEmail')->where([
            'status' => 'Active',
            'useSmtp' => true,
            'smtpHost!=' => null,
            'emailAddress' => $emailAddress,
        ])->findOne();

        if (!$inboundEmail) return null;

        $smtpParams = $this->getServiceFactory()->create('InboundEmail')->getSmtpParamsFromAccount($inboundEmail);
        return $smtpParams;
    }

    protected function getUserSmtpParams($emailAddress, $userId)
    {
        $smtpParams = null;

        $user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$user || !$user->get('isActive')) return null;

        $emailAccount = $this->getEntityManager()->getRepository('EmailAccount')->where([
            'emailAddress' => $emailAddress,
            'assignedUserId' => $userId,
            'useSmtp' => true,
            'status' => 'Active',
        ])->findOne();

        if ($emailAccount) {
            $smtpParams = $this->getServiceFactory()->create('EmailAccount')->getSmtpParamsFromAccount($emailAccount);
        }

        if (!$smtpParams) {
            $preferences = $this->getEntityManager()->getEntity('Preferences', $userId);
            if ($preferences) {
                $smtpParams = $preferences->getSmtpParams();
                if (array_key_exists('password', $smtpParams)) {
                    $smtpParams['password'] = $this->getCrypt()->decrypt($smtpParams['password']);
                }
            }
        }

        if ($smtpParams) {
            $smtpParams['fromName'] = $user->get('name');
        }

        return $smtpParams ?? null;
    }

    public function runScheduledWorkflow($data): void
    {
        if (is_array($data)) {
            $data = (object) $data;
        }

        if (empty($data->workflowId)) {
            throw new Error();
        }

        $entityManager = $this->getEntityManager();

        $workflow = $entityManager->getEntity('Workflow', $data->workflowId);

        if (!$workflow instanceof Entity) {
            throw new Error('Workflow['.$data->workflowId.'][runScheduledWorkflow]: Entity is not found.');
        }

        if (!$workflow->get('isActive')) {
            return;
        }

        $targetReport = $workflow->get('targetReport');

        if (!$targetReport instanceof Entity) {
            throw new Error(
                'Workflow['.$data->workflowId.'][runScheduledWorkflow]: ' .
                'TargetReport Entity is not found.'
            );
        }

        $reportService = $this->getServiceFactory()->create('Report');

        $result = $reportService->run(
            $targetReport->get('id'),
            null,
            null,
            [
                'returnSthCollection' => true,
            ]
        );

        $jobEntity = $entityManager->getEntity('Job');

        $collection = $result['collection'] ?? null;

        if (!$collection || !is_object($collection)) {
            throw new Error("Scheduled Workflow: Bad report result.");
        }

        foreach ($collection as $collectionEntity) {
            $runData = [
                'workflowId' => $workflow->get('id'),
                'entityType' => $collectionEntity->getEntityType(),
                'entityId' => $collectionEntity->get('id'),
            ];

            try {
                $this->runScheduledWorkflowForEntity($runData);
            }
            catch (Exception $e) {
                $job = clone $jobEntity;

                $job->set([
                    'serviceName' => 'Workflow',
                    'method' => 'runScheduledWorkflowForEntity',
                    'methodName' => 'runScheduledWorkflowForEntity',
                    'data' => $runData,
                    'executeTime' => date('Y-m-d H:i:s'),
                ]);

                $entityManager->saveEntity($job);
            }
        }
    }

    public function runScheduledWorkflowForEntity($data): void
    {
        $entityManager = $this->getEntityManager();

        if (is_array($data)) {
            $data = (object) $data;
        }

        if (empty($data->entityType)) {
            $data->entityType = $data->entityName;
        }

        $entity = $entityManager->getEntity($data->entityType, $data->entityId);

        if (!$entity instanceof Entity) {
            throw new Error(
                'Workflow['.$data->workflowId.'][runActions]: ' .
                'Entity['.$data->entityType.'] ['.$data->entityId.'] is not found.'
            );
        }

        $this->triggerWorkflow($entity, $data->workflowId);
    }
}

