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

namespace Espo\Modules\Advanced\Core\Workflow\Actions;

use Espo\Core\Exceptions\Error;
use Espo\Modules\Advanced\Core\Workflow\Utils;

use Espo\ORM\Entity;

class SendEmail extends Base
{
    protected function run(Entity $entity, $actionData)
    {
        $jobData = [
            'workflowId' => $this->getWorkflowId(),
            'entityId' => $this->getEntity()->get('id'),
            'entityType' => $this->getEntity()->getEntityType(),
            'from' => $this->getEmailAddress('from'),
            'to' => $this->getEmailAddress('to'),
            'replyTo' => $this->getEmailAddress('replyTo'),
            'emailTemplateId' => isset($actionData->emailTemplateId) ? $actionData->emailTemplateId : null,
            'doNotStore' => isset($actionData->doNotStore) ? $actionData->doNotStore : false,
            'optOutLink' => $actionData->optOutLink ?? false,
        ];

        if ($this->bpmnProcess) {
            $jobData['processId'] = $this->bpmnProcess->id;
        }

        if (is_null($jobData['to'])) {
            return true;
        }

        if (!empty($actionData->processImmediately)) {
            $storeSentEmailData = !!$this->createdEntitiesData && !$jobData['doNotStore'];
            if ($storeSentEmailData) {
                $jobData['returnEmailId'] = true;
            }

            $variables = null;
            if ($this->hasVariables()) {
                $variables = $this->getVariables();
                $jobData['variables'] = $variables;
            }

            $emailId = $this->getContainer()->get('serviceFactory')->create('Workflow')->sendEmail($jobData);

            if ($storeSentEmailData && $emailId && isset($actionData->elementId)) {
                $alias = $actionData->elementId;
                $this->createdEntitiesData->$alias = (object) [
                    'entityType' => 'Email',
                    'entityId' => $emailId,
                ];
            }

            return true;
        }

        $job = $this->getEntityManager()->getEntity('Job');
        $job->set([
            'serviceName' => 'Workflow',
            'method' => 'sendEmail',
            'methodName' => 'sendEmail',
            'data' => $jobData,
            'executeTime' => $this->getExecuteTime($actionData),
            'queue' => 'e0'
        ]);

        $this->getEntityManager()->saveEntity($job);

        return true;
    }

    protected function getEmailAddress($type = 'to', $defaultReturn = null)
    {
        $data = $this->getActionData();

        $fieldValue = null;
        if (property_exists($data, $type)) {
            $fieldValue = $data->$type;
        }

        $returnData = null;

        switch ($fieldValue) {
            case 'specifiedEmailAddress':
                $address = $data->{$type . 'Email'};
                if ($address && strpos($address, '{$$') !== false && $this->hasVariables()) {
                    $variables = $this->getVariables() ?? (object) [];
                    foreach (get_object_vars($variables) as $key => $v) {
                        if ($v && is_string($v)) {
                            $address = str_replace('{$$'.$key.'}', $v, $address);
                        }
                    }
                }
                $returnData = [
                    'email' => $address,
                    'type' => $fieldValue,
                ];
                break;
            case 'processAssignedUser':
                if (!$this->bpmnProcess) {
                    return;
                }
                if (!$this->bpmnProcess->get('assignedUserId')) {
                    return;
                }
                return [
                    'entityType' => 'User',
                    'entityId' => $this->bpmnProcess->get('assignedUserId'),
                    'type' => $fieldValue
                ];
            case 'targetEntity':
                $entity = $this->getEntity();
                return array(
                    'entityType' => $entity->getEntityType(),
                    'entityId' => $entity->id,
                    'type' => $fieldValue
                );
            case 'teamUsers':
            case 'followers':
            case 'followersExcludingAssignedUser':
                $entity = $this->getEntity();

                $returnData = array(
                    'entityType' => $entity->getEntityType(),
                    'entityId' => $entity->get('id'),
                    'type' => $fieldValue,
                );
                break;

            case 'specifiedTeams':
            case 'specifiedUsers':
            case 'specifiedContacts':
                $speicifiedEntityType = null;
                if ($fieldValue === 'specifiedTeams') {
                    $speicifiedEntityType = 'Team';
                }
                if ($fieldValue === 'specifiedUsers') {
                    $speicifiedEntityType = 'User';
                }
                if ($fieldValue === 'specifiedContacts') {
                    $speicifiedEntityType = 'Contact';
                }
                $returnData = array(
                    'type' => $fieldValue,
                    'entityIds' => $data->{$type . 'SpecifiedEntityIds'},
                    'entityType' => $speicifiedEntityType
                );
                break;

            case 'currentUser':
                $returnData = array(
                    'entityType' => $this->getContainer()->get('user')->getEntityType(),
                    'entityId' => $this->getContainer()->get('user')->get('id'),
                    'type' => $fieldValue,
                );
                break;

            case 'system':
                $returnData = array(
                    'type' => $fieldValue,
                );
                break;

            case 'fromOrReplyTo':
                $entity = $this->getEntity();
                $emailAddress = null;
                $this->getEntityManager()->getRepository('Email')->loadFromField($entity);
                if ($entity->has('replyToString') && $entity->get('replyToString')) {
                    $replyTo = $entity->get('replyToString');
                    $arr = explode(';', $replyTo);
                    $emailAddress = $arr[0];
                    preg_match_all("/[\._a-zA-Z0-9-]+@[\._a-zA-Z0-9-]+/i", $emailAddress, $matches);
                    if (empty($matches[0])) {
                        break;
                    }
                    $emailAddress = $matches[0][0];
                } else if ($entity->has('from') && $entity->get('from')) {
                    $emailAddress = $entity->get('from');
                }
                if ($emailAddress) {
                    $returnData = array(
                        'type' => $fieldValue,
                        'email' => $emailAddress
                    );
                }
                break;

            default:
                $entity = $this->getEntity();
                if (strpos($fieldValue, 'link:') === 0) {
                    $fieldValue = substr($fieldValue, 5);
                }

                $link = $fieldValue;

                if (empty($fieldValue)) break;

                $e = $entity;

                if (strpos($link, '.')) {
                    list ($firstLink, $link) = explode('.', $link);
                    if (!$entity->hasRelation($firstLink) && ($entity->getRelationType($firstLink) === 'belongsTo' || $entity->getRelationType($firstLink) === 'belongsToParent')) {
                        break;
                    }
                    $e = $entity->get($firstLink);
                    if (!$e) break;
                }

                if ($link === 'followers') {
                    $idList = $this->getServiceFactory()->create('Stream')->getEntityFolowerIdList($e);
                    $returnData = array(
                        'entityType' => 'User',
                        'entityIds' => $idList,
                        'type' => 'link:' . $fieldValue
                    );
                    return $returnData;
                }

                if (
                    $e->hasRelation($link)
                    &&
                    ($e->getRelationType($link) === 'hasMany' || $e->getRelationType($link) === 'manyMany')
                    &&
                    $e->hasLinkMultipleField($link)
                    &&
                    $e->getRelationParam($link, 'entity')
                ) {
                    $idList = $e->getLinkMultipleIdList($link);
                    if (!empty($idList)) {
                        $returnData = array(
                            'entityType' => $e->getRelationParam($link, 'entity'),
                            'entityIds' => $idList,
                            'type' => 'link:' . $fieldValue
                        );
                        return $returnData;
                    }
                    break;
                }

                $fieldEntity = Utils::getFieldValue($e, $link, true, $this->getEntityManager());
                if ($fieldEntity instanceof \Espo\ORM\Entity) {
                    if (
                        $fieldEntity->hasAttribute('emailAddress')
                        &&
                        (
                            $fieldEntity->getAttributeType('emailAddress') === 'email'
                            ||
                            $fieldEntity->getAttributeParam('emailAddress', 'fieldType') === 'email'
                        )
                    ) {
                        $returnData = array(
                            'entityType' => $fieldEntity->getEntityType(),
                            'entityId' => $fieldEntity->get('id'),
                            'type' => 'link:' . $fieldValue
                        );
                    }
                }
                break;
        }

        return (isset($returnData)) ? $returnData : $defaultReturn;
    }
}
