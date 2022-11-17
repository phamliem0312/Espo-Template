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

use Espo\ORM\Entity;

use Espo\Core\Utils\Util;

class CreateRelatedEntity extends CreateEntity
{
    protected function run(Entity $entity, $actionData)
    {
        $entityManager = $this->getEntityManager();

        $linkEntityName = $this->getLinkEntityName($entity, $actionData->link);

        if (!isset($linkEntityName)) {
            $GLOBALS['log']->error(
                'Workflow\Actions\\'.$actionData->type.': ' .
                'Cannot find an entity type of the link ['.$actionData->link.'] in the entity ' .
                '[' . $entity->getEntityType() . '].'
            );

            return false;
        }

        $newEntity = $entityManager->getEntity($linkEntityName);

        $data = $this->getDataToFill($newEntity, $actionData->fields);

        $newEntity->set($data);

        $link = $actionData->link;

        $linkType = $entity->getRelationParam($link, 'type');

        $isRelated = false;

        $foreignLink = $entity->getRelationParam($link, 'foreign');

        if ($foreignLink = $entity->getRelationParam($link, 'foreign')) {
            $foreignRelationType = $newEntity->getRelationType($foreignLink);

            if (in_array($foreignRelationType, ['belongsTo', 'belongsToParent'])) {
                if ($foreignRelationType === 'belongsTo') {
                    $newEntity->set($foreignLink. 'Id', $entity->id);
                    $isRelated = true;
                }
                else if ($foreignRelationType === 'belongsToParent') {
                    $newEntity->set($foreignLink. 'Id', $entity->id);
                    $newEntity->set($foreignLink. 'Type', $entity->getEntityType());
                    $isRelated = true;
                }
            }
        }

        if (!$newEntity->has('createdById')) {
            $newEntity->set('createdById', 'system');
        }

        $newEntity->set('id', Util::generateId());

        if (!empty($actionData->formula)) {
            $this->getFormulaManager()->run($actionData->formula, $newEntity, $this->getFormulaVariables());
        }

        $entityManager->saveEntity($newEntity, [
            'skipCreatedBy' => true,
            'workflowId' => $this->getWorkflowId(),
            'createdById' => $newEntity->get('createdById') ?? 'system',
        ]);

        $newEntityId = $newEntity->get('id');

        if (!empty($newEntityId)) {
            $newEntity = $entityManager->getEntity($newEntity->getEntityType(), $newEntityId);

            if (!$isRelated && $linkType === 'belongsTo') {
                $entity->set($link . 'Id', $newEntity->id);
                $entity->set($link . 'Name', $newEntity->get('name'));

                $this->getEntityManager()->saveEntity($entity, [
                    'skipWorkflow' => true,
                    'noStream' => true,
                    'noNotifications' => true,
                    'skipModifiedBy' => true,
                    'skipCreatedBy' => true,
                    'skipHooks' => true,
                    'silent' => true,
                ]);

                $isRelated = true;
            }

            if (!$isRelated) {
                $entityManager
                    ->getRepository($entity->getEntityType())
                    ->relate($entity, $actionData->link, $newEntity);
            }

            if ($this->createdEntitiesData && !empty($actionData->elementId) && !empty($actionData->id)) {
                $this->createdEntitiesDataIsChanged = true;

                $alias = $actionData->elementId . '_' . $actionData->id;

                $this->createdEntitiesData->$alias = (object) [
                    'entityType' => $newEntity->getEntityType(),
                    'entityId' => $newEntity->id
                ];
            }
        }

        return !empty($newEntityId) ? true: false;
    }

    /**
     * Get an Entity name of a link.
     *
     * @param Espo\ORM\Entity $entity
     * @param string $linkName
     *
     * @return string|null
     */
    protected function getLinkEntityName(Entity $entity, $linkName)
    {
        $linkEntity = $entity->get($linkName);

        if ($linkEntity instanceof Entity) {
            return $linkEntity->getEntityType();
        }

        if (isset($linkName) && $entity->hasRelation($linkName)) {
            return $entity->getRelationParam($linkName, 'entity');
        }

        return null;
    }
}
