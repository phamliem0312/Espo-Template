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

abstract class BaseEntity extends Base
{
    protected $entityDefsList = [];

    protected function getEntityDefs($entityType)
    {
        if (!isset($this->entityDefsList[$entityType])) {
            $this->entityDefsList[$entityType] = $this->getMetadata()->get('entityDefs.' . $entityType);
        }

        return $this->entityDefsList[$entityType];
    }

    /**
     * Get value of a field by $fieldName
     *
     * @param  string $fieldName
     * @param  \Espo\Orm\Entity $filledEntity
     * @return mixed
     */
    protected function getValue($fieldName, \Espo\Orm\Entity $filledEntity = null)
    {
        $entityHelper = $this->getEntityHelper();

        $actionData = $this->getActionData();
        $entity = $this->getEntity();

        if (isset($actionData->fields->$fieldName)) {
            $fieldParams = $actionData->fields->$fieldName;

            switch ($fieldParams->subjectType) {
                case 'value':
                    if (isset($fieldParams->attributes) && is_object($fieldParams->attributes)) {
                        $filledEntity = isset($filledEntity) ? $filledEntity : $entity;
                        $fieldValue = $fieldParams->attributes;
                    }

                    break;

                case 'field':
                    $filledEntity = isset($filledEntity) ? $filledEntity : $entity;
                    $fieldValue = $entityHelper->getFieldValues($entity, $filledEntity, $fieldParams->field, $fieldName);

                    if (isset($fieldParams->shiftDays)) {
                        $shiftUnit = 'days';

                        if (!empty($fieldParams->shiftUnit)) {
                            $shiftUnit = $fieldParams->shiftUnit;
                        }

                        if (!in_array($shiftUnit, ['hours', 'minutes', 'days', 'months'])) {
                            $shiftUnit = 'days';
                        }

                        foreach ($fieldValue as $attribute => $value) {
                            $fieldValue->$attribute = Utils::shiftDays(
                                $fieldParams->shiftDays,
                                $value,
                                $filledEntity->getAttributeType($attribute),
                                $shiftUnit,
                                null,
                                $this->getConfig()->get('timeZone')
                            );
                        }
                    }

                    break;

                case 'today':
                    $filledFieldType = null;

                    if (isset($filledEntity)) {
                        $filledFieldType = Utils::getFieldType($filledEntity, $fieldName);
                    }

                    $shiftUnit = 'days';

                    if (!empty($fieldParams->shiftUnit)) {
                        $shiftUnit = $fieldParams->shiftUnit;
                    }

                    if (!in_array($shiftUnit, ['hours', 'minutes', 'days', 'months'])) {
                        $shiftUnit = 'days';
                    }

                    return Utils::shiftDays(
                        $fieldParams->shiftDays,
                        null,
                        $filledFieldType,
                        $shiftUnit,
                        null,
                        $this->getConfig()->get('timeZone')
                    );

                    break;

                default:
                    throw new Error(
                        'Workflow['.$this->getWorkflowId().']: Unknown fieldName for a field [' . $fieldName . ']'
                    );
            }
        }

        return $fieldValue;
    }

    protected function shiftDate($shiftDays, $filledFieldType, $shiftUnit)
    {
    }

    /**
     * Get data to fill.
     *
     * @return array
     */
    protected function getDataToFill(Entity $entity, $fields)
    {
        $data = [];

        if (empty($fields)) {
            return $data;
        }

        $metadataFields = $this->getMetadata()->get(['entityDefs', $entity->getEntityType(), 'fields']);
        $metadataFieldList = array_keys($metadataFields);

        foreach ($fields as $fieldName => $fieldParams) {
            if ($entity->hasLinkMultipleField($fieldName)) {
                $copiedIdList = [];
                $idListFieldName = $fieldName . 'Ids';

                if ($this->getMetadata()->get(
                    ['entityDefs', $entity->getEntityType(), 'fields', $fieldName, 'type']) == 'attachmentMultiple'
                ) {
                    $attachmentData = $this->getValue($fieldName, $entity);

                    if (!empty($attachmentData) && is_array($attachmentData->$idListFieldName)) {
                        foreach ($attachmentData->$idListFieldName as $attachmentId) {
                            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);

                            if ($attachment) {
                                $attachment = $this->getEntityManager()
                                    ->getRepository('Attachment')
                                    ->getCopiedAttachment($attachment);

                                $attachment->set('field', $fieldName);

                                $this->getEntityManager()->saveEntity($attachment);

                                $copiedIdList[] = $attachment->id;
                            }
                        }
                    }

                    $attachmentData->$idListFieldName = $copiedIdList;
                    $data = array_merge($data, get_object_vars($attachmentData));

                    continue;
                }
            }

            if (
                $entity->hasRelation($fieldName) ||
                $entity->hasAttribute($fieldName) ||
                in_array($fieldName, $metadataFieldList)
            ) {
                $fieldValue = $this->getValue($fieldName, $entity);

                if (is_object($fieldValue)) {
                    $data = array_merge($data, get_object_vars($fieldValue));
                }
                else {
                    $data[$fieldName] = $fieldValue;
                }
            }
        }

        return $data;
    }
}
