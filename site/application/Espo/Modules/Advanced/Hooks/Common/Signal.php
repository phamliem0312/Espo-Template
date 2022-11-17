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

namespace Espo\Modules\Advanced\Hooks\Common;

use Espo\ORM\Entity;

class Signal extends \Espo\Core\Hooks\Base
{
    public static $order = 100;

    protected $ignoreEntityTypeList = [
        'Notification',
        'EmailAddress',
        'PhoneNumber',
    ];

    protected $ignoreRegularEntityTypeList = [
        'Note',
    ];

    protected function init()
    {
        $this->addDependency('signalManager');
        $this->addDependency('config');
        $this->addDependency('metadata');
    }

    public function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        if ($this->getInjection('config')->get('signalCrudHooksDisabled')) {
            return;
        }

        if (in_array($entity->getEntityType(), $this->ignoreEntityTypeList)) {
            return;
        }

        $ignoreRegular = in_array($entity->getEntityType(), $this->ignoreRegularEntityTypeList);

        $signalManager = $this->getInjection('signalManager');

        if ($entity->isNew()) {
            $signalManager->trigger('@create', $entity, $options);

            if (!$ignoreRegular) {
                $signalManager->trigger(['create', $entity->getEntityType()]);
            }

            if ($entity->getEntityType() === 'Note' && $entity->get('type') === 'Post') {
                $parentId = $entity->get('parentId');
                $parentType = $entity->get('parentType');

                if ($parentType && $parentId) {
                    $signalManager->trigger(['streamPost', $parentType, $parentId]);
                }
            }
        }
        else {
            $signalManager->trigger('@update', $entity, $options);

            if (!$ignoreRegular) {
                $signalManager->trigger(['update', $entity->getEntityType(), $entity->id]);
            }
        }

        if ($ignoreRegular) {
            return;
        }

        foreach ($entity->getRelationList() as $relation) {
            $type = $entity->getRelationType($relation);

            if ($type === 'belongsToParent' && $entity->isNew()) {
                $parentId = $entity->get($relation . 'Id');
                $parentType = $entity->get($relation . 'Type');

                if (!$parentType || !$parentId) {
                    continue;
                }

                if (!$this->getInjection('metadata')->get(['scopes', $parentType, 'object'])) {
                    continue;
                }

                $signalManager->trigger(['createChild', $parentType, $parentId, $entity->getEntityType()]);

                continue;
            }

            if ($type === 'belongsTo') {
                $idAttribute = $relation . 'Id';
                $idValue = $entity->get($idAttribute);

                $relate = true;
                $unrelate = false;

                $prevIdValue = null;

                if (!$entity->isNew()) {
                    if (!$entity->isAttributeChanged($idAttribute)) {
                        continue;
                    }

                    if (!$idValue) {
                        $relate = false;
                    }

                    $prevIdValue = $entity->getFetched($idAttribute);

                    if ($prevIdValue) {
                        $unrelate = true;
                    }
                }
                else {
                    if (!$idValue) {
                        continue;
                    }
                }

                $foreignEntityType = $entity->getRelationParam($relation, 'entity');
                $foreign = $entity->getRelationParam($relation, 'foreign');

                if (!$foreignEntityType) {
                    continue;
                }

                if (!$foreign) {
                    continue;
                }

                if (in_array($foreignEntityType, ['User', 'Team'])) {
                    continue;
                }

                if (!$this->getInjection('metadata')->get(['scopes', $foreignEntityType, 'object'])) {
                    continue;
                }

                if ($entity->isNew()) {
                    $signalManager->trigger(['createRelated', $foreignEntityType, $idValue, $foreign]);
                }

                continue; // skip

                if ($relate) {
                    if (!$entity->isNew()) {
                        $signalManager->trigger(['relate', $foreignEntityType, $idValue, $foreign, $entity->id]);
                        $signalManager->trigger(['relate', $foreignEntityType, $idValue, $foreign]);
                    }
                }

                if ($unrelate) {
                    $signalManager->trigger(['unrelate', $foreignEntityType, $prevIdValue, $foreign, $entity->id]);
                    $signalManager->trigger(['unrelate', $foreignEntityType, $prevIdValue, $foreign]);
                }

                continue;
            }
        }
    }

    public function afterRemove(Entity $entity, array $options = [])
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        if ($this->getInjection('config')->get('signalCrudHooksDisabled')) {
            return;
        }

        if (in_array($entity->getEntityType(), $this->ignoreEntityTypeList)) {
            return;
        }

        $ignoreRegular = in_array($entity->getEntityType(), $this->ignoreRegularEntityTypeList);

        $signalManager = $this->getInjection('signalManager');

        $signalManager->trigger('@delete', $entity, $options);

        if (!$ignoreRegular) {
            $signalManager->trigger(['delete', $entity->getEntityType(), $entity->id]);
        }
    }

    public function afterRelate(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        if ($this->getInjection('config')->get('signalCrudHooksDisabled')) {
            return;
        }

        if (in_array($entity->getEntityType(), $this->ignoreEntityTypeList)) {
            return;
        }

        $ignoreRegular = in_array($entity->getEntityType(), $this->ignoreRegularEntityTypeList);

        if ($entity->isNew()) {
            return;
        }

        $signalManager = $this->getInjection('signalManager');

        $foreign = $hookData['foreignEntity'] ?? null;
        $link = $hookData['relationName'] ?? null;

        if (!$foreign || !$link) {
            return;
        }

        $foreignId = $foreign->id;

        $relationType = $entity->getRelationParam($link, 'type');

        if ($relationType !== 'manyMany') {
            $ignoreRegular = true;
        }

        $signalManager->trigger(['@relate', $link, $foreignId], $entity, $options);
        $signalManager->trigger(['@relate', $link], $entity, $options);

        if (!$ignoreRegular) {
            $signalManager->trigger(['relate', $entity->getEntityType(), $entity->id, $link, $foreignId]);
            $signalManager->trigger(['relate', $entity->getEntityType(), $entity->id, $link]);
        }

        $foreignLink = $entity->getRelationParam($link, 'foreign');

        if ($foreignLink) {
            $signalManager->trigger(['@relate', $foreignLink, $entity->id], $foreign);
            $signalManager->trigger(['@relate', $foreignLink], $foreign);

            if (!$ignoreRegular) {
                $signalManager->trigger(['relate', $foreign->getEntityType(), $foreign->id, $foreignLink, $entity->id]);
                $signalManager->trigger(['relate', $foreign->getEntityType(), $foreign->id, $foreignLink]);
            }
        }
    }

    public function afterUnrelate(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        if ($this->getInjection('config')->get('signalCrudHooksDisabled')) {
            return;
        }

        if (in_array($entity->getEntityType(), $this->ignoreEntityTypeList)) {
            return;
        }

        $ignoreRegular = in_array($entity->getEntityType(), $this->ignoreRegularEntityTypeList);

        if ($entity->isNew()) {
            return;
        }

        $signalManager = $this->getInjection('signalManager');

        $foreign = $hookData['foreignEntity'] ?? null;
        $link = $hookData['relationName'] ?? null;

        if (!$foreign || !$link) {
            return;
        }

        $foreignId = $foreign->id;

        $relationType = $entity->getRelationParam($link, 'type');

        if ($relationType !== 'manyMany') {
            $ignoreRegular = true;
        }

        $signalManager->trigger(['@unrelate', $link, $foreignId], $entity, $options);
        $signalManager->trigger(['@unrelate', $link], $entity, $options);

        if (!$ignoreRegular) {
            $signalManager->trigger(['unrelate', $entity->getEntityType(), $entity->id, $link, $foreignId]);
            $signalManager->trigger(['unrelate', $entity->getEntityType(), $entity->id, $link]);
        }

        $foreignLink = $entity->getRelationParam($link, 'foreign');

        if ($foreignLink) {
            $signalManager->trigger(['@unrelate', $foreignLink, $entity->id], $foreign);
            $signalManager->trigger(['@unrelate', $foreignLink], $foreign);

            $signalManager->trigger(['unrelate', $foreign->getEntityType(), $foreign->id, $foreignLink, $entity->id]);
            $signalManager->trigger(['unrelate', $foreign->getEntityType(), $foreign->id, $foreignLink]);
        }
    }

    public function afterMassRelate(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        if ($this->getInjection('config')->get('signalCrudHooksDisabled')) {
            return;
        }

        if (in_array($entity->getEntityType(), $this->ignoreEntityTypeList)) {
            return;
        }

        $link = $hookData['relationName'] ?? null;

        if (!$link) {
            return;
        }

        $signalManager = $this->getInjection('signalManager');

        $signalManager->trigger(['@relate', $link], $entity, $options);
        $signalManager->trigger(['relate', $entity->getEntityType(), $entity->id, $link]);
    }

    public function afterLeadCapture(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        if ($entity->getEntityType() == 'LeadCapture') {
            return;
        }

        $id = $hookData['leadCaptureId'];

        $signalManager = $this->getInjection('signalManager');

        $signalManager->trigger(['@leadCapture', $id], $entity);
        $signalManager->trigger(['@leadCapture'], $entity);

        $signalManager->trigger(['leadCapture', $entity->getEntityType(), $entity->id, $id]);
        $signalManager->trigger(['leadCapture', $entity->getEntityType(), $entity->id]);
    }

    public function afterConfirmation(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        $eventEntityType = $entity->getEntityType();
        $eventId = $entity->id;
        $status = $hookData['status'];
        $entityType = $hookData['inviteeType'];
        $id = $hookData['inviteeId'];

        $signalManager = $this->getInjection('signalManager');

        if ($status === 'Accepted') {
            $signalManager->trigger(['eventAccepted', $entityType, $id, $eventEntityType, $eventId]);
            $signalManager->trigger(['eventAccepted', $entityType, $id, $eventEntityType]);
        }
        if ($status === 'Tentative') {
            $signalManager->trigger(['eventTentative', $entityType, $id, $eventEntityType, $eventId]);
            $signalManager->trigger(['eventTentative', $entityType, $id, $eventEntityType]);
        }
        if ($status === 'Declined') {
            $signalManager->trigger(['eventDeclined', $entityType, $id, $eventEntityType, $eventId]);
            $signalManager->trigger(['eventDeclined', $entityType, $id, $eventEntityType]);
        }

        if ($status === 'Accepted' || $status === 'Tentative') {
            $signalManager->trigger(['eventAcceptedTentative', $entityType, $id, $eventEntityType, $eventId]);
            $signalManager->trigger(['eventAcceptedTentative', $entityType, $id, $eventEntityType]);
        }
    }

    public function afterOptOut(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        $signalManager = $this->getInjection('signalManager');

        $signalManager->trigger(implode('.', ['@optOut']), $entity);

        $signalManager->trigger(implode('.', ['optOut', $entity->getEntityType(), $entity->id]));
    }

    public function afterCancelOptOut(Entity $entity, array $options, array $hookData)
    {
        if (!empty($options['skipWorkflow'])) {
            return;
        }

        if (!empty($options['skipSignal'])) {
            return;
        }

        if (!empty($options['silent'])) {
            return;
        }

        $signalManager = $this->getInjection('signalManager');

        $signalManager->trigger(implode('.', ['@cancelOptOut']), $entity);

        $signalManager->trigger(implode('.', ['cancelOptOut', $entity->getEntityType(), $entity->id]));
    }
}
