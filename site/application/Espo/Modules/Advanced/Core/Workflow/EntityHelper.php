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

namespace Espo\Modules\Advanced\Core\Workflow;

use Espo\ORM\Entity;
use Espo\Core\Utils\Util;

class EntityHelper
{
    private $container;

    private $streamService;

    protected $entityDefsList = [];

    private $serviceHash = [];

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

    protected function getMetadata()
    {
        return $this->container->get('metadata');
    }

    protected function getUser()
    {
        return $this->container->get('user');
    }

    protected function normalizeRelatedFieldName(Entity $entity, $fieldName)
    {
        if ($entity->hasRelation($fieldName)) {
            $type = $entity->getRelationType($fieldName);

            $key = $entity->getRelationParam($fieldName, 'key');
            $foreignKey = $entity->getRelationParam($fieldName, 'foreignKey');

            switch ($type) {
                case 'hasChildren':
                    if ($foreignKey) {
                        $fieldName = $foreignKey;
                    }

                    break;

                case 'belongsTo':
                    if ($key) {
                        $fieldName = $key;
                    }

                    break;

                case 'hasMany':
                case 'manyMany':
                    $fieldName .= 'Ids';

                    break;
            }
        }

        return $fieldName;
    }

    /**
     * Get list of field based on its type.
     *
     * @param Entity $entity
     * @param string $fieldName
     *
     * @return array
     */
    public function getActualFields(Entity $entity, $fieldName)
    {
        $entityType = $entity->getEntityType();

        $fieldManagerUtil = $this->getContainer()->get('fieldManagerUtil');

        if (isset($fieldManagerUtil)) {
            $list = [];
            $actualList = $fieldManagerUtil->getActualAttributeList($entityType, $fieldName);
            $additionalList = [];

            if (method_exists($fieldManagerUtil, 'getAdditionalActualAttributeList')) {
                $additionalList = $fieldManagerUtil->getAdditionalActualAttributeList($entityType, $fieldName);
            }

            foreach ($actualList as $item) {
                if (!in_array($item, $additionalList)) {
                    $list[] = $item;
                }
            }

            return $list;
        }

        return $this->getAttributeListByType($entityName, $fieldName, 'actual');
    }

    /**
     * Duplicate of \Espo\Core\Utils\FieldManagerUtil. Uses for old espocrm versions
     */
    private function getAttributeListByType($scope, $name, $type)
    {
        $fieldType = $this->getMetadata()->get('entityDefs.' . $scope . '.fields.' . $name . '.type');

        if (!$fieldType) {
            return [];
        }

        $defs = $this->getMetadata()->get('fields.' . $fieldType);

        if (!$defs) {
            return [];
        }

        if (is_object($defs)) {
            $defs = get_object_vars($defs);
        }

        $fieldList = [];

        if (isset($defs[$type . 'Fields'])) {
            $list = $defs[$type . 'Fields'];
            $naming = 'suffix';

            if (isset($defs['naming'])) {
                $naming = $defs['naming'];
            }

            if ($naming == 'prefix') {
                foreach ($list as $f) {
                    $fieldList[] = $f . ucfirst($name);
                }
            } else {
                foreach ($list as $f) {
                    $fieldList[] = $name . ucfirst($f);
                }
            }
        }
        else {
            if ($type == 'actual') {
                $fieldList[] = $name;
            }
        }

        return $fieldList;
    }

    /**
     * Get field value for a field/related field. If this field has a relation, get value from the relation.
     */
    public function getFieldValues(Entity $fromEntity, Entity $toEntity, $fromField, $toField)
    {
        $entity = $fromEntity;
        $fieldName = $fromField;

        $values = new \StdClass();

        // Get field names, 0 - field name or relation name, 1 - related field name.
        if (strstr($fieldName, '.')) {
            list($entityFieldName, $relatedEntityFieldName) = explode('.', $fieldName);

            $relatedEntity = $entity->get($entityFieldName);

            // If $entity is just created and doesn't have added relations.
            if (!isset($relatedEntity) && $entity->hasRelation($entityFieldName)) {
                $foreignEntityType = $entity->getRelationParam($entityFieldName, 'entity');

                $normalizedEntityFieldName = $this->normalizeRelatedFieldName($entity, $entityFieldName);

                if (
                    $foreignEntityType &&
                    $entity->hasAttribute($normalizedEntityFieldName) &&
                    $entity->get($normalizedEntityFieldName)
                ) {
                    $relatedEntity = $this->getEntityManager()
                        ->getEntity($foreignEntityType, $entity->get($normalizedEntityFieldName));
                }
            }

            if ($relatedEntity instanceof \Espo\ORM\Entity) {
                $entity = $relatedEntity;
                $fieldName = $relatedEntityFieldName;
            }
            else {
                $GLOBALS['log']->debug(
                    'Workflow [EntityHelper:getFieldValues]: The related field ['.$fieldName.'] of entity ['.
                    $entity->getEntityType().'] has unsupported or empty entity ['.
                    (isset($relatedEntity) ? get_class($relatedEntity) : var_export($relatedEntity, true)).'].');

                return;
            }
        }

        /* load field values */
        if ($entity->hasRelation($fieldName)) {
            if (!$entity->isNew()) {
                switch ($entity->getRelationType($fieldName)) { //ORM types
                    case 'manyMany':
                    case 'hasChildren':
                        $entity->loadLinkMultipleField($fieldName);

                        break;

                    case 'belongsTo':
                    case 'hasOne':
                        $entity->loadLinkField($fieldName);

                        break;
                }
            }
        }

        $fieldMap = $this->getRelevantAttributeMap($entity, $toEntity, $fieldName, $toField);

        $service = $this->getRecordService($entity->getEntityType());

        foreach ($fieldMap as $fromFieldName => $toFieldName) {
            $getCopiedMethodName = 'getCopied' . ucfirst($fromFieldName);

            if (method_exists($entity, $getCopiedMethodName)) {
                $values->$toFieldName = $entity->$getCopiedMethodName();
                continue;
            }

            if ($service) {
                $getCopiedMethodName = 'getCopiedEntityAttribute' . ucfirst($fromFieldName);
                if (method_exists($service, $getCopiedMethodName)) {
                    $values->$toFieldName = $service->$getCopiedMethodName($entity);
                    continue;
                }
            }

            $values->$toFieldName = $entity->get($fromFieldName);
        }

        $toFieldType = $this->getMetadata()->get(['entityDefs', $toEntity->getEntityType(), 'fields', $toField, 'type']);

        if ($toFieldType === 'personName' && !empty($values->$toFieldName)) {
            $fullNameValue = trim($values->$toFieldName);

            $firstNameAttribute = 'first' . ucfirst($toField);
            $lastNameAttribute = 'last' . ucfirst($toField);

            if (strpos($fullNameValue, ' ') === false) {
                $lastNameValue = $fullNameValue;
                $firstNameValue = null;
            }
            else {
                $index = strrpos($fullNameValue, ' ');
                $firstNameValue = substr($fullNameValue, 0, $index);
                $lastNameValue = substr($fullNameValue, $index + 1);
            }

            $values->$firstNameAttribute = $firstNameValue;
            $values->$lastNameAttribute = $lastNameValue;
        }

        /* correct field types. E.g. set teamsIds from defaultTeamId */
        if ($toEntity->hasRelation($toField)) {
            $normalizedFieldName = $this->normalizeRelatedFieldName($toEntity, $toField);

            switch ($toEntity->getRelationType($toField)) { //ORM types
                case 'manyMany':
                    if (isset($values->$normalizedFieldName) && !is_array($values->$normalizedFieldName)) {
                        $values->$normalizedFieldName = (array) $values->$normalizedFieldName;
                    }
                    break;
            }
        }

        return $values;
    }

    /**
     *  todo: REWRITE
     */
    protected function getRelevantAttributeMap(Entity $entity1, Entity $entity2, $field1, $field2)
    {
        $attributeList1 = $this->getActualFields($entity1, $field1);
        $attributeList2 = $this->getActualFields($entity2, $field2);

        $fieldType1 = $this->getMetadata()->get(['entityDefs', $entity1->getEntityType(), 'fields', $field1, 'type']);
        $fieldType2 = $this->getMetadata()->get(['entityDefs', $entity2->getEntityType(), 'fields', $field2, 'type']);

        $ignoreActualAttributesOnValueCopyFieldList = $this->getMetadata()->get(['entityDefs', 'Workflow', 'ignoreActualAttributesOnValueCopyFieldList'], []);

        if (in_array($fieldType1, $ignoreActualAttributesOnValueCopyFieldList)) {
            $attributeList1 = [$field1];
        }

        if (in_array($fieldType2, $ignoreActualAttributesOnValueCopyFieldList)) {
            $attributeList2 = [$field2];
        }

        $attributeMap = array();
        if (count($attributeList1) == count($attributeList2)) {
            if ($fieldType1 === 'datetimeOptional' && $fieldType2 === 'datetimeOptional') {
                if ($entity1->get($attributeList1[1])) {
                    $attributeMap[$attributeList1[1]] = $attributeList2[1];
                } else {
                    $attributeMap[$attributeList1[0]] = $attributeList2[0];
                }
            } else {
                foreach ($attributeList1 as $key => $name) {
                    $attributeMap[$name] = $attributeList2[$key];
                }
            }
        }
        else {
            if ($fieldType1 === 'datetimeOptional' || $fieldType2 === 'datetimeOptional') {
                if (count($attributeList2) > count($attributeList1)) {
                    if ($fieldType1 === 'date') {
                        $attributeMap[$attributeList1[0]] = $attributeList2[1];
                    } else {
                        $attributeMap[$attributeList1[0]] = $attributeList2[0];
                    }
                } else {
                    if ($fieldType2 === 'date') {
                        if ($entity1->get($attributeList1[1])) {
                            $attributeMap[$attributeList1[1]] = $attributeList2[0];
                        } else {
                            $attributeMap[$attributeList1[0]] = $attributeList2[0];
                        }
                    } else {
                        $attributeMap[$attributeList1[0]] = $attributeList2[0];
                    }
                }
            }
        }

        return $attributeMap;
    }

    protected function getRecordService($entityType)
    {
        if (!isset($this->serviceHash[$entityType])) {
            $this->serviceHash[$entityType] = $this->getContainer()->get('serviceFactory')->create($entityType);
        }

        return $this->serviceHash[$entityType];
    }
}
