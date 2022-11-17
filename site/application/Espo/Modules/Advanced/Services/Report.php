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

use Espo\Core\{
    Exceptions\Error,
    Exceptions\NotFound,
    Exceptions\Forbidden,
};

use Espo\{
    ORM\Entity,
    Modules\Advanced\Core\ORM\SthCollection,
    Modules\Advanced\Core\ORM\CustomEntityFactory
};

use StdClass;
use Exception;
use DateTime;
use DateInterval;
use DateTimeZone;
use PDO;

class Report extends \Espo\Services\Record
{
    protected function init()
    {
        parent::init();
        $this->addDependency('language');
        $this->addDependency('container');
        $this->addDependency('acl');
        $this->addDependency('aclManager');
        $this->addDependency('preferences');
        $this->addDependency('config');
        $this->addDependency('user');
        $this->addDependency('serviceFactory');
        $this->addDependency('formulaManager');
        $this->addDependency('injectableFactory');
        $this->addDependency('dateTime');
    }

    const STUB_KEY = '__STUB__';

    const GRID_SUB_LIST_LIMIT = 500;

    protected $numericFieldTypeList = ['currency', 'currencyConverted', 'int', 'float', 'enumInt', 'enumFloat', 'duration'];

    protected $forceSelectAllAttributes = true;

    protected $customEntityFactory = null;

    protected function getPreferences()
    {
        return $this->injections['preferences'];
    }

    protected function getServiceFactory()
    {
        return $this->injections['serviceFactory'];
    }

    protected function getConfig()
    {
        return $this->injections['config'];
    }

    protected function getUser()
    {
        return $this->injections['user'];
    }

    protected function getLanguage()
    {
        return $this->injections['language'];
    }

    protected function getAcl()
    {
        return $this->injections['acl'];
    }

    protected function getFormulaManager()
    {
        return $this->getInjection('formulaManager');
    }

    protected function getContainer()
    {
        return $this->injections['container'];
    }

    protected function getRecordService($name)
    {
        if ($this->getServiceFactory()->checkExists($name)) {
            $service = $this->getServiceFactory()->create($name);
            $service->setEntityType($name);
        }
        else {
            $service = $this->getServiceFactory()->create('Record');

            if (method_exists($service, 'setEntityType')) {
                $service->setEntityType($name);
            }
            else {
                $service->setEntityName($name);
            }
        }

        return $service;
    }

    protected function getFieldManagerUtil()
    {
        return $this->getInjection('fieldManagerUtil');
    }

    protected function beforeCreateEntity(Entity $entity, $data)
    {
        $this->processJointGridBeforeSave($entity);

        if (!$this->getAcl()->check($entity->get('entityType'), 'read')) {
            throw new Forbidden();
        }
    }

    protected function beforeUpdateEntity(Entity $entity, $data)
    {
        if ($entity->isAttributeChanged('type')) {
            $entity->set('type', $entity->getFetched('type'));
        }

        if ($entity->get('type') !== 'JointGrid' && $entity->isAttributeChanged('entityType')) {
            $entity->set('entityType', $entity->getFetched('entityType'));
        }

        $this->processJointGridBeforeSave($entity);
    }

    protected function processJointGridBeforeSave(Entity $entity)
    {
        if ($entity->get('type') === 'JointGrid') {
            $joinedReportDataList = $entity->get('joinedReportDataList');

            if (is_array($joinedReportDataList) && count($joinedReportDataList)) {

                foreach ($joinedReportDataList as $i => $item) {
                    if (empty($item->id)) {
                        throw new Error();
                    }

                    $report = $this->getEntityManager()->getEntity('Report', $item->id);

                    if (!$report) {
                        throw new Error('Report not found.');
                    }

                    if (!$this->getAcl()->check($report->get('entityType'), 'read')) {
                        throw new Forbidden();
                    }

                    $groupBy = $report->get('groupBy');

                    if (!is_array($groupBy) || count($groupBy) > 1 || $report->get('type') !== 'Grid') {
                        throw new Error("Sub-report {$item->id} is not supported in joint report.");
                    }

                    if ($i == 0) {
                        $groupCount = count($groupBy);

                        $entityType = $report->get('entityType');
                        $entity->set('entityType', $entityType);
                    }
                    else {
                        if ($groupCount !== count($groupBy)) {
                            throw new Error("Sub-reports must have the same Group By number.");
                        }
                    }
                }
            }
        }
    }

    public function getInternalReportImpl(Entity $report)
    {
        $className = $report->get('internalClassName');
        if (!empty($className)) {
            if (stripos($className, ':') !== false) {
                list($moduleName, $reportName) = explode(':', $className);
                if ($moduleName == 'Custom') {
                    $className = "Espo\\Custom\\Reports\\{$reportName}";
                } else {
                    $className = "Espo\\Modules\\{$moduleName}\\Reports\\{$reportName}";
                }
            } else {
                $className = "Espo\\Reports\\{$className}";
            }
        } else {
            throw new Error('No class name specified for internal report.');
        }
        $reportObj = new $className($this->getContainer());

        return $reportObj;
    }

    public function fetchDataFromReport(Entity $report)
    {
        $data = $report->get('data');
        if (empty($data)) {
            $data = new StdClass();
        }
        $data->orderBy = $report->get('orderBy');
        $data->groupBy = $report->get('groupBy');
        $data->columns = $report->get('columns');
        $data->entityType = $report->get('entityType');

        if (!$data->orderBy) {
            $data->orderBy = [];
        }

        if ($report->get('type') === 'List') {
            $data->orderByList = $report->get('orderByList');
            $data->columnsData = $report->get('columnsData');
        }

        if ($report->get('type') === 'Grid') {
            $data->applyAcl = $report->get('applyAcl');
        }

        if ($report->get('filtersData') && !$report->get('filtersDataList')) {
            $data->filtersWhere = $this->convertFiltersData($report->get('filtersData'));
        } else {
            $data->filtersWhere = $this->convertFiltersDataList($report->get('filtersDataList'), $report->get('entityType'));
        }

        $data->chartColors = $report->get('chartColors');
        $data->chartColor = $report->get('chartColor');
        $data->chartType = $report->get('chartType');

        if ($report->get('type') === 'JointGrid') {
            $data->joinedReportDataList = $report->get('joinedReportDataList');
        }

        if ($report->get('type') === 'Grid') {
            $data->chartDataList = $report->get('chartDataList');
        }

        return $data;
    }

    public function checkReportIsPosibleToRun(Entity $report)
    {
        if (in_array($report->get('entityType'), $this->getMetadata()->get('entityDefs.Report.entityListToIgnore', []))) {
            throw new Forbidden();
        }
    }

    public function run($id, $where = null, $params = null, $additionalParams = [], $user = null)
    {
        if (empty($id)) {
            throw new Error();
        }

        $report = $this->getEntityManager()->getEntity('Report', $id);

        if (!$report) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($report, 'read')) {
            throw new Forbidden();
        }

        if ($report->get('isInternal')) {
            $reportObj = $this->getInternalReportImpl($report);
            return $reportObj->run($where, $params, $user);
        }

        $type = $report->get('type');

        $entityType = $report->get('entityType');

        $data = $this->fetchDataFromReport($report);

        if (!$this->getAcl()->check($entityType, 'read') && !$this->getUser()->isPortal()) {
            throw new Forbidden();
        }

        $this->checkReportIsPosibleToRun($report);

        if ($where && empty($additionalParams['skipRuntimeFiltersCheck'])) {
            $this->checkRuntimeFilters($where, $report->get('runtimeFilters'));
        }

        switch ($type) {
            case 'Grid':
                if (!empty($params) && is_array($params) && array_key_exists('groupValue', $params)) {
                    return $this->executeSubReport($entityType, $data, $where, $params, $user);
                }

                return $this->executeGridReport($entityType, $data, $where, $additionalParams, $user);

            case 'List':

                return $this->executeListReport($entityType, $data, $where, $params, $additionalParams, $user);

            case 'JointGrid':

                return $this->executeJointGridReport($data, $user, $params);
        }
    }

    protected function checkRuntimeFilters($where, $allowedFilterList)
    {
        foreach ($where as $item) {
            $this->checkRuntimeFiltersItem($item, $allowedFilterList);
        }
    }

    protected function checkRuntimeFiltersItem($item, $allowedFilterList)
    {
        $type = isset($item['type']) ? $item['type'] : null;

        if ($type === 'and' || $type === 'or') {
            $where = (isset($item['value']) && is_array($item['value'])) ? $item['value'] : [];

            foreach ($where as $subItem) {
                $this->checkRuntimeFiltersItem($subItem, $allowedFilterList);
            }

            return;
        }

        $attribute = isset($item['attribute']) ? $item['attribute'] : null;

        if (!$attribute) {
            $attribute = isset($item['field']) ? $item['field'] : null;
        }

        if (!$attribute) {
            throw new Forbidden("Not allowed runtime filter group.");
        }

        if (strpos($attribute, ':') !== false) {
            throw new Forbidden("Not allowed function usage in runtime filter.");
        }

        if (strpos($attribute, '.') === false) {
            return;
        }


        $isAllowed = false;

        foreach ($allowedFilterList as $filterField) {
            if (strpos($attribute, $filterField) === 0) {
                $isAllowed = true;

                break;
            }
        }

        if (!$isAllowed) {
            throw new Forbidden("Not allowed runtime filter.");
        }
    }

    protected function convertFiltersDataList($filtersDataList, $entityType)
    {
        if (empty($filtersDataList)) {
            return null;
        }

        $arr = [];

        foreach ($filtersDataList as $defs) {
            $field = null;
            if (isset($defs->name)) {
                $field = $defs->name;
            }

            if (empty($defs) || empty($defs->params)) {
                continue;
            }

            $params = $defs->params;

            if (!empty($defs->type) && in_array($defs->type, ['or', 'and', 'not', 'having', 'subQueryIn', 'subQueryNotIn'])) {
                if (empty($params->value)) {
                    continue;
                }

                $o = new StdClass();
                $o->type = $params->type;
                $o->value = $this->convertFiltersDataList($params->value, $entityType);

                $arr[] = $o;

            } else if (!empty($defs->type) && $defs->type === 'complexExpression') {
                $o = (object) [];

                $function = null;
                if (isset($params->function)) {
                    $function = $params->function;
                }

                if ($function === 'custom') {
                    if (empty($params->expression)) {
                        continue;
                    }

                    $o->attribute = $params->expression;
                    $o->type = 'expression';
                }
                else if ($function === 'customWithOperator') {
                    if (empty($params->expression)) {
                        continue;
                    }

                    if (empty($params->operator)) {
                        continue;
                    }

                    $o->attribute = $params->expression;
                    $o->type = $params->operator;
                }
                else {
                    if (empty($params->attribute)) {
                        continue;
                    }

                    if (empty($params->operator)) {
                        continue;
                    }

                    $o->attribute = $params->attribute;

                    if ($function) {
                        $o->attribute = $params->function . ':' . $o->attribute;
                    }

                    $o->type = $params->operator;
                }

                if (isset($params->value) && is_string($params->value) && strlen($params->value)) {
                    try {
                        $o->value = $this->getFormulaManager()->run($params->value);
                    } catch (Error $e) {
                        throw new Error('Error in formula expression');
                    }
                }

                $arr[] = $o;
            } else {
                if (isset($params->where)) {
                    $arr[] = $params->where;
                } else {
                    if (isset($params->field)) {
                        $field = $params->field;
                    }
                    if (!empty($params->type)) {
                        $type = $params->type;
                        if (!empty($params->dateTime)) {
                            $arr[] = $this->convertDateTimeWhere(
                                $type, $field, isset($params->value) ? $params->value : null, $entityType
                            );
                        } else {
                            $o = new StdClass();
                            $o->type = $type;
                            $o->field = $field;
                            $o->attribute = $field;
                            $o->value = isset($params->value) ? $params->value : null;
                            $arr[] = $o;
                        }
                    }
                }
            }
        }

        return $arr;
    }

    protected function convertFiltersData($filtersData)
    {
        if (empty($filtersData)) {
            return null;
        }

        $arr = [];

        foreach ($filtersData as $name => $defs) {
            $field = $name;

            if (empty($defs)) {
                continue;
            }

            if (isset($defs->where)) {
                $arr[] = $defs->where;
            } else {
                if (isset($defs->field)) {
                    $field = $defs->field;
                }
                $type = $defs->type;
                if (!empty($defs->dateTime)) {
                    $arr[] = $this->convertDateTimeWhere($type, $field, isset($defs->value) ? $defs->value : null);
                } else {
                    $o = new StdClass();
                    $o->type = $type;
                    $o->field = $field;
                    $o->value = $defs->value;
                    $arr[] = $o;
                }
            }
        }

        return $arr;
    }

    protected function convertDateTimeWhere($type, $field, $value, $entityType = null)
    {
        $timeZone = $this->getPreferences()->get('timeZone');
        if (empty($timeZone)) {
            $timeZone = $this->getConfig()->get('timeZone');
        }

        if ($entityType) {
            $selectManager = $this->getSelectManagerFactory()->create($entityType);

            if (method_exists($selectManager, 'transformDateTimeWhereItem')) {
                $item = [
                    'attribute' => $field,
                    'type' => $type,
                    'value' => $value,
                    'dateTime' => true,
                    'timeZone' => $timeZone,
                ];
                $where = $selectManager->transformDateTimeWhereItem($item);
                $where = (object) $where;

                return $where;
            }
        }

        $where = new StdClass();
        $where->field = $field;

        $format = 'Y-m-d H:i:s';

        if (empty($value) && in_array($type, ['on', 'before', 'after'])) {
            return null;
        }

        $dt = new DateTime('now', new DateTimeZone($timeZone));

        switch ($type) {
            case 'today':
                $where->type = 'between';
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new DateTimeZone('UTC'));
                $from = $dt->format($format);
                $dt->modify('+1 day');
                $to = $dt->format($format);
                $where->value = [$from, $to];
                break;
            case 'past':
                $where->type = 'before';
                $dt->setTimezone(new DateTimeZone('UTC'));
                $where->value = $dt->format($format);
                break;
            case 'future':
                $where->type = 'after';
                $dt->setTimezone(new DateTimeZone('UTC'));
                $where->value = $dt->format($format);
                break;
            case 'lastSevenDays':
                $where->type = 'between';

                $dtFrom = clone $dt;

                $dt->setTimezone(new DateTimeZone('UTC'));
                $to = $dt->format($format);


                $dtFrom->modify('-7 day');
                $dtFrom->setTime(0, 0, 0);
                $dtFrom->setTimezone(new DateTimeZone('UTC'));

                $from = $dtFrom->format($format);

                $where->value = [$from, $to];

                break;
            case 'lastXDays':
                $where->type = 'between';

                $dtFrom = clone $dt;

                $dt->setTimezone(new DateTimeZone('UTC'));
                $to = $dt->format($format);

                $number = strval(intval($value));
                $dtFrom->modify('-'.$number.' day');
                $dtFrom->setTime(0, 0, 0);
                $dtFrom->setTimezone(new DateTimeZone('UTC'));

                $from = $dtFrom->format($format);

                $where->value = [$from, $to];

                break;
            case 'nextXDays':
                $where->type = 'between';

                $dtTo = clone $dt;

                $dt->setTimezone(new DateTimeZone('UTC'));
                $from = $dt->format($format);

                $number = strval(intval($value));
                $dtTo->modify('+'.$number.' day');
                $dtTo->setTime(24, 59, 59);
                $dtTo->setTimezone(new DateTimeZone('UTC'));

                $to = $dtTo->format($format);

                $where->value = [$from, $to];

                break;
            case 'nextXDays':
                $where->type = 'between';

                $dtTo = clone $dt;

                $dt->setTimezone(new DateTimeZone('UTC'));
                $from = $dt->format($format);

                $number = strval(intval($value));
                $dtTo->modify('+'.$number.' day');
                $dtTo->setTime(24, 59, 59);
                $dtTo->setTimezone(new DateTimeZone('UTC'));

                $to = $dtTo->format($format);

                $where->value = [$from, $to];

                break;
            case 'olderThanXDays':
                $where->type = 'before';
                $number = strval(intval($value));
                $dt->modify('-'.$number.' day');
                $dt->setTime(0, 0, 0);
                $dt->setTimezone(new DateTimeZone('UTC'));
                $where->value = $dt->format($format);
                break;
            case 'on':
                $where->type = 'between';

                $dt = new DateTime($value, new DateTimeZone($timeZone));
                $dt->setTimezone(new DateTimeZone('UTC'));
                $from = $dt->format($format);

                $dt->modify('+1 day');
                $to = $dt->format($format);
                $where->value = [$from, $to];
                break;
            case 'before':
                $where->type = 'before';
                $dt = new DateTime($value, new DateTimeZone($timeZone));
                $dt->setTimezone(new DateTimeZone('UTC'));
                $where->value = $dt->format($format);
                break;
            case 'after':
                $where->type = 'after';
                $dt = new DateTime($value, new DateTimeZone($timeZone));
                $dt->setTimezone(new DateTimeZone('UTC'));
                $where->value = $dt->format($format);
                break;
            case 'between':
                $where->type = 'between';
                if (is_array($value)) {
                    $dt = new DateTime($value[0], new DateTimeZone($timeZone));
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $from = $dt->format($format);

                    $dt = new DateTime($value[1], new DateTimeZone($timeZone));
                    $dt->setTimezone(new DateTimeZone('UTC'));
                    $to = $dt->format($format);

                    $where->value = [$from, $to];
                }
               break;
            default:
                $where->type = $type;
        }

        return $where;
    }

    protected function handleLeftJoins($item, $entityType, &$params)
    {
        if (strpos($item, ':') !== false) {
            $argumentList = self::getAllAttributesFromComplexExpression($item);

            if (!is_null($argumentList)) {
                foreach ($argumentList as $argument) {
                    $this->handleLeftJoins($argument, $entityType, $params);
                }

                return;
            }

            list($f, $item) = explode(':', $item);
        }

        if (strpos($item, '.') !== false) {
            list($rel, $f) = explode('.', $item);

            if (!in_array($rel, $params['leftJoins'])) {
                $params['leftJoins'][] = $rel;

                $defs = $this->getEntityManager()->getMetadata()->get($entityType);

                if (!empty($defs['relations']) && !empty($defs['relations'][$rel])) {
                    $relationType = $defs['relations'][$rel]['type'] ?? null;

                    if (in_array($relationType, ['hasMany', 'manyMany', 'hasChildren'])) {
                        $params['distinct'] = true;
                    }
                }
            }
        } else {
            $defs = $this->getEntityManager()->getMetadata()->get($entityType);

            $fieldDefs = (
                $defs['attributes'] ?? $defs['fields'] ?? []
            )[$item] ?? [];

            $type = $fieldDefs['type'] ?? null;

            if ($type === 'foreign') {
                $relation = $fieldDefs['relation'] ?? null;

                if ($relation && !in_array($relation, $params['leftJoins'])) {
                    $params['leftJoins'][] = $relation;
                }
            }
        }
    }

    protected function getTimeZoneOffset()
    {
        $tzOffset = 0;

        $timeZone = $this->getConfig()->get('timeZone', 'UTC');

        if ($timeZone === 'UTC') {
            $tzOffset = 0;
        }
        else {
            try {
                $dateTimeZone = new DateTimeZone($timeZone);
                $dateTime = new DateTime('now', $dateTimeZone);

                $dateTime->modify('first day of january');
                $tzOffset = $dateTimeZone->getOffset($dateTime) / 3600;
            }
            catch (Exception $e) {}
        }

        return $tzOffset;
    }

    protected function handleGroupBy($groupBy, $entityType, &$params, &$linkColumns, &$groupValueMap)
    {
        $version = $this->getConfig()->get('version');

        $tzOffset = (string) $this->getTimeZoneOffset();

        foreach ($groupBy as $groupIndex => $item) {
            $this->processGroupByAvailability($entityType, $item);

            $function = null;
            $argument = $item;

            if (strpos($item, ':') !== false) {
                list($function, $argument) = explode(':', $item);
            }

            if (strpos($item, '(') !== false && strpos($item, ':') !== false) {
                $this->handleLeftJoins($item, $entityType, $params);

                $params['select'][] = $item;
                $params['groupBy'][] = $item;

                continue;
            }

            if ($function === 'YEAR_FISCAL') {
                $fiscalYearShift = $this->getConfig()->get('fiscalYearShift', 0);

                if ($fiscalYearShift) {
                    $function = 'YEAR_' . $fiscalYearShift;

                    $item = $function . ':' . $argument;
                }
                else {
                    $function = 'YEAR';

                    $item = $function . ':' . $argument;
                }
            }
            else if ($function === 'QUARTER_FISCAL') {
                $fiscalYearShift = $this->getConfig()->get('fiscalYearShift', 0);

                if ($fiscalYearShift) {
                    $function = 'QUARTER_' . $fiscalYearShift;

                    $item = $function . ':' . $argument;
                }
                else {
                    $function = 'QUARTER';

                    $item = $function . ':' . $argument;
                }
            }
            else if ($function === 'WEEK') {
                if ($this->getConfig()->get('weekStart')) {
                    $function = 'WEEK_1';

                    $item = $function . ':' . $argument;
                }
                else {
                    $function = 'WEEK_0';

                    $item = $function . ':' . $argument;
                }
            }

            if (strpos($item, '.') === false) {
                $fieldType = $this->getMetadata()
                    ->get(['entityDefs', $entityType, 'fields', $argument, 'type']);

                if (in_array($fieldType, ['link', 'file', 'image'])) {
                    if (!in_array($item, $params['leftJoins'])) {
                        $params['leftJoins'][] = $item;
                    }

                    $params['select'][] = $item . 'Id';
                    $params['groupBy'][] = $item . 'Id';

                    $linkColumns[] = $item;

                }
                else if ($fieldType == 'linkParent') {
                    if (!in_array($item, $params['leftJoins'])) {
                        $params['leftJoins'][] = $item;
                    }

                    $params['select'][] = $item . 'Type';
                    $params['select'][] = $item . 'Id';
                    $params['groupBy'][] = $item . 'Id';
                    $params['groupBy'][] = $item . 'Type';

                }
                else if ($function && in_array($fieldType, ['datetime', 'datetimeOptional'])) {
                    if ($tzOffset && $version === 'dev' || version_compare($version, '5.6.0') >= 0) {
                        $groupBy = $function . ":TZ:({$argument},{$tzOffset})";

                        $params['groupBy'][] = $groupBy;
                        $params['select'][] = $groupBy;
                    }
                    else {
                        $params['select'][] = $item;
                        $params['groupBy'][] = $item;
                    }

                }
                else {
                    if ($fieldType == 'enum') {
                        $this->fillEnumGroupNames($entityType, $item, $groupValueMap);
                    }

                    $params['select'][] = $item;
                    $params['groupBy'][] = $item;
                }
            }
            else {
                $a = explode('.', $argument);

                $link = $a[0];
                $field = $a[1];

                $skipSelect = false;

                $defs = $this->getEntityManager()->getMetadata()->get($entityType);

                if (!empty($defs['relations']) && !empty($defs['relations'][$link])) {
                    $relationType = $defs['relations'][$link]['type'];
                    $foreignScope = $defs['relations'][$link]['entity'];

                    $foreignDefs = $this->getEntityManager()->getMetadata()->get($foreignScope);

                    $foreignFieldType = $this->getMetadata()->get(['entityDefs', $foreignScope, 'fields', $field, 'type']);

                    if ($foreignFieldType == 'enum') {
                        $this->fillEnumGroupNames($foreignScope, $field, $groupValueMap);
                    }

                    if (!empty($foreignDefs['relations']) && !empty($foreignDefs['relations'][$field])) {
                        $foreignRelationType = $foreignDefs['relations'][$field]['type'];

                        if (
                            ($relationType === 'belongsTo' || $relationType === 'hasOne') &&
                            $foreignRelationType === 'belongsTo'
                        ) {
                            $params['select'][] = $item . 'Id';
                            $params['groupBy'][] = $item . 'Id';

                            $skipSelect = true;

                            $linkColumns[] = $item;
                        }
                    }

                    if ($function && in_array($foreignFieldType, ['datetime', 'datetimeOptional'])) {
                        if ($tzOffset && $version === 'dev' || version_compare($version, '5.6.0') >= 0) {
                            $skipSelect = true;

                            $groupBy = $function . ":TZ:({$link}.{$field},{$tzOffset})";

                            $params['groupBy'][] = $groupBy;
                            $params['select'][] = $groupBy;
                        }
                    }
                }

                $this->handleLeftJoins($item, $entityType, $params);

                if (!$skipSelect) {
                    $params['select'][] = $item;
                    $params['groupBy'][] = $item;
                }
            }
        }
    }

    protected function fillEnumGroupNames($entityType, $item, &$groupValueMap)
    {
        $groupValueMap[$item] = $this->getLanguage()->translate($item, 'options', $entityType);

        if (!is_array($groupValueMap[$item])) {
            unset($groupValueMap[$item]);

            $translation = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $item, 'translation']);

            if ($translation) {
                $groupValueMap[$item] = $this->getLanguage()->get(explode('.', $translation));

                if (!is_array($groupValueMap[$item])) {
                    unset($groupValueMap[$item]);
                }
            }
        }
    }

    protected function processGroupByAvailability($entityType, $item)
    {
        if (strpos($item, ':') !== false) {
            $argumentList = self::getAllAttributesFromComplexExpression($item);

            if (!is_null($argumentList)) {
                foreach ($argumentList as $argument) {
                    $this->processGroupByAvailability($entityType, $argument);
                }

                return;
            }
        }

        if (strpos($item, ':') !== false) {
            list($function, $field) = explode(':', $item);
        } else {
            $field = $item;
        }

        if (strpos($field, '.') !== false) {
            list($link, $field) = explode('.', $field);

            $entityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link,  'entity']);

            if (!$entityType) {
                return;
            }
        }

        if (method_exists($this->getAcl(), 'getScopeRestrictedFieldList')) {
            if (in_array($field, $this->getAcl()->getScopeRestrictedFieldList($entityType, 'onlyAdmin'))) {
                throw new Forbidden;
            }

            if (in_array($field, $this->getAcl()->getScopeRestrictedFieldList($entityType, 'internal'))) {
                throw new Forbidden;
            }

            if (in_array($field, $this->getAcl()->getScopeRestrictedFieldList($entityType, 'forbidden'))) {
                throw new Forbidden;
            }
        }
    }

    protected function handleColumns($columns, $entityType, &$params, &$linkColumns)
    {
        foreach ($columns as $item) {
            if (strpos($item, '.') === false) {

                $type = $this->getMetadata()->get('entityDefs.' . $entityType . '.fields.' . $item . '.type');

                if (in_array($type, ['link', 'file', 'image'])) {
                    if (!in_array($item, $params['leftJoins'])) {
                        $params['leftJoins'][] = $item;
                    }

                    if (!in_array($item . 'Name', $params['select'])) {
                        $params['select'][] = $item . 'Name';
                    }

                    if (!in_array($item . 'Id', $params['select'])) {
                        $params['select'][] = $item . 'Id';
                    }

                    $linkColumns[] = $item;
                }
                else if ($type == 'linkParent') {
                    if (!in_array($item . 'Id', $params['select'])) {
                        $params['select'][] = $item . 'Id';
                    }

                    if (!in_array($item . 'Type', $params['select'])) {
                        $params['select'][] = $item . 'Type';
                    }

                    $linkColumns[] = $item;

                }
                else if ($type == 'currency') {
                    if (!in_array($item, $params['select'])) {
                        $params['select'][] = $item;
                    }

                    if (!in_array($item . 'Currency', $params['select'])) {
                        $params['select'][] = $item . 'Currency';
                    }

                    if (!in_array($item . 'Converted', $params['select'])) {
                        $params['select'][] = $item . 'Converted';
                    }
                }
                else if ($type == 'duration') {
                    $start = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $item, 'start']);
                    $end = $this->getMetadata()->get(['entityDefs', $entityType , 'fields', $item, 'end']);

                    if (!in_array($start, $params['select'])) {
                        $params['select'][] = $start;
                    }

                    if (!in_array($end, $params['select'])) {
                        $params['select'][] = $end;
                    }

                    if (!in_array($item, $params['select'])) {
                        $params['select'][] = $item;
                    }
                }
                else if ($type == 'personName') {
                    if (!in_array($item, $params['select'])) {
                        $params['select'][] = $item;
                    }

                    if (!in_array('first' . ucfirst($item), $params['select'])) {
                        $params['select'][] = 'first' . ucfirst($item);
                    }

                    if (!in_array('last' . ucfirst($item), $params['select'])) {
                        $params['select'][] = 'last' . ucfirst($item);
                    }

                }
                else if ($type == 'address') {
                    $pList = ['city', 'country', 'postalCode', 'street', 'state'];

                    foreach ($pList as $p) {
                        $column = $item . ucfirst($p);

                        if (!in_array($column, $params['select'])) {
                            $params['select'][] = $column;
                        }
                    }
                }
                else if ($type == 'linkMultiple' || $type == 'attachmentMultiple') {
                    continue;
                }
                else {
                    if (!in_array($item, $params['select'])) {
                        $params['select'][] = $item;
                    }
                }
            }
            else {
                $columnList = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $item, 'columnList']);

                if ($columnList) {
                    foreach ($columnList as $column) {
                        if (!in_array($column, $params['select'])) {
                            $params['select'][] = $column;
                        }
                    }
                }
                else {
                    $this->handleLeftJoins($item, $entityType, $params);

                    $a = explode('.', $item);
                    $link = $a[0];
                    $field = $a[1];

                    $skipSelect = false;

                    $foreignType = $this->getForeignFieldType($entityType, $link, $field);

                    if (in_array($foreignType, ['link', 'file', 'image'])) {
                          if (!in_array($item . 'Id', $params['select'])) {
                            $params['select'][] = $item . 'Id';
                            $skipSelect = true;
                        }
                    }

                    if (!$skipSelect && !in_array($item, $params['select'])) {
                        $params['select'][] = $item;
                    }
                }

            }
        }
    }

    protected function getForeignFieldType($entityType, $link, $field)
    {
        $defs = $this->getEntityManager()->getMetadata()->get($entityType);

        if (!empty($defs['relations']) && !empty($defs['relations'][$link])) {
            $foreignScope = $defs['relations'][$link]['entity'];

            $foreignType = $this->getMetadata()->get(['entityDefs', $foreignScope, 'fields', $field, 'type']);

            return $foreignType;
        }
    }

    protected function getRealForeignOrderColumn($entityType, $item)
    {
        $data = $this->getDataFromColumnName($entityType, $item);

        if (!$data->entityType) {
            throw new Error("Bad foreign order by.");
        }

        if (in_array($data->fieldType, ['link', 'linkParent', 'image', 'file'])) {
            return $item . 'Id';
        }

        return $item;
    }

    protected function handleOrderBy($orderBy, $entityType, &$params, &$orderLists)
    {
        foreach ($orderBy as $item) {
            if (strpos($item, 'LIST:') !== false) {
                $orderBy = substr($item, 5);

                if (strpos($orderBy, '.') !== false) {
                    list($rel, $field) = explode('.', $orderBy);

                    $foreignEntity = $this->getMetadata()
                        ->get('entityDefs.' . $entityType . '.links.' . $rel . '.entity');

                    if (empty($foreignEntity)) {
                        continue;
                    }

                    $optionList = $this->getMetadata()
                        ->get('entityDefs.' . $foreignEntity . '.fields.' . $field . '.options',  []);
                }
                else {
                    $field = $orderBy;
                    $optionList = $this->getMetadata()
                        ->get('entityDefs.' . $entityType . '.fields.' . $field . '.options',  []);
                }

                $optionPreparedList = [];

                foreach ($optionList as $option) {
                    $optionPreparedList[] = str_replace(',', '_COMMA_', $option);
                }

                $params['orderBy'][] = [
                    'LIST:' . $orderBy . ':' . implode(',', $optionPreparedList),
                ];

                $orderLists[$orderBy] = $optionList;
            } else {
                if (strpos($item, 'ASC:') !== false) {
                    $orderBy = substr($item, 4);

                    $order = 'ASC';
                }
                else if (strpos($item, 'DESC:') !== false) {
                    $orderBy = substr($item, 5);
                    $order = 'DESC';
                }
                else {
                    continue;
                }

                $field = $orderBy;
                $orderEntityType = $entityType;

                $link = null;

                if (strpos($orderBy, '.') !== false) {
                    list($link, $field) = explode('.', $orderBy);

                    $orderEntityType = $this->getMetadata()
                        ->get(['entityDefs', $entityType, 'links', $link, 'entity']);

                    if (empty($orderEntityType)) {
                        continue;
                    }
                }

                $fieldType = $this->getMetadata()->get(['entityDefs', $orderEntityType, 'fields', $field, 'type']);

                if (in_array($fieldType, ['link', 'file', 'image'])) {
                    if ($link) {
                        continue;
                    }

                    $orderBy = $orderBy . 'Name';

                    if (!in_array($orderBy, $params['select'])) {
                        $params['select'][] = $orderBy;
                    }
                }
                else if ($fieldType === 'linkParent') {
                    if ($link) {
                        continue;
                    }

                    $orderBy = $orderBy . 'Type';
                }

                if (!in_array($orderBy, $params['select'])) {
                    continue;
                }

                $index = array_search($orderBy, $params['select']) + 1;

                $params['orderBy'][] = [
                    $index,
                    $order
                ];
            }
        }
    }

    protected function handleFilters($where, $entityType, &$params, $isGrid = false)
    {
        foreach ($where as $item) {
            $this->handleWhereItem($item, $entityType, $params);
        }

        $having = [];

        foreach ($where as $i => $item) {
            $type = isset($item['type']) ? $item['type'] : null;
            $value = isset($item['value']) ? $item['value'] : null;

            if ($type === 'having' && is_array($value)) {
                foreach ($item['value'] as $havingItem) {
                    $having[] = $havingItem;
                }

                unset($where[$i]);
            }
        }

        $selectManager = $this->getSelectManagerFactory()->create($entityType);

        $filtersParams = [];

        $selectManager->checkWhere($where);

        $selectManager->applyWhere($where, $filtersParams);

        $params = $this->mergeSelectParams($params, $filtersParams, $entityType, $isGrid);

        if (!empty($having)) {
            if (method_exists($selectManager, 'checkWhere') && is_callable([$selectManager, 'checkWhere'])) {
                $selectManager->checkWhere($having);
            }

            $havingClause = $selectManager->convertWhere($having, true, $params);

            $params['havingClause'] = $havingClause;
        }

        if (!$isGrid && !empty($having) /*&& !empty($params['distinct'])*/) {
            $params['groupBy'] = ['id'];
        }
    }

    protected function handleWhereItem($item, $entityType, &$params)
    {
        $type = isset($item['type']) ? $item['type'] : null;

        $version = $this->getConfig()->get('version');

        if ($version === 'dev' || version_compare($version, '5.6.0') >= 0) {
            if ($type === 'having') {
                $selectManager = $this->getSelectManagerFactory()->create($entityType);
                $filtersParams = $selectManager->applyLeftJoinsFromWhereItem($item, $params);

                return;
            }

            if (!empty($params['distinct'])) {
                return;
            }
        }

        if ($type) {
            if (in_array($type, ['or', 'and', 'not', 'having'])) {
                if (!array_key_exists('value', $item) || !is_array($item['value'])) {
                    return;
                }

                foreach ($item['value'] as $listItem) {
                    $this->handleWhereItem($listItem, $entityType, $params);
                }

                return;
            }
        }

        $attribute = null;

        if (!empty($item['field'])) {
            $attribute = $item['field'];
        }

        if (!empty($item['attribute'])) {
            $attribute = $item['attribute'];
        }

        if ($attribute) {
            if ($version === 'dev' || version_compare($version, '5.6.0') >= 0) {
                $this->handleDistinct($attribute, $entityType, $params);
            } else {
                $this->handleLeftJoins($attribute, $entityType, $params);
            }
        }
    }

    protected function applySelectFromHavingItem($item, &$params): void
    {
        $type = $item['type'] ?? null;
        $value = $item['value'] ?? null;
        $attribute = $item['attribute'] ?? null;


        if ($type === 'having' || $type === 'and' || $type === 'or') {
            foreach ($value as $subItem) {
                $this->applySelectFromHavingItem($subItem, $params);
            }

            return;
        }

        if ($type === 'expression') {
            $argumentList = self::getAllAttributesFromComplexExpression($attribute);

            foreach ($argumentList as $argument) {
                if (in_array($argument, $params['select'])) {
                    continue;
                }

                if (strpos($argument, '.') !== false) {
                    continue;
                }

                $params['select'][] = $argument;
            }

            return;
        }
    }

    protected function handleDistinct($item, $entityType, &$params)
    {
        if (strpos($item, ':') !== false) {

            $argumentList = self::getAllAttributesFromComplexExpression($item);

            if (!is_null($argumentList)) {
                foreach ($argumentList as $argument) {
                    $this->handleDistinct($argument, $entityType, $params);
                }

                return;
            }

            list($f, $item) = explode(':', $item);
        }

        if (strpos($item, '.') !== false) {
            list($rel, $f) = explode('.', $item);

            $defs = $this->getEntityManager()->getMetadata()->get($entityType);

            $relsDefs = $defs['relations'] ?? [];
            $relDefs = $relsDefs[$rel] ?? [];
            $type = $relDefs['type'] ?? null;


            if ($type === 'hasMany' || $type === 'manyMany') {
                $params['distinct'] = true;
            }
        }
    }

    protected function handleWhere($where, $entityType, &$params, $isGrid = false)
    {
        foreach ($where as $item) {
            $this->handleWhereItem($item, $entityType, $params);
        }

        $selectManager = $this->getSelectManagerFactory()->create($entityType);

        $filtersParams = [];

        $selectManager->checkWhere($where);

        $selectManager->applyWhere($where, $filtersParams);

        $params = $this->mergeSelectParams($params, $filtersParams, $entityType, $isGrid);
    }

    protected function mergeSelectParams($params1, $params2, $entityType, $isGrid = false)
    {
        $selectManager = $this->getSelectManagerFactory()->create($entityType);

        $customWhere = '';
        if (!empty($params1['customWhere'])) {
            $customWhere .= $params1['customWhere'];
        }
        if (!empty($params2['customWhere'])) {
            $customWhere .= $params2['customWhere'];
        }

        $customJoin = '';
        if (!empty($params1['customJoin'])) {
            $customJoin .= $params1['customJoin'];
        }
        if (!empty($params2['customJoin'])) {
            $customJoin .= $params2['customJoin'];
        }

        foreach ($params2['joins'] as $join) {
            $selectManager->addJoin($join, $params1);
        }
        foreach ($params2['leftJoins'] as $join) {
            $selectManager->addLeftJoin($join, $params1);
        }
        if ($isGrid) {
            unset($params2['additionalSelectColumns']);
        }

        unset($params2['joins']);
        unset($params2['leftJoins']);

        if (empty($params1['whereClause1'])) {
            $params1['whereClause1'] = [];
        }
        if (empty($params1['whereClause2'])) {
            $params1['whereClause2'] = [];
        }

        $whereClause = $params1['whereClause'];
        $whereClause2 = $params2['whereClause'];

        foreach ($whereClause2 as $key => $value) {
            if (is_int($key)) {
                $whereClause[] = $value;
            } else {
                $whereClause[] = [
                    $key => $value
                ];
            }
        }

        $result = array_replace_recursive($params2, $params1);

        $result['whereClause'] = $whereClause;

        $result['customWhere'] = $customWhere;
        $result['customJoin'] = $customJoin;

        return $result;
    }

    public function fetchSelectParamsFromListReport(Entity $report, $user = null)
    {
        $data = $this->fetchDataFromReport($report);
        $params = $this->prepareListReportSelectParams($report->get('entityType'), $data, null, null, $user);

        return $params;
    }

    public function prepareListReportSelectParams(
        $entityType,
        $data,
        $where = null,
        $rawParams = null,
        $user = null,
        $additionalParams = []
    ) {
        if (!$rawParams) {
            $rawParams = [];
        }

        $rawParams = $rawParams ?? [];
        $orderBy = $rawParams['orderBy'] ?? $rawParams['sortBy'] ?? null;
        $order = $rawParams['order'] ?? false;

        if ($orderBy && strpos($orderBy, '_') !== false) {
            unset($rawParams['orderBy']);
            unset($rawParams['sortBy']);
            unset($rawParams['order']);
        }

        $selectManager = $this->getSelectManagerFactory()->create($entityType, $user);

        $params = $selectManager->getSelectParams($rawParams);

        if (!empty($data->columns)) {
            $params['select'] = [];
            $linkColumns = [];

            $this->handleColumns($data->columns, $entityType, $params, $linkColumns);

            $params['select'][] = 'id';
        }

        if (!empty($data->filtersWhere)) {
            $filtersWhere = json_decode(json_encode($data->filtersWhere), true);

            $this->handleFilters($filtersWhere, $entityType, $params, false);
        }

        if ($orderBy) {
            $sortByField = $orderBy;

            $sortByFieldType = $this->getMetadata()
                ->get('entityDefs.' . $entityType . '.fields.' . $sortByField . '.type');

            if (in_array($sortByFieldType, ['link', 'file', 'image'])) {
                $selectManager->addLeftJoin($orderBy, $params);
            }

            $seed = $this->getEntityManager()->getEntity($entityType);

            $sortAttributeList = [];

            if ($sortByFieldType === 'currency') {
                $sortAttributeList[] = $sortByField . 'Converted';
            }

            if (strpos($sortByField, '_') !== false) {
                if (strpos($sortByField, ':') !== false) {
                    throw new Forbidden("Functions are not allowed in orderBy.");
                }

                $sortByField = str_replace('_', '.', $sortByField);

                $sortByColumn = $this->getRealForeignOrderColumn($entityType, $sortByField);

                $sortAttributeList[] = $sortByColumn;

                $params['orderBy'] = [[$sortByColumn, $order], ['id', $order]];
                unset($params['order']);

            }
            else {
                $sortByAttributeList = $this->getFieldManagerUtil()->getAttributeList($entityType, $sortByField);

                foreach ($sortByAttributeList as $attribute) {
                    if (!in_array($attribute, $sortAttributeList) && $seed->hasAttribute($attribute)) {
                        $sortAttributeList[] = $attribute;
                    }
                }
            }

            if (array_key_exists('select', $params) && is_array($params['select'])) {
                foreach ($sortAttributeList as $attribute) {
                    if (!in_array($attribute, $params['select'])) {
                        $params['select'][] = $attribute;
                    }
                }
            }
        }

        if ($where) {
            $this->handleWhere($where, $entityType, $params, false);
        }

        if ($user && !$user->isAdmin()) {
            $selectManager->applyAccess($params);
        }

        return $params;
    }

    protected function executeListReport(
        $entityType,
        $data,
        $where = null,
        array $rawParams = null,
        $additionalParams = [],
        $user = null
    ) {
        if (!empty($additionalParams['customColumnList']) && is_array($additionalParams['customColumnList'])) {
            $initialColumnList = $data->columns;

            $newColumnList = [];

            foreach ($additionalParams['customColumnList'] as $item) {
                if (strpos($item, '.') !== false) {
                    if (!in_array($item, $initialColumnList)) {
                        break;
                    }
                }

                $newColumnList[] = $item;
            }

            $data->columns = $newColumnList;
        }

        if (empty($rawParams['orderBy'])) {
            if (isset($data->orderByList) && $data->orderByList) {
                list($order, $orderBy) = explode(':', $data->orderByList);

                $rawParams['orderBy'] = $orderBy;
                $rawParams['order'] = $order === 'ASC' ? 'asc' : 'desc';
            }
            else {
                $rawParams['orderBy'] = $this->getMetadata()
                    ->get(['entityDefs', $entityType, 'collection', 'orderBy']);

                if ($rawParams['orderBy']) {
                    $rawParams['order'] = $this->getMetadata()
                        ->get(['entityDefs', $entityType, 'collection', 'order']) ?? 'asc';
                }
            }
        }

        $params = $this->prepareListReportSelectParams(
            $entityType,
            $data,
            $where,
            $rawParams,
            $user,
            $additionalParams = []
        );

        $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);

        if (!empty($additionalParams['fullSelect'])) {
            unset($params['select']);
        }

        if (
            isset($params['havingClause']) &&
            isset($params['groupBy']) &&
            (
                class_exists('Espo\\ORM\\QueryParams\\Select') ||
                class_exists('Espo\\ORM\\Query\\Select')
            )
        ) {
            $paramsAux = $params;

            $paramsAux['select'] = ['id'];
            $paramsAux['distinct'] = false;

            $filtersWhere = json_decode(json_encode($data->filtersWhere), true);

            foreach ($filtersWhere as $whereItem) {
                if (($whereItem['type'] ?? null) !== 'having') {
                    continue;
                }

                $this->applySelectFromHavingItem($whereItem, $paramsAux);
            }

            foreach ($params['groupBy'] as $item) {
                if ($item === 'id') {
                    continue;
                }

                $paramsAux['select'][] = $item;
            }

            unset($params['havingClause']);
            unset($params['groupBy']);

            unset($params['whereClause']);

            unset($paramsAux['limit']);
            unset($paramsAux['offset']);

            unset($paramsAux['orderBy']);
            unset($paramsAux['order']);

            $selectClassName = class_exists('Espo\\ORM\\Query\\Select') ?
                'Espo\\ORM\\Query\\Select' :
                'Espo\\ORM\\QueryParams\\Select';

            $params['whereClause'][] = [
                'id=s' => [
                    'select' => ['subQueryAux.id'],
                    'fromQuery' => call_user_func([$selectClassName, 'fromRaw'], $paramsAux),
                    'fromAlias' => 'subQueryAux',
                    'withDeleted' => true,
                ]
            ];
        }

        $sql = $this->getEntityManager()->getQuery()->createSelectQuery($entityType, $params);

        $additionalAttributeDefs = [];

        $linkMultipleFieldList = [];

        $foreignLinkFieldDataList = [];

        foreach ($data->columns as $column) {
            if (strpos($column, '.') === false) {
                $fieldType = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $column, 'type']);

                if (in_array($fieldType, ['linkMultiple', 'attachmentMultiple'])) {
                    $linkMultipleFieldList[] = $column;
                }

                continue;
            }

            $arr = explode('.', $column);
            $link = $arr[0];
            $attribute = $arr[1];

            $foreignAttribute = $link . '_' . $attribute;

            $foreignType = $this->getForeignFieldType($entityType, $link, $attribute);

            if (in_array($foreignType, ['image', 'file', 'link'])) {
                $additionalAttributeDefs[$foreignAttribute . 'Id'] = [
                    'type' => 'foreign'
                ];

                if ($foreignType === 'link') {
                    $additionalAttributeDefs[$foreignAttribute . 'Name'] = [
                        'type' => 'varchar'
                    ];

                    $foreignEntityType = $this->getForeignLinkForeignEntityType($entityType, $link, $attribute);

                    if ($foreignEntityType) {
                        $foreignLinkFieldDataList[] = (object) [
                            'name' => $foreignAttribute,
                            'entityType' => $foreignEntityType
                        ];
                    }
                }
            }
            else {
                $additionalAttributeDefs[$foreignAttribute] = [
                    'type' => 'foreign',
                    'relation' => $link,
                    'foreign' => $attribute,
                ];
            }
        }

        $count = $this->getEntityManager()->getRepository($entityType)->count($params);

        $pdo = $this->getEntityManager()->getPDO();

        $sth = $pdo->prepare($sql);

        $sth->execute();

        $dataList = [];

        $service = $this->getRecordService($entityType);
        $collection = $this->getEntityManager()->createCollection($entityType);

        $entityDefs = $this->getEntityManager()->getMetadata()->get($entityType) ?? [];

        $attributeDefs = $entityDefs['attributes'] ?? $entityDefs['fields'] ?? [];

        $attributeDefs = array_merge($attributeDefs, $additionalAttributeDefs);

        if (
            !empty($additionalParams['isExport']) ||
            !empty($additionalParams['returnSthCollection'])
        ) {
            $collection = new SthCollection(
                $sth,
                $entityType,
                $this->getEntityManager(),
                $attributeDefs,
                $linkMultipleFieldList,
                $foreignLinkFieldDataList,
                $this->getCustomEntityFactory()
            );
        }
        else {
            while ($row = $sth->fetch(PDO::FETCH_ASSOC)) {
                $rowData = [];

                foreach ($row as $attr => $value) {
                    $attribute = str_replace('.', '_', $attr);
                    $rowData[$attribute] = $value;
                }

                $entity = $this->getCustomEntityFactory()->create($entityType, $attributeDefs);

                $entity->set($rowData);
                $entity->setAsFetched();

                $service->loadAdditionalFieldsForList($entity);

                foreach ($linkMultipleFieldList as $field) {
                    $entity->loadLinkMultipleField($field);
                }

                foreach ($foreignLinkFieldDataList as $item) {
                    $foreignId = $entity->get($item->name . 'Id');

                    if ($foreignId) {
                        $foreignEntity = $this->getEntityManager()
                            ->getRepository($item->entityType)
                            ->where(['id' => $foreignId])
                            ->select(['name'])
                            ->findOne();

                        if ($foreignEntity) {
                            $entity->set($item->name . 'Name', $foreignEntity->get('name'));
                        }
                    }
                }

                $collection[] = $entity;
            }
        }

        return [
            'collection' => $collection,
            'total' => $count,
            'columns' => $data->columns,
            'columnsData' => $data->columnsData,
        ];
    }

    protected function getForeignLinkForeignEntityType($entityType, $link, $field)
    {
        $foreignEntityType1 = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);

        return $this->getMetadata()->get(['entityDefs', $foreignEntityType1, 'links', $field, 'entity']);
    }

    protected function composeSubReportSelectParams(
        object $data,
        ?array $where,
        array $rawParams,
        $user = null
    ): array {

        $entityType = $data->entityType;

        $selectManager = $this->getSelectManagerFactory()->create($entityType, $user);

        $params = $selectManager->getSelectParams($rawParams);

        $groupValue = $rawParams['groupValue'];

        unset($rawParams['groupValue']);

        $groupIndex = 0;

        if (isset($rawParams['groupIndex'])) {
            $groupIndex = intval($rawParams['groupIndex']);

            unset($rawParams['groupIndex']);
        }

        $params['whereClause'] = isset($params['whereClause']) ? $params['whereClause'] : [];
        $params['leftJoins'] = isset($params['leftJoins']) ? $params['leftJoins'] : [];

        $groupByOther = null;

        if (!empty($data->groupBy)) {
            $groupBy1Type = $this->getMetadata()->get(
                ['entityDefs', $entityType, 'fields', $data->groupBy[0], 'type']
            );

            $groupBy2Type = null;

            if (count($data->groupBy) === 2) {
                $groupBy2Type = $this->getMetadata()->get(
                    ['entityDefs', $entityType, 'fields', $data->groupBy[1], 'type']
                );
            }

            $this->handleGroupBy($data->groupBy, $entityType, $params, $linkColumns, $groupValueMap);

            if (!isset($params['groupBy'][$groupIndex])) {
                throw new Error('No group by.');
            }

            $groupBy = $params['groupBy'][$groupIndex];

            if (count($data->groupBy) === 2) {
                if ($groupIndex === 1) {
                    $groupByOther = $params['groupBy'][0];

                    if ($groupBy1Type === 'linkParent') {
                        $groupBy = $params['groupBy'][2];
                    }
                } else {
                    if ($groupBy1Type === 'linkParent') {
                        $groupByOther = $params['groupBy'][2];
                    } else {
                        $groupByOther = $params['groupBy'][1];
                    }
                }
            }

            unset($params['groupBy']);
        }

        $noGroupBy = empty($groupBy);

        unset($params['select']);

        if (isset($rawParams['select'])) {
            if (method_exists($selectManager, 'getSelectAttributeList')) {
                $select = $selectManager->getSelectAttributeList($rawParams);

                $params['select'] = $select;
            }
        }

        if (!empty($data->filtersWhere)) {
            $filtersWhere = json_decode(json_encode($data->filtersWhere), true);

            $this->handleFilters($filtersWhere, $entityType, $params, false);
        }

        unset($params['havingClause']);

        if ($where) {
            $this->handleWhere($where, $entityType, $params, false);
        }

        $params['whereClause'] = (!empty($params['whereClause'])) ? $params['whereClause'] : [];

        if (!$noGroupBy) {
            if (
                $this->getMetadata()->get(
                    'entityDefs.' . $entityType . '.fields.' . $data->groupBy[$groupIndex] . '.type'
                ) == 'linkParent'
            ) {
                $arr = explode(':,:', $groupValue);

                $valueType = $arr[0];
                $valueId = null;

                if (count($arr)) {
                    $valueId = $arr[1];
                }

                if (empty($valueId)) {
                    $valueId = null;
                }

                $params['whereClause'][] = [
                    $data->groupBy[$groupIndex] . 'Type' => $valueType,
                    $data->groupBy[$groupIndex] . 'Id' => $valueId
                ];
            } else {
                $params['whereClause'][] = [$groupBy => $groupValue];
            }
        }

        if ($groupByOther) {
            if (array_key_exists('groupValue2', $rawParams)) {
                $groupValue2 = $rawParams['groupValue2'];

                if ($groupValue2 === '' || is_null($groupValue2)) {
                    $params['whereClause'][] = [
                        'OR' => [
                            [$groupByOther => ''],
                            [$groupByOther => null],
                        ]
                    ];
                } else {
                    if (
                        $this->getMetadata()->get(
                            ['entityDefs', $entityType, 'fields', $data->groupBy[1], 'type']
                        ) == 'linkParent'
                    ) {
                        $arr = explode(':,:', $groupValue2);

                        $valueType = $arr[0];
                        $valueId = null;

                        if (count($arr)) {
                            $valueId = $arr[1];
                        }

                        if (empty($valueId)) {
                            $valueId = null;
                        }

                        $params['whereClause'][] = [
                            $data->groupBy[1] . 'Type' => $valueType,
                            $data->groupBy[1] . 'Id' => $valueId
                        ];
                    } else {
                        $params['whereClause'][] = [$groupByOther => $groupValue2];
                    }
                }
            } else {
                $params['whereClause'][] = [$groupByOther . '!=' => null];
            }
        }

        $applyAcl = $data->applyAcl;

        if ($user && $applyAcl && !$user->isAdmin()) {
            $selectManager->applyAccess($params);
        }

        return $params;
    }

    protected function executeSubReport($entityType, $data, $where, array $rawParams, $user = null)
    {
        $params = $this->composeSubReportSelectParams($data, $where, $rawParams, $user);

        $collection = $this->getEntityManager()->getRepository($entityType)->find($params);

        $count = $this->getEntityManager()->getRepository($entityType)->count($params);

        $service = $this->getRecordService($entityType);

        foreach ($collection as $entity) {
            $service->loadAdditionalFieldsForList($entity);

            if (isset($rawParams['select'])) {
                $service->loadLinkMultipleFieldsForList($entity, $rawParams['select']);
            }

            $service->prepareEntityForOutput($entity);
        }

        return [
            'collection' => $collection,
            'total' => $count,
        ];
    }

    protected function executeGridReport($entityType, $data, $where, $additionalParams = [], $user = null)
    {
        $params = [];

        $seed = $this->getEntityManager()->getEntity($entityType);

        $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);

        $params['select'] = [];
        $params['groupBy'] = [];
        $params['orderBy'] = [];
        $params['whereClause'] = [];
        $params['leftJoins'] = isset($params['leftJoins']) ? $params['leftJoins'] : [];

        $params['additionalSelectColumns'] = [];

        $groupValueMap = [];
        $orderLists = [];
        $linkColumns = [];
        $sums = [];

        if (!empty($data->groupBy)) {
            $this->handleGroupBy($data->groupBy, $entityType, $params, $linkColumns, $groupValueMap);
        }

        $groupingColumnList = [];

        $columnList = $data->columns;

        $groupByList = $data->groupBy;

        $numericColumnList = [];

        foreach ($columnList as $item) {
            if ($this->isColumnNumeric($item, $entityType)) {
                $numericColumnList[] = $item;
            }
        }

        $subListColumnList = [];
        $summaryColumnList = [];

        foreach ($columnList as $item) {
            if ($this->isColumnSummary($item)) {
                $summaryColumnList[] = $item;
            }

            if ($this->isColumnSubList($item, $data->groupBy[0] ?? null)) {
                $subListColumnList[] = $item;
            }
        }

        if (count($groupByList) > 1) {
            $subListColumnList = [];
        }

        $columnToBuildList = $columnList;

        if (count($data->groupBy) === 2) {
            $columnToBuildList = $summaryColumnList;
        }

        $columnToBuildList = array_filter(
            $columnToBuildList,
            function (string $item) use ($subListColumnList) {
                return !in_array($item, $subListColumnList);
            }
        );

        $columnToBuildList = array_values($columnToBuildList);

        $aggregatedColumnList = array_filter(
            $columnList,
            function (string $item) use ($subListColumnList) {
                return !in_array($item, $subListColumnList);
            }
        );

        $aggregatedColumnList = array_values($aggregatedColumnList);

        if (count($subListColumnList)) {
            foreach ($columnToBuildList as $column) {
                if ($this->isColumnSubListAggregated($column)) {
                    $subListColumnList[] = $column;
                }
            }
        }

        if (!empty($aggregatedColumnList)) {
            $this->handleColumns($aggregatedColumnList, $entityType, $params, $linkColumns);

            foreach ($aggregatedColumnList as $column) {
                $this->processGroupByAvailability($entityType, $column);
            }
        }

        if (!empty($data->orderBy)) {
            $this->handleOrderBy($data->orderBy, $entityType, $params, $orderLists);
        }

        if (!empty($data->filtersWhere)) {
            $filtersWhere = json_decode(json_encode($data->filtersWhere), true);

            $this->handleFilters($filtersWhere, $entityType, $params, true);
        }

        if ($where) {
            $this->handleWhere($where, $entityType, $params, true);
        }

        $selectManager = $this->getSelectManagerFactory()->create($entityType, $user);

        $applyAcl = $data->applyAcl;

        if ($user && $applyAcl && !$user->isAdmin()) {
            $selectManager->applyAccess($params);
        }

        $useSubQuery = false;

        if (!empty($params['distinct'])) {
            foreach ($params['select'] as $item) {
                if (strpos($item, 'SUM:') === 0 || strpos($item, 'AVG:') === 0) {
                    $useSubQuery = true;
                }
            }
        }

        if (!empty($params['havingClause']) && empty($params['groupBy'])) {
            throw new Error("Having filter requires non-empty Group By.");
        }

        if ($useSubQuery) {
            $paramsSubQuery = $params;

            $params['leftJoins'] = [];
            $params['joins'] = [];
            $params['whereClause'] = [];
            $params['select'] = [];
            $params['groupBy'] = [];
            $params['orderBy'] = [];

            $paramsSubQuery['select'] = ['id'];

            unset($paramsSubQuery['groupBy']);
            unset($paramsSubQuery['havingClause']);
            unset($paramsSubQuery['orderBy']);
            unset($paramsSubQuery['order']);

            $this->getEntityManager()->getRepository($entityType)->handleSelectParams($params);

            $stub1 = [];
            $stub2 = [];

            $this->handleGroupBy($data->groupBy, $entityType, $params, $stub1, $stub2);
            $this->handleColumns($aggregatedColumnList, $entityType, $params, $stub1);

            if (!empty($data->orderBy)) {
                $stub3 = [];
                $this->handleOrderBy($data->orderBy, $entityType, $params, $stub3);
            }

            if (!empty($filtersWhere)) {
                foreach ($filtersWhere as $item) {
                    if (
                        !empty($item['type']) &&
                        $item['type'] === 'having' &&
                        !empty($item['value']) &&
                        is_array($item['value'])
                    ) {
                        $this->handleWhereItem($item, $entityType, $params);
                    }
                }
            }

            $params['whereClause'][] = [
                'id=s' => [
                    'entityType' => $entityType,
                    'selectParams' => $paramsSubQuery
                ]
            ];

            unset($params['distinct']);
        }

        $sql = $this->getEntityManager()->getQuery()->createSelectQuery($entityType, $params);

        $pdo = $this->getEntityManager()->getPDO();
        $sth = $pdo->prepare($sql);
        $sth->execute();

        $rows = $sth->fetchAll(PDO::FETCH_ASSOC);

        foreach ($rows as $i => $row) {
            foreach ($row as $column => $value) {
                if (in_array($column, $params['groupBy']) && is_null($value)) {
                    unset($rows[$i]);
                }
            }
        }

        foreach ($data->groupBy as $groupByItem) {
            $groupFieldType = $this->getMetadata()->get(['entityDefs', $entityType, 'fields', $groupByItem, 'type']);

            if ($groupFieldType == 'linkParent') {
                $this->mergeGroupByColumns(
                    $rows,
                    $params['groupBy'],
                    $groupByItem,
                    [$groupByItem . 'Type', $groupByItem . 'Id']
                );

                $groupValueMap[$groupByItem] = [];

                foreach ($rows as $row) {
                    $itemCompositeValue = $row[$groupByItem];

                    $arr = explode(':,:', $itemCompositeValue);

                    $itemValue = '';

                    if (count($arr)) {
                        $itemEntity = $this->getEntityManager()->getEntity($arr[0], $arr[1]);

                        if ($itemEntity) {
                            $itemValue = $this->getLanguage()
                                ->translate($arr[0], 'scopeNames') . ': ' . $itemEntity->get('name');
                        }
                    }

                    $groupValueMap[$groupByItem][$row[$groupByItem]] = $itemValue;
                }
            }
            else if ($groupFieldType == 'link') {
                $groupValueMap[$groupByItem] = [];

                $foreignEntityType = $this->getMetadata()
                    ->get(['entityDefs', $entityType, 'links', $groupByItem, 'entity']);

                foreach ($rows as $row) {
                    $id = $row[$groupByItem . 'Id'];

                    $foreignEntity = $this->getEntityManager()->getEntity($foreignEntityType, $id);

                    if ($foreignEntity) {
                        $groupValueMap[$groupByItem][$id] = $foreignEntity->get('name');
                    }
                }
            }
            else if ($groupByItem === 'id') {
                foreach ($rows as $row) {
                    $id = $row[$groupByItem];

                    $rowEntity = $this->getEntityManager()->getEntity($entityType, $id);
                    if ($rowEntity) {
                        $groupValueMap[$groupByItem][$id] = $rowEntity->get('name');
                    }
                }
            }
        }

        $grouping = [];

        foreach ($params['groupBy'] as $i => $groupCol) {
            $groupAlias = $this->sanitizeSelectAlias($groupCol);

            $grouping[$i] = [];

            foreach ($rows as $row) {
                if (!in_array($row[$groupAlias], $grouping[$i])) {
                    $grouping[$i][] = $row[$groupAlias];
                }
            }

            if ($i > 0) {
                if (in_array('ASC:' . $groupCol, $data->orderBy)) {
                    sort($grouping[$i]);
                }

                if (in_array('DESC:' . $groupCol, $data->orderBy)) {
                    rsort($grouping[$i]);
                }
                else if (in_array('LIST:' . $groupCol, $data->orderBy)) {
                    if (!empty($orderLists[$groupCol])) {
                        $list = $orderLists[$groupCol];

                        usort($grouping[$i], function ($a, $b) use ($list) {
                            return array_search($a, $list) > array_search($b, $list);
                        });
                    }
                }
            }

            $this->prepareGroupingRange($groupCol, $grouping[$i], $data, $where);
        }

        if (count($params['groupBy']) === 0) {
            $grouping = [[self::STUB_KEY]];

            $groupValueMap = [
                self::STUB_KEY => [
                    self::STUB_KEY => ''
                ]
            ];

        }
        else if (count($params['groupBy']) === 1) {
            $groupNumber = 0;
            $groupCol = $params['groupBy'][$groupNumber];

            if (
                strpos($groupCol, 'MONTH:') === 0 ||
                strpos($groupCol, 'YEAR:') === 0 ||
                strpos($groupCol, 'DAY:') === 0
            ) {
                foreach ($grouping[$groupNumber] as $groupValue) {
                    $isMet = false;

                    foreach ($rows as $row) {
                        if ($groupValue === $row[$this->sanitizeSelectAlias($groupCol)]) {
                            $isMet = true;
                            break;
                        }
                    }

                    if ($isMet) {
                        continue;
                    }

                    $newRow = [];
                    $newRow[$this->sanitizeSelectAlias($groupCol)] = $groupValue;

                    foreach ($data->columns as $column) {
                        $newRow[$column] = 0;
                    }

                    $rows[] = $newRow;
                }
            }
        }
        else {
            $groupCol1 = $params['groupBy'][0];
            $groupCol2 = $params['groupBy'][1];

            if (
                strpos($groupCol1, 'MONTH:') === 0 ||
                strpos($groupCol1, 'YEAR:') === 0 ||
                strpos($groupCol1, 'DAY:') === 0 ||
                strpos($groupCol2, 'MONTH:') === 0 ||
                strpos($groupCol2, 'YEAR:') === 0 ||
                strpos($groupCol2, 'DAY:') === 0
            ) {
                $skipFilling = false;

                if (strpos($groupCol1, 'DAY:') === 0 || strpos($groupCol2, 'DAY:') === 0) {
                    $skipFilling = true;

                    foreach ($data->columns as $column) {
                        if (strpos($column, 'AVG:') === 0) {
                            $skipFilling = false;
                        }
                    }
                }

                if (!$skipFilling) {
                    foreach ($grouping[0] as $groupValue1) {
                        foreach ($grouping[1] as $groupValue2) {
                            $isMet = false;
                            foreach ($rows as $row) {
                                if (
                                    $groupValue1 === $row[$this->sanitizeSelectAlias($groupCol1)]
                                    &&
                                    $groupValue2 === $row[$this->sanitizeSelectAlias($groupCol2)]
                                ) {
                                    $isMet = true;

                                    break;
                                }
                            }

                            if ($isMet) {
                                continue;
                            }

                            $newRow = [];

                            $newRow[$this->sanitizeSelectAlias($groupCol1)] = $groupValue1;
                            $newRow[$this->sanitizeSelectAlias($groupCol2)] = $groupValue2;

                            foreach ($data->columns as $column) {
                                $newRow[$column] = 0;
                            }

                            $rows[] = $newRow;
                        }
                    }
                }
            }
        }

        $paramsCopied = $params;

        if (count($data->groupBy) === 0) {
            $paramsCopied['groupBy'] = [self::STUB_KEY];

            if (count($rows)) {
                $rows[0][self::STUB_KEY] = self::STUB_KEY;
            }
        }

        $cellValueMaps = (object) [];

        $reportData = $this->buildGrid($entityType, $rows, $paramsCopied, $columnToBuildList, $sums, $cellValueMaps);

        $nonSummaryData = null;
        $nonSummaryColumnGroupMap = null;

        if (count($data->groupBy) === 2) {
            $nonSummaryColumnGroupMap = (object) [];

            if (count($columnList) > count($summaryColumnList)) {
                $nonSummaryData = $this->buildGridnonSummaryData(
                    $entityType, $columnList, $summaryColumnList, $data, $rows,
                    $paramsCopied, $cellValueMaps, $nonSummaryColumnGroupMap
                );
            }
        }

        foreach ($linkColumns as $column) {
            if (array_key_exists($column, $groupValueMap)) {
                continue;
            }

            $groupValueMap[$column] = [];

            foreach ($rows as $row) {
                if (array_key_exists($column . 'Id', $row)) {
                    if (array_key_exists($column . 'Name', $row)) {
                        $groupValueMap[$column][$row[$column . 'Id']] = $row[$column . 'Name'];
                    }
                    else {
                        $relatedId = $row[$column . 'Id'];

                        if (strpos($column, '.')) {
                            list($link1, $link2) = explode('.', $column);

                            $entityType1 = $this->getMetadata()
                                ->get(['entityDefs', $entityType, 'links', $link1, 'entity']);

                            if ($entityType1) {
                                $entityType2 = $this->getMetadata()
                                    ->get(['entityDefs', $entityType1, 'links', $link2, 'entity']);

                                if ($entityType2) {
                                    $relatedEntity = $this
                                        ->getEntityManager()->getEntity($entityType2, $relatedId);

                                    if ($relatedEntity) {
                                        $groupValueMap[$column][$row[$column . 'Id']] = $relatedEntity->get('name');
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }

        $columnTypeMap = [];

        foreach ($data->columns as $item) {
            $columnData = $this->getDataFromColumnName($entityType, $item);
            $type = $this->getMetadata()->get(['entityDefs', $columnData->entityType, 'fields', $columnData->field, 'type']);

            if ($entityType === 'Opportunity' && $columnData->field === 'amountWeightedConverted') {
                $type = 'currencyConverted';
            }

            if ($columnData->function === 'COUNT') {
                $type = 'int';
            }

            $columnTypeMap[$item] = $type;
        }

        $columnNameMap = [];

        foreach ($data->columns as $item) {
            $columnNameMap[$item] = $this->translateColumnName($entityType, $item);
        }

        foreach ($data->groupBy as $level => $groupByItem) {
            if (strpos($groupByItem, 'QUARTER:') === 0) {
                foreach ($grouping[$level] as $value) {
                    $key = $value;
                    list($year, $quarter) = explode('_', $value);
                    $value = 'Q' . $quarter . ' ' . $year;
                    $groupValueMap[$groupByItem][$key] = $value;
                }
            }
            if (strpos($groupByItem, 'QUARTER_FISCAL:') === 0) {
                foreach ($grouping[$level] as $value) {
                    $key = $value;
                    list($year, $quarter) = explode('_', $value);
                    $value = 'Q' . $quarter . ' ' . $year . '-' . strval($year + 1);
                    $groupValueMap[$groupByItem][$key] = $value;
                }
            }
            else if (strpos($groupByItem, 'YEAR_FISCAL:') === 0) {
                foreach ($grouping[$level] as $value) {
                    $key = $value;
                    $groupValueMap[$groupByItem][$key] = strval($value) . '-' . strval($value + 1);
                }
            }
        }

        $nonSummaryColumnList = [];

        foreach ($columnList as $item) {
            if (!in_array($item, $summaryColumnList)) {
                $nonSummaryColumnList[] = $item;
            }
        }

        $subListData = (object) [];

        if (count($subListColumnList)) {
            $subListData = $this->executeGridReportSubList($grouping[0], $subListColumnList, $data, $where, $user);
        }

        foreach ($reportData as $k => $v) {
            $reportData[$k] = $reportData[$k] = (object) $v;

            foreach ($v as $k1 => $v1) {
                if (is_array($v1)) {
                    $reportData[$k]->$k1 = (object) $v1;
                }
            }
        }

        $reportData = (object) $reportData;

        $result = [
            'type' => 'Grid',
            'groupBy' => $data->groupBy,
            'columns' => $data->columns,
            'columnList' => $columnList,
            'groupByList' => $groupByList,
            'numericColumnList' => $numericColumnList,
            'summaryColumnList' => $summaryColumnList,
            'nonSummaryColumnList' => $nonSummaryColumnList,
            'nonSummaryColumnGroupMap' => $nonSummaryColumnGroupMap,
            'subListColumnList' => $subListColumnList,
            'aggregatedColumnList' => $aggregatedColumnList,
            'subListData' => $subListData,
            'sums' => $sums,
            'groupValueMap' => $groupValueMap,
            'columnNameMap' => $columnNameMap,
            'columnTypeMap' => $columnTypeMap,
            'cellValueMaps' => $cellValueMaps,
            'depth' => count($data->groupBy),
            'grouping' => $grouping,
            'reportData' => $reportData,
            'nonSummaryData' => $nonSummaryData,
            'entityType' => $entityType,
            'success' => !empty($data->success) ? $data->success : null,
            'chartColors' => !empty($data->chartColors) ? $data->chartColors : null,
            'chartColor' => $data->chartType ? $data->chartColor : null,
            'chartType' => $data->chartType,
            'chartDataList' => $data->chartDataList ?? null,
        ];

        if (count($groupByList) === 2) {
            $group1NonSummaryColumnList = [];
            $group2NonSummaryColumnList = [];

            if (!empty($result['nonSummaryColumnList'])) {
                foreach ($result['nonSummaryColumnList'] as $column) {
                    $group = $result['nonSummaryColumnGroupMap']->$column;

                    if ($group == $result['groupByList'][0]) {
                        $group1NonSummaryColumnList[] = $column;
                    }

                    if ($group == $result['groupByList'][1]) {
                        $group2NonSummaryColumnList[] = $column;
                    }
                }
            }

            $result['group1NonSummaryColumnList'] = $group1NonSummaryColumnList;
            $result['group2NonSummaryColumnList'] = $group2NonSummaryColumnList;

            $result['group1Sums'] = $sums;

            $group2Sums = [];

            foreach ($grouping[1] as $group2) {
                $o = [];

                foreach ($summaryColumnList as $column) {
                    $columnData = $this->getDataFromColumnName($entityType, $column);

                    $function = $columnData->function;

                    $sum = 0;

                    foreach ($grouping[0] as $group1) {
                        $value = 0;

                        if (
                            isset($reportData->$group1) &&
                            isset($reportData->$group1->$group2) &&
                            isset($reportData->$group1->$group2->$column)
                        ) {
                            $value = $reportData->$group1->$group2->$column;
                        }

                        if ($function === 'MAX') {
                            if ($value > $sum) {
                                $sum = $value;
                            }
                        }
                        else if ($function === 'MIN') {
                            if ($value < $sum) {
                                $sum = $value;
                            }
                        }
                        else {
                            $sum += $value;
                        }
                    }

                    if ($function === 'AVG') {
                        $sum = $sum / count($grouping[0]);
                    }

                    $o[$column] = $sum;
                }

                $group2Sums[$group2] = $o;
            }
            $sums = (object) [];

            foreach ($summaryColumnList as $column) {
                $columnData = $this->getDataFromColumnName($entityType, $column);
                $function = $columnData->function;
                $sum = 0;

                foreach ($grouping[0] as $group1) {
                    $value = 0;
                    if (isset($result['group1Sums'][$group1]) && isset($result['group1Sums'][$group1][$column])) {
                        $value = $result['group1Sums'][$group1][$column];
                    }
                    if ($function === 'MAX') {
                        if ($value > $sum) {
                            $sum = $value;
                        }
                    } else if ($function === 'MIN') {
                        if ($value < $sum) {
                            $sum = $value;
                        }
                    } else {
                        $sum += $value;
                    }
                }

                if ($function === 'AVG') {
                    $sum = $sum / count($grouping[0]);
                }

                $sums->$column = $sum;
            }

            $result['sums'] = $sums;
            $result['group2Sums'] = $group2Sums;
        }

        return $result;
    }

    protected function executeGridReportSubList(
        array $groupValueList, array $columnList, object $data, ?array $where, $user = null
    ) : object {

        $result = (object) [];

        foreach ($groupValueList as $groupValue) {
            $result->$groupValue = $this->executeGridReportSubListItem($groupValue, $columnList, $data, $where, $user);
        }

        return $result;
    }

    protected function executeGridReportSubListItem(
        $groupValue, array $columnList, object $data, ?array $where, $user = null
    ) : array {

        $entityType = $data->entityType;

        $rawParams = [
            'groupValue' => $groupValue,
            'groupIndex' => 0,
            'select' => ['id'],
        ];

        $selectParams = $this->composeSubReportSelectParams($data, $where, $rawParams, $user);

        $linkColumnList = [];

        $realColumnList = array_map(
            function (string $column) {
                if (strpos($column, ':') === false) {
                    return $column;
                }

                return explode(':', $column)[1];
            },
            $columnList
        );

        $this->handleColumns($realColumnList, $data->entityType, $selectParams, $linkColumnList);

        $stubOrder = [];

        $this->handleOrderBy($data->orderBy, $data->entityType, $selectParams, $stubOrder);

        $columnAttributeMap = [];

        foreach ($columnList as $column) {
            if (in_array($column, $linkColumnList)) {
                $columnAttributeMap[$column] = $column . 'Name';

                continue;
            }

            if (strpos($column, ':') !== false) {
                $columnAttributeMap[$column] = explode(':', $column)[1];

                continue;
            }

            $columnAttributeMap[$column] = $column;
        }

        $limit = $this->getConfig()->get('reportGridSubListLimit') ?? self::GRID_SUB_LIST_LIMIT;

        $collection = $this->getEntityManager()
            ->getRepository($entityType)
            ->limit(0, $limit)
            ->find($selectParams);

        $itemList = [];

        foreach ($collection as $entity) {
            $item = (object) [];

            $item->id = $entity->id;

            foreach ($columnList as $column) {
                $attribute = $columnAttributeMap[$column];

                $columnData = $this->getDataFromColumnName($entityType, $column);

                $value = $this->getCellDisplayValue($entity->get($attribute), $columnData);

                $item->$column = $value;
            }

            $itemList[] = $item;
        }

        return $itemList;
    }

    protected function buildGridNonSummaryData(
        $entityType, $columnList, $summaryColumnList, $data, $rows, $params, &$cellValueMaps, &$nonSummaryColumnGroupMap
    ) {
        $nonSummaryData = (object) [];

        foreach ($data->groupBy as $i => $groupColumn) {
            $nonSummaryData->$groupColumn = (object) [];

            $groupAlias = $this->sanitizeSelectAlias($params['groupBy'][$i]);

            foreach ($columnList as $column) {
                if (in_array($column, $summaryColumnList)) {
                    continue;
                }

                if (strpos($column, $groupColumn . '.') === 0) {
                    $nonSummaryColumnGroupMap->$column = $groupColumn;

                    $columnData = $this->getDataFromColumnName($entityType, $column);

                    $columnKey = $column;

                    if ($columnData->fieldType === 'link') {
                        $columnKey .= 'Id';
                    }

                    $columnAlias = $this->sanitizeSelectAlias($columnKey);

                    foreach ($rows as $row) {
                        $groupValue = $row[$groupAlias];

                        if (!property_exists($nonSummaryData->$groupColumn, $groupValue)) {
                            $nonSummaryData->$groupColumn->$groupValue = (object) [];
                        }

                        $value = $row[$columnAlias];

                        if (!is_null($value)) {
                            $nonSummaryData->$groupColumn->$groupValue->$column = $value;

                            if (!property_exists($cellValueMaps, $column)) {
                                $cellValueMaps->$column = (object) [];
                            }

                            if (!property_exists($cellValueMaps->$column, $value)) {
                                $cellValueMaps->$column->$value = $this->getCellDisplayValue($value, $columnData);
                            }
                        }
                    }
                }
            }
        }

        return $nonSummaryData;
    }

    public function isColumnNumeric($item, $entityType)
    {
        $columnData = $this->getDataFromColumnName($entityType, $item);

        if (in_array($columnData->function, ['COUNT', 'SUM', 'AVG'])) {
            return true;
        }

        if (in_array($columnData->fieldType, $this->numericFieldTypeList)) {
            return true;
        }

        return false;
    }

    public function isColumnSummary($item)
    {
        $function = null;

        if (strpos($item, ':') > 0) {
            list($function) = explode(':', $item);
        }

        if ($function && in_array($function, ['COUNT', 'SUM', 'AVG', 'MIN', 'MAX'])) {
            return true;
        }

        return false;
    }

    public function isColumnSubListAggregated(string $item) : bool
    {
        if (strpos($item, ':') === false) {
            return false;
        }

        if (strpos($item, ',') !== false) {
            return false;
        }

        if (strpos($item, '.') !== false) {
            return false;
        }

        if (strpos($item, '(') !== false) {
            return false;
        }

        $function = explode(':', $item)[0];

        if ($function === 'COUNT') {
            return false;
        }

        if (in_array($function, ['SUM', 'MAX', 'MIN', 'AVG'])) {
            return true;
        }

        return false;
    }

    public function isColumnSubList(string $item, ?string $groupBy = null) : bool
    {
        if (strpos($item, ':') !== false) {
            return false;
        }

        if (strpos($item, '.') === false) {
            return true;
        }

        if (!$groupBy) {
            return true;
        }

        if (explode('.', $item)[0] === $groupBy) {
            return false;
        }

        return true;
    }

    protected function prepareGroupingRange($groupCol, &$list, $data, $where = null)
    {
        $isDate = false;

        if (strpos($groupCol, 'MONTH:') === 0) {
            $isDate = true;
            $this->prepareGroupingMonth($list);
        }
        else if (strpos($groupCol, 'QUARTER:') === 0 || strpos($groupCol, 'QUARTER_') === 0) {
            $isDate = true;
            $this->prepareGroupingQuarter($list);
        }
        else if (strpos($groupCol, 'WEEK_0:') === 0) {
            $isDate = true;
            $this->prepareGroupingWeek($list);
        }
        else if (strpos($groupCol, 'WEEK_1:') === 0) {
            $isDate = true;
            $this->prepareGroupingWeek($list, true);
        }
        else if (strpos($groupCol, 'DAY:') === 0) {
            $isDate = true;
            $this->prepareGroupingDay($list);
        }
        else if (strpos($groupCol, 'YEAR:') === 0 || strpos($groupCol, 'YEAR_') === 0) {
            $isDate = true;
            $this->prepareGroupingYear($list);
        }

        $filterList = [];

        if ($where) {
            $filterList = $filterList + $where;
        }

        if (!empty($data->filtersWhere)) {
            $arr = [];

            foreach ($data->filtersWhere as $item) {
                $arr[] = get_object_vars($item);
            }

            $filterList = array_merge($filterList,  $arr);
        }

        if ($isDate) {
            if (in_array('DESC:' . $groupCol, $data->orderBy)) {
                rsort($list);
            }

            if ($filterList) {
                if (strpos($groupCol, 'MONTH:') === 0) {
                    $fillToYearStart = false;

                    foreach ($filterList as $item) {
                        if (empty($item['type']) || empty($item['attribute'])) {
                            continue;
                        }

                        // originalType here is not actual. Can be omitted.
                        $originalType = isset($item['originalType']) ? $item['originalType'] : $item['type'];

                        if ($originalType === 'currentYear' || $originalType === 'lastYear') {
                            if ($item['attribute'] === substr($groupCol, 6)) {
                                $fillToYearStart = true;

                                break;
                            }
                            else {
                                $query = $this->getEntityManager()->getQuery();

                                $aList = self::getAllAttributesFromComplexExpression($groupCol);

                                if (!is_null($aList)) {
                                    if (count($aList) && $aList[0] === $item['attribute']) {
                                        $fillToYearStart = true;

                                        break;
                                    }
                                }
                            }
                        }
                    }

                    if ($fillToYearStart) {
                        if (count($list)) {
                            $first = $list[0];

                            list($year, $month) = explode('-', $first);

                            if (intval($month) > 1) {
                                for ($m = intval($month) - 1; $m >= 1; $m--) {
                                    $newDate = $year . '-' . str_pad(strval($m), 2, '0', \STR_PAD_LEFT);

                                    array_unshift($list, $newDate);
                                }
                            }

                            $last = $list[count($list) - 1];

                            list($year, $month) = explode('-', $last);

                            $originalType = isset($item['originalType']) ? $item['originalType'] : $item['type'];

                            if ($originalType === 'currentYear') {
                                $dtThisMonthStart = new DateTime();
                                $todayMonthNumber = intval($dtThisMonthStart->format('m'));
                            }
                            else {
                                $todayMonthNumber = 12;
                            }

                            for ($m = intval($month) + 1; $m <= $todayMonthNumber; $m ++) {
                                $newDate = $year . '-' . str_pad(strval($m), 2, '0', \STR_PAD_LEFT);
                                $list[] = $newDate;
                            }
                        }
                    }
                }
            }
        } else {
            if (strpos($groupCol, ':') === false) {
                $columnData = $this->getDataFromColumnName($data->entityType, $groupCol);

                $skipFilling = false;

                if ($columnData->fieldType === 'enum') {
                    foreach ($filterList as $filterItem) {
                        if (empty($filterItem['attribute'])) {
                            continue;
                        }

                        if ($filterItem['attribute'] === $groupCol) {
                            $skipFilling = true;
                        }
                    }
                    if (!$skipFilling) {
                        $optionList = $this->getMetadata()->get(
                            ['entityDefs', $columnData->entityType, 'fields', $columnData->field, 'options']
                        );

                        if (is_array($optionList)) {
                            foreach ($optionList as $item) {
                                if (!in_array($item, $list)) {
                                    $list[] = $item;
                                }
                            }

                            if (in_array('LIST:'. $groupCol, $data->orderBy)) {
                                $list = $optionList;
                            }
                        }
                    }
                }
            }
        }
    }

    protected function prepareGroupingMonth(&$list)
    {
        sort($list);
        $fullList = [];

        if (isset($list[0]) && isset($list[count($list) - 1])) {
            $dt = new DateTime($list[0] . '-01');
            $dtEnd = new DateTime($list[count($list)  - 1] . '-01');
            if ($dt && $dtEnd) {
                $interval = new DateInterval('P1M');
                while ($dt->getTimestamp() <= $dtEnd->getTimestamp()) {
                    $fullList[] = $dt->format('Y-m');
                    $dt->add($interval);
                }

                $list = $fullList;
            }
        }
    }

    protected function prepareGroupingQuarter(&$list)
    {
        sort($list);
        $fullList = [];

        if (isset($list[0]) && isset($list[count($list) - 1])) {
            $startArr = explode('_', $list[0]);
            $endArr = explode('_', $list[count($list)  - 1]);

            $startMonth = str_pad((($startArr[1] - 1) * 3) + 1, 2, '0', \STR_PAD_LEFT);
            $endMonth = str_pad((($endArr[1] - 1) * 3) + 1, 2, '0', \STR_PAD_LEFT);

            $dt = new DateTime($startArr[0] . '-' . $startMonth . '-01');
            $dtEnd = new DateTime($endArr[0] . '-' . $endMonth . '-01');

            if ($dt && $dtEnd) {
                $interval = new DateInterval('P3M');

                while ($dt->getTimestamp() <= $dtEnd->getTimestamp()) {
                    $fullList[] = $dt->format('Y') . '_' . (floor(intval($dt->format('m')) / 3) + 1);
                    $dt->add($interval);
                }

                $list = $fullList;
            }
        }
    }

    protected function prepareGroupingDay(&$list)
    {
        sort($list);
        $fullList = [];

        if (isset($list[0])) {
            $dt = new DateTime($list[0]);
            $dtEnd = new DateTime($list[count($list)  - 1]);

            if ($dt && $dtEnd) {

                $interval = new DateInterval('P1D');

                while ($dt->getTimestamp() <= $dtEnd->getTimestamp()) {
                    $fullList[] = $dt->format('Y-m-d');
                    $dt->add($interval);
                }

                $list = $fullList;
            }
        }
    }

    protected function prepareGroupingYear(&$list)
    {
        sort($list);
        $fullList = [];

        if (isset($list[0])) {
            $dt = new DateTime($list[0] . '-01-01');
            $dtEnd = new DateTime($list[count($list) - 1] . '-01-01');

            if ($dt && $dtEnd) {
                $interval = new DateInterval('P1Y');

                while ($dt->getTimestamp() <= $dtEnd->getTimestamp()) {
                    $fullList[] = $dt->format('Y');
                    $dt->add($interval);
                }

                $list = $fullList;
            }
        }
    }

    protected function prepareGroupingWeek(&$list, $fromMonday = false)
    {
        sort($list);

        usort($list, function ($v1, $v2) {
            list($year1, $week1) = explode('/', $v1);
            list($year2, $week2) = explode('/', $v2);

            if ($year2 > $year1 || $year2 === $year1 && $week2 > $week1) {
                return false;
            }

            return true;
        });

        if (isset($list[0]) && isset($list[count($list) - 1])) {
            $first = $list[0];
            $last = $list[count($list) - 1];

            list($year, $week) = explode('/', $first);
            $week++;
            $dt = new DateTime($year . '-01-01');
            $diff = $this->getConfig()->get('weekStart', 0) - $dt->format('N');

            if ($this->getConfig()->get('weekStart') && $dt->format('N') === '1') {
                $week--;
            }

            if ($diff > 0) {
                $diff -= 7;
            }

            $dt->modify($diff . ' days');
            $dt->modify($week. ' weeks');

            list($year, $week) = explode('/', $last);

            $dtEnd = new DateTime($year . '-01-01');
            $diff = $this->getConfig()->get('weekStart', 0) - $dtEnd->format('N');

            if ($diff > 0) {
                $diff -= 7;
            }

            $dtEnd->modify($diff . ' days');

            if ($this->getConfig()->get('weekStart') && $dt->format('N') === '1') {
                $week--;
            }

            $dtEnd->modify($week . ' weeks');

            if ($dt && $dtEnd) {
                $mSelectList = [];

                while ($dt->getTimestamp() <= $dtEnd->getTimestamp()) {
                    $dItem = $dt->format('Y-m-d');
                    $fItem = $fromMonday ? "WEEK_1:('" . $dItem . "')" : "WEEK_0:('" . $dItem . "')";

                    $mSelectList[] = [$fItem, $dItem];

                    $dt->modify('+1 week');
                }

                if (count($mSelectList)) {
                    $selectParams = [
                        'select' => $mSelectList,
                        'limit' => 1,
                    ];

                    $sql = $this->getEntityManager()->getQuery()->createSelectQuery('Report', $selectParams);
                    $pdo = $this->getEntityManager()->getPDO();
                    $sth = $pdo->prepare($sql);
                    $sth->execute();

                    $row = $sth->fetch(PDO::FETCH_ASSOC);

                    foreach ($row as $item) {
                        if (!in_array($item, $list)) {
                            $list[] = $item;
                        }
                    }

                    sort($list);
                    usort($list, function ($v1, $v2) {
                        list($year1, $week1) = explode('/', $v1);
                        list($year2, $week2) = explode('/', $v2);

                        if ($year2 > $year1 || $year2 === $year1 && $week2 > $week1) {
                            return false;
                        }

                        return true;
                    });
                }
            }

            if (!in_array($first, $list)) {
                array_unshift($list, $first);
            }

            if (!in_array($last, $list)) {
                $list[] = $last;
            }
        }
    }

    protected function executeJointGridReport($data, $user = null, $params = [])
    {
        if (empty($data->joinedReportDataList)) {
            throw new Error("Bad report.");
        }

        $result = null;

        $groupColumn = null;

        $reportList = [];

        foreach ($data->joinedReportDataList as $i => $item) {
            if (empty($item->id)) {
                throw new Error("Bad report.");
            }

            $report = $this->getEntityManager()->getEntity('Report', $item->id);

            if (!$report) {
                throw new Error("Sub-report {$item->id} doesn't exist.");
            }

            $reportList[] = $report;
        }

        foreach ($data->joinedReportDataList as $i => $item) {
            $report = $reportList[$i];

            $where = null;

            if (isset($params['subReportIdWhereMap']) && isset($params['subReportIdWhereMap']->{$item->id})) {
                $where = $params['subReportIdWhereMap']->{$item->id};
            }

            if ($report->get('isInternal')) {
                $reportObj = $this->getInternalReportImpl($report);

                $subReportResult = $reportObj->run($where, [], $user);
            }
            else {
                $type = $report->get('type');

                if ($type !== 'Grid') {
                    throw new Error("Bad sub-report.");
                }

                $this->checkReportIsPosibleToRun($report);

                $subReportEntityType = $report->get('entityType');

                $subReportData = $this->fetchDataFromReport($report);

                $subReportResult = $this->executeGridReport($subReportEntityType, $subReportData, $where, [], $user);
            }

            $subReportResult['columnOriginalMap'] = [];

            $subReportNumericColumnList = $subReportResult['numericColumnList'];
            $subReportResult['numericColumnList'] = [];

            $subReportAggregatedColumnList = $subReportResult['aggregatedColumnList'] ?? null;
            $subReportResult['aggregatedColumnList'] = [];

            $columnToUnsetList = [];

            foreach ($subReportResult['columns'] as $k => &$columnPointer) {
                $originalColumnName = $columnPointer;

                $newColumnName = $columnPointer . '@'. $i;

                $subReportResult['columnOriginalMap'][$newColumnName] = $columnPointer;

                if (in_array($originalColumnName, $subReportNumericColumnList)) {
                    $subReportResult['numericColumnList'][] = $newColumnName;
                }

                if (
                    $subReportAggregatedColumnList &&
                    in_array($originalColumnName, $subReportAggregatedColumnList)
                ) {
                    $subReportResult['aggregatedColumnList'][] = $newColumnName;
                }

                if (
                    isset($subReportAggregatedColumnList) &&
                    !in_array($columnPointer, $subReportAggregatedColumnList)
                ) {
                    $columnToUnsetList[] = $newColumnName;
                }

                $columnPointer = $newColumnName;
            }

            $subReportResult['columns'] = array_filter(
                $subReportResult['columns'],
                function (string $item) use ($columnToUnsetList) {
                    return !in_array($item, $columnToUnsetList);
                }
            );

            $subReportResult['columns'] = array_values($subReportResult['columns']);

            $sums = [];

            foreach ($subReportResult['sums'] as $key => $sum) {
                $sums[$key . '@'. $i] = $sum;
            }

            $subReportResult['sums'] = $sums;

            $columnNameMap = [];

            foreach ($subReportResult['columnNameMap'] as $key => $name) {
                if (strpos($key, '.') === false) {
                    if (!empty($item->label)) {
                        $name = $item->label . '.' . $name;
                    }
                }

                $columnNameMap[$key . '@'. $i] = $name;
            }

            $subReportResult['columnNameMap'] = $columnNameMap;

            $columnTypeMap = [];

            foreach ($subReportResult['columnTypeMap'] as $key => $type) {
                $columnTypeMap[$key . '@'. $i] = $type;
            }

            $subReportResult['columnTypeMap'] = $columnTypeMap;

            $chartColors = [];

            if ($subReportResult['chartColor']) {
                $chartColors[$subReportResult['columns'][0]] = $subReportResult['chartColor'];
            }

            foreach ($subReportResult['chartColors'] as $key => $color) {
                $chartColors[$key . '@'. $i] = $color;
            }
            $subReportResult['chartColors'] = $chartColors;

            $cellValueMaps = (object) [];

            foreach (get_object_vars($subReportResult['cellValueMaps']) as $column => $map) {
                $cellValueMaps->{$column . '@'. $i} = $map;
            }

            $subReportResult['cellValueMaps'] = $cellValueMaps;

            foreach (get_object_vars($subReportResult['reportData']) as $key => $dataItem) {
                $newDataItem = (object) [];

                foreach (get_object_vars($dataItem) as $key1 => $value) {
                    $newDataItem->{$key1 . '@'. $i} = $value;
                }

                $subReportResult['reportData']->$key = $newDataItem;
            }

            if ($i === 0) {
                $groupCount = count($report->get('groupBy'));

                if ($groupCount) {
                    $groupColumn = $report->get('groupBy')[0];
                }

                if ($groupCount > 2) {
                    throw new Error("Group By 2 items is not supported in joint reports.");
                }

                $result = $subReportResult;

                $result['entityTypeList'] = [$report->get('entityType')];

                $result['columnEntityTypeMap'] = [];
                $result['columnReportIdMap'] = [];
                $result['columnSubReportLabelMap'] = [];
            }
            else {
                if (count($report->get('groupBy')) !== $groupCount) {
                    throw new Error("Sub-reports must have the same Group By number.");
                }

                foreach ($subReportResult['columns'] as $column) {
                    $result['columns'][] = $column;
                }

                foreach ($subReportResult['sums'] as $key => $value) {
                    $result['sums'][$key] = $value;
                }

                foreach ($subReportResult['columnNameMap'] as $key => $name) {
                    $result['columnNameMap'][$key] = $name;
                }

                foreach ($subReportResult['columnTypeMap'] as $key => $type) {
                    $result['columnTypeMap'][$key] = $type;
                }

                foreach ($subReportResult['chartColors'] as $key => $value) {
                    $result['chartColors'][$key] = $value;
                }

                foreach ($subReportResult['columnOriginalMap'] as $key => $value) {
                    $result['columnOriginalMap'][$key] = $value;
                }

                foreach (get_object_vars($subReportResult['cellValueMaps']) as $column => $map) {
                    $result['cellValueMaps']->$column = $map;
                }

                foreach ($subReportResult['groupValueMap'] as $group => $map) {
                    if (!array_key_exists($group, $result['groupValueMap'])) {
                    } else {
                        $result['groupValueMap'][$group] = array_merge($result['groupValueMap'][$group], $map);
                    }
                }

                foreach ($subReportResult['numericColumnList'] as $item) {
                    $result['numericColumnList'][] = $item;
                }

                foreach ($subReportResult['aggregatedColumnList'] as $item) {
                    $result['aggregatedColumnList'][] = $item;
                }

                foreach ($subReportResult['grouping'][0] as $groupName) {
                    if (!in_array($groupName, $result['grouping'][0])) {
                        $result['grouping'][0][] = $groupName;
                    }
                }

                foreach (get_object_vars($subReportResult['reportData']) as $key => $dataItem) {
                    if (property_exists($result['reportData'], $key)) {
                        foreach (get_object_vars($dataItem) as $key1 => $value) {
                            $result['reportData']->$key->$key1 = $value;
                        }
                    } else {
                        $result['reportData']->$key = $dataItem;
                    }
                }

                $result['entityTypeList'][] = $report->get('entityType');
            }

            foreach ($subReportResult['columns'] as $column) {
                $result['columnEntityTypeMap'][$column] = $report->get('entityType');
                $result['columnReportIdMap'][$column] = $report->id;

                if (empty($item->label)) {
                    $result['columnSubReportLabelMap'][$column] =
                        $this->getLanguage()->translate($report->get('entityType'), 'scopeNamesPlural');
                } else {
                    $result['columnSubReportLabelMap'][$column] = $item->label;
                }
            }
        }

        if ($groupColumn && isset($result['grouping'][0])) {
            $this->prepareGroupingRange($groupColumn, $result['grouping'][0], $data);
        }

        foreach (get_object_vars($result['reportData']) as $key => $dataItem) {
            foreach ($result['columns'] as $column) {
                if (property_exists($dataItem, $column)) {
                    continue;
                }

                $originalColumn = $result['columnOriginalMap'][$column];
                $originalEntityType = $result['columnEntityTypeMap'][$column];

                list($p, $i) = explode('@', $column);

                $report = $reportList[$i];

                if ($this->isColumnNumeric($originalColumn, $originalEntityType)) {
                    $result['reportData']->$key->$column = 0;

                    continue;
                }

                $value = null;

                if ($groupColumn && $groupColumn !== self::STUB_KEY) {
                    $subReportGroupColumn = $report->get('groupBy')[0];

                    if (strpos($originalColumn, $subReportGroupColumn) === 0) {
                        $displayValue = null;
                        $columnData = $this->getDataFromColumnName($originalEntityType, $originalColumn);
                        $e = $this->getEntityManager()->getEntity($columnData->entityType, $key);

                        if ($e) {
                            $value = $e->get($columnData->field);

                            if ($columnData->fieldType === 'link') {
                                $value = $e->get($columnData->field . 'Id');

                                $displayValue = $e->get($columnData->field . 'Name');
                            } else {
                                $displayValue = $this->getCellDisplayValue($value, $columnData);
                            }
                        }

                        if (!is_null($displayValue)) {
                            if (!empty($result['cellValueMaps']) && !property_exists($result['cellValueMaps'], $column)) {
                                $result['cellValueMaps']->$column = (object) [];
                            }

                            $result['cellValueMaps']->$column->$value = $displayValue;
                        }
                    }
                }

                $result['reportData']->$key->$column = $value;
            }
        }

        $result['columnList'] = $result['columns'];

        $summaryColumnList = [];

        foreach ($result['columnList'] as $column) {
            if ($this->isColumnSummary($column)) {
                $summaryColumnList[] = $column;
            }
        }

        $result['summaryColumnList'] = $summaryColumnList;

        $colorList = [];

        foreach ($result['chartColors'] as $key => $value) {
            if (in_array($value, $colorList)) {
                unset($result['chartColors'][$key]);
            }

            $colorList[] = $value;
        }

        if (empty($result['chartColors'])) {
            $result['chartColors'] = null;
        }

        $result['subListColumnList'] = [];

        $result['chartType'] = $data->chartType;
        $result['isJoint'] = true;

        return $result;
    }

    protected function sanitizeSelectAlias($name)
    {
        if (method_exists($this->getEntityManager()->getQuery(), 'sanitizeSelectAlias')) {
            $name = $this->getEntityManager()->getQuery()->sanitizeSelectAlias($name);
        }

        return $name;
    }

    protected function mergeGroupByColumns(&$rowList, &$groupByList, $key, $columnList)
    {
        foreach ($rowList as &$row) {
            $arr = [];

            foreach ($columnList as $column) {
                $value = $row[$column];

                if (empty($value)) {
                    $value = '';
                }

                $arr[] = $value;
            }
            $row[$key] = implode(':,:', $arr);

            foreach ($columnList as $column) {
                unset($row[$column]);
            }
        }

        foreach ($columnList as $j => $column) {
            foreach ($groupByList as $i => $groupByItem) {
                if ($groupByItem === $column) {
                    if ($j === 0) {
                        $groupByList[$i] = $key;
                    } else {
                        unset($groupByList[$i]);
                    }
                }
            }
        }

        $groupByList = array_values($groupByList);
    }

    protected function buildGrid($entityType, $rows, $params, $columns, &$sums, &$cellValueMaps, $groups = [], $number = 0)
    {
        $k = count($groups);

        $data = [];

        if ($k <= count($params['groupBy']) - 1) {

            $groupColumn = $params['groupBy'][$k];

            $keys = [];

            foreach ($rows as $row) {
                foreach ($groups as $i => $g) {
                    $groupAlias = $this->sanitizeSelectAlias($params['groupBy'][$i]);

                    if ($row[$groupAlias] !== $g) {
                        continue 2;
                    }
                }

                $groupAlias = $this->sanitizeSelectAlias($groupColumn);

                $key = $row[$groupAlias];

                if (!in_array($key, $keys)) {
                    $keys[] = $key;
                }
            }

            foreach ($keys as $number => $key) {
                $gr = $groups;
                $gr[] = $key;
                $data[$key] = $this->buildGrid($entityType, $rows, $params, $columns, $sums, $cellValueMaps, $gr, $number + 1);
            }
        } else {
            $s = &$sums;

            for ($i = 0; $i < count($groups) - 1; $i++) {
                $group = $groups[$i];

                if (!array_key_exists($group, $s)) {
                    $s[$group] = [];
                }

                $s = &$s[$group];
            }

            foreach ($rows as $j => $row) {
                foreach ($groups as $i => $g) {
                    $groupAlias = $this->sanitizeSelectAlias($params['groupBy'][$i]);

                    if ($row[$groupAlias] != $g) {
                        continue 2;
                    }
                }

                foreach ($columns as $column) {
                    $selectAlias = $this->sanitizeSelectAlias($column);

                    if ($this->isColumnNumeric($column, $entityType)) {
                        if (empty($s[$column])) {

                            $s[$column] = 0;

                            if (strpos($column, 'MIN:') === 0) {
                                $s[$column] = null;
                            }
                            else if (strpos($column, 'MAX:') === 0) {
                                $s[$column] = null;
                            }
                        }

                        if (strpos($column, 'COUNT:') === 0) {
                            $value = intval($row[$selectAlias]);
                        }
                        else {
                            $value = floatval($row[$selectAlias]);
                        }

                        if (strpos($column, 'MIN:') === 0) {
                            if (is_null($s[$column]) || $s[$column] >= $value) {
                                $s[$column] = $value;
                            }
                        }
                        else if (strpos($column, 'MAX:') === 0) {
                            if (is_null($s[$column]) || $s[$column] < $value) {
                                $s[$column] = $value;
                            }
                        }
                        else if (strpos($column, 'AVG:') === 0) {
                            $s[$column] = $s[$column] + ($value - $s[$column]) / floatval($number);
                        }
                        else {
                            $s[$column] = $s[$column] + $value;
                        }

                        $data[$column] = $value;
                    }
                    else {
                        $columnData = $this->getDataFromColumnName($entityType, $column);

                        if (!property_exists($cellValueMaps, $column)) {
                            $cellValueMaps->$column = (object) [];
                        }

                        $fieldType = $columnData->fieldType;

                        $value = null;

                        if (array_key_exists($selectAlias, $row)) {
                            $value = $row[$selectAlias];;
                        }

                        if ($fieldType === 'enum') {

                        } else if ($fieldType === 'link') {
                            $selectAlias = $this->sanitizeSelectAlias($column . 'Id');

                            $value = $row[$selectAlias];
                        }

                        $data[$column] = $value;

                        if (!is_null($value) && !property_exists($cellValueMaps->$column, $value)) {
                            $displayValue = $this->getCellDisplayValue($value, $columnData);
                            if (!is_null($displayValue)) {
                                $cellValueMaps->$column->$value = $displayValue;
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }

    protected function getCellDisplayValue($value, $columnData)
    {
        $displayValue = $value;

        $fieldType = $columnData->fieldType;

        if ($fieldType === 'link') {
            if ($value) {
                try {
                    $foreignEntityType = $this->getMetadata()->get(
                        ['entityDefs', $columnData->entityType, 'links', $columnData->field, 'entity']
                    );

                    if ($foreignEntityType) {
                        $e = $this->getEntityManager()->getEntity($foreignEntityType, $value);

                        if ($e) {
                            $displayValue = $e->get('name');
                        }
                    }
                } catch (Exception $e) {}
            }
        }
        else if ($fieldType === 'enum') {
            $displayValue = $this->getLanguage()->translateOption(
                $value, $columnData->field, $columnData->entityType
            );

            $translation = $this->getMetadata()->get(
                ['entityDefs', $columnData->entityType, 'fields', $columnData->field, 'translation']
            );

            if ($translation) {
                $translationMap = $this->getLanguage()->get(explode('.', $translation));

                if (is_array($translationMap) && array_key_exists($value, $translationMap)) {
                    $displayValue = $translationMap[$value];
                }
            }
        } else if ($fieldType === 'datetime' || $fieldType === 'datetimeOptional') {
            if ($value) {
                $displayValue = $this->getInjection('dateTime')->convertSystemDateTime($value);
            }
        } else if ($fieldType === 'date') {
            if ($value) {
                $displayValue = $this->getInjection('dateTime')->convertSystemDate($value);
            }
        }

        if (is_null($displayValue)) {
            $displayValue = $value;
        }

        return $displayValue;
    }

    public function getGridReportResultForExport($id, $where, $currentColumn = null, $user = null, $data = null)
    {
        if (!$data) {
            $data = $this->run($id, $where, null, [], $user);
        }

        $depth = $data['depth'];

        $reportData = $data['reportData'];

        $result = [];

        if ($depth == 2) {
            $groupName1 = $data['groupBy'][0];
            $groupName2 = $data['groupBy'][1];

            $group1NonSummaryColumnList = [];
            $group2NonSummaryColumnList = [];

            if (isset($data['group1NonSummaryColumnList'])) {
                $group1NonSummaryColumnList = $data['group1NonSummaryColumnList'];
            }

            if (isset($data['group2NonSummaryColumnList'])) {
                $group2NonSummaryColumnList = $data['group2NonSummaryColumnList'];
            }

            $row = [];

            $row[] = '';

            foreach ($group2NonSummaryColumnList as $column) {
                $text = $data['columnNameMap'][$column];

                $row[] = $text;
            }

            foreach ($data['grouping'][0] as $gr1) {
                $label = $gr1;

                if (empty($label)) {
                    $label = $this->getLanguage()->translate('-Empty-', 'labels', 'Report');
                }
                else if (!empty($data['groupValueMap'][$groupName1][$gr1])) {
                    $label = $data['groupValueMap'][$groupName1][$gr1];
                }

                $row[] = $label;
            }

            $result[] = $row;

            foreach ($data['grouping'][1] as $gr2) {
                $row = [];
                $label = $gr2;

                if (empty($label)) {
                    $label = $this->getLanguage()->translate('-Empty-', 'labels', 'Report');
                }
                else if (!empty($data['groupValueMap'][$groupName2][$gr2])) {
                    $label = $data['groupValueMap'][$groupName2][$gr2];
                }

                $row[] = $label;

                foreach ($group2NonSummaryColumnList as $column) {
                    $row[] = $this->getCellDisplayValueFromResult(1, $gr2, $column, $data);
                }

                foreach ($data['grouping'][0] as $gr1) {
                    $value = 0;

                    if (!empty($reportData->$gr1) && !empty($reportData->$gr1->$gr2)) {
                        if (!empty($reportData->$gr1->$gr2->$currentColumn)) {
                            $value = $reportData->$gr1->$gr2->$currentColumn;
                        }
                    }

                    $row[] = $value;
                }

                $result[] = $row;
            }

            $row = [];

            $row[] = $this->getLanguage()->translate('Total', 'labels', 'Report');

            foreach ($group2NonSummaryColumnList as $c2) {
                $row[] = '';
            }

            foreach ($data['grouping'][0] as $gr1) {
                $sum = 0;

                if (!empty($data['group1Sums'][$gr1])) {
                    if (!empty($data['group1Sums'][$gr1][$currentColumn])) {
                        $sum = $data['group1Sums'][$gr1][$currentColumn];
                    }
                }

                $row[] = $sum;
            }

            $result[] = $row;

            if (count($group1NonSummaryColumnList)) {
                $result[] = [];
            }

            foreach ($group1NonSummaryColumnList as $column) {
                $row = [];
                $text = $data['columnNameMap'][$column];
                $row[] = $text;

                foreach ($group2NonSummaryColumnList as $c2) {
                    $row[] = '';
                }

                foreach ($data['grouping'][0] as $gr1) {
                    $row[] = $this->getCellDisplayValueFromResult(0, $gr1, $column, $data);
                }

                $result[] = $row;
            }

        } else if ($depth == 1 || $depth === 0) {
            $aggregatedColumnList = $data['aggregatedColumnList'] ?? $data['columnList'];

            if ($depth == 1) {
                $groupName = $data['groupBy'][0];
            }
            else {
                $groupName = self::STUB_KEY;
            }

            $row = [];
            $row[] = '';

            foreach ($aggregatedColumnList as $column) {
                $label = $column;

                if (!empty($data['columnNameMap'][$column])) {
                    $label = $data['columnNameMap'][$column];
                }

                $row[] = $label;
            }

            $result[] = $row;

            foreach ($data['grouping'][0] as $gr) {
                $row = [];

                $label = $gr;

                if (empty($label)) {
                    $label = $this->getLanguage()->translate('-Empty-', 'labels', 'Report');
                }
                else if (
                    !empty($data['groupValueMap'][$groupName]) && array_key_exists($gr, $data['groupValueMap'][$groupName])
                ) {
                    $label = $data['groupValueMap'][$groupName][$gr];
                }

                $row[] = $label;

                $columnList = $data['aggregatedColumnList'] ?? $data['columnList'];


                foreach ($aggregatedColumnList as $column) {
                    if (in_array($column, $data['numericColumnList'])) {
                        $value = 0;

                        if (!empty($reportData->$gr)) {
                            if (!empty($reportData->$gr->$column)) {
                                $value = $reportData->$gr->$column;
                            }
                        }
                    }
                    else {
                        $value = '';

                        if (property_exists($reportData, $gr) && property_exists($reportData->$gr, $column)) {
                            $value = $reportData->$gr->$column;

                            if (
                                !is_null($value)
                                &&
                                !empty($data['cellValueMaps']) && property_exists($data['cellValueMaps'], $column)
                                &&
                                property_exists($data['cellValueMaps']->$column, $value)
                            ) {
                                $value = $data['cellValueMaps']->$column->$value;
                            }
                        }
                    }

                    $row[] = $value;
                }

                $result[] = $row;
            }

            if ($depth) {
                $row = [];

                $row[] = $this->getLanguage()->translate('Total', 'labels', 'Report');

                foreach ($aggregatedColumnList as $column) {
                    if (!in_array($column, $data['numericColumnList'])) {
                        $row[] = '';

                        continue;
                    }

                    $sum = 0;

                    if (!empty($data['sums'][$column])) {
                        $sum = $data['sums'][$column];
                    }

                    $row[] = $sum;
                }

                $result[] = $row;
            }
        }

        return $result;
    }

    public function getCellDisplayValueFromResult($grountIndex, $gr1, $column, $data)
    {
        $groupName = $data['groupByList'][$grountIndex];

        $dataMap = $data['nonSummaryData']->$groupName;

        $value = '';

        if ($this->isColumnNumeric($column, $data['entityType'])) {
            $value = 0;
        }

        if (
            property_exists($dataMap, $gr1)
            &&
            property_exists($dataMap->$gr1, $column)
        ) {
            $value = $dataMap->$gr1->$column;
        }

        if (!$this->isColumnNumeric($column, $data['entityType']) && !is_null($value)) {
            if (!empty($data['cellValueMaps']) && property_exists($data['cellValueMaps'], $column)) {
                if (property_exists($data['cellValueMaps']->$column, $value)) {
                    $value = $data['cellValueMaps']->$column->$value;
                }
            }
        }

        if (is_null($value)) {
            $value = '';
        }

        return $value;
    }

    public function getGridReportCsv($id, $where, $column = null, $user = null)
    {
        $result = $this->getGridReportResultForExport($id, $where, $column, $user);

        $delimiter = $this->getConfig()->get('exportDelimiter', ';');

        $fp = fopen('php://temp', 'w');

        foreach ($result as $row) {
            fputcsv($fp, $row, $delimiter);
        }

        rewind($fp);
        $csv = stream_get_contents($fp);
        fclose($fp);

        return $csv;
    }

    public function getDataFromColumnName($entityType, $column, $result = null)
    {
        if ($result && !empty($result['isJoint'])) {
            $entityType = $result['columnEntityTypeMap'][$column];
            $column = $result['columnOriginalMap'][$column];
        }

        $field = $column;
        $link = null;
        $function = null;

        if (strpos($field, ':') !== false) {
            list($function, $field) = explode(':', $field);
        }

        if (strpos($field, '.') !== false) {
            list($link, $field) = explode('.', $field);
            $scope = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);
        } else {
            $scope = $entityType;
        }

        $fieldType = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field, 'type']);

        return (object) [
            'function' => $function,
            'field' => $field,
            'entityType' => $scope,
            'link' => $link,
            'fieldType' => $fieldType,
        ];
    }

    public function exportGridXlsx($id, $where, $user = null)
    {
        $report = $this->getEntityManager()->getEntity('Report', $id);

        if (!$report) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($report, 'read')) {
            throw new Forbidden();
        }

        $contents = $this->getGridReportXlsx($id, $where, $user);

        $name = $report->get('name');
        $name = preg_replace("/([^\w\s\d\-_~,;:\[\]\(\).])/u", '_', $name) . ' ' . date('Y-m-d');

        $mimeType = $this->getMetadata()->get(['app', 'export', 'formatDefs', 'xlsx', 'mimeType']);
        $fileExtension = $this->getMetadata()->get(['app', 'export', 'formatDefs', 'xlsx', 'fileExtension']);

        $fileName = $name . '.' . $fileExtension;

        $attachment = $this->getEntityManager()->getEntity('Attachment');

        $attachment->set('name', $fileName);
        $attachment->set('role', 'Export File');
        $attachment->set('type', $mimeType);
        $attachment->set('contents', $contents);
        $attachment->set([
            'relatedType' => 'Report',
            'relatedId' => $id,
        ]);

        $this->getEntityManager()->saveEntity($attachment);

        return (object) [
            'id' => $attachment->id
        ];
    }

    public function exportGridCsv($id, $where, $column = null, $user = null)
    {
        $report = $this->getEntityManager()->getEntity('Report', $id);

        if (!$report) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($report, 'read')) {
            throw new Forbidden();
        }

        $contents = $this->getGridReportCsv($id, $where, $column, $user);

        $name = $report->get('name');
        $name = preg_replace("/([^\w\s\d\-_~,;:\[\]\(\).])/u", '_', $name) . ' ' . date('Y-m-d');

        $mimeType = $this->getMetadata()->get(['app', 'export', 'formatDefs', 'csv', 'mimeType']);
        $fileExtension = $this->getMetadata()->get(['app', 'export', 'formatDefs', 'csv', 'fileExtension']);

        $fileName = $name . '.' . $fileExtension;

        $attachment = $this->getEntityManager()->getEntity('Attachment');
        $attachment->set('name', $fileName);
        $attachment->set('role', 'Export File');
        $attachment->set('type', $mimeType);
        $attachment->set('contents', $contents);
        $attachment->set([
            'relatedType' => 'Report',
            'relatedId' => $id,
        ]);

        $this->getEntityManager()->saveEntity($attachment);

        return (object) [
            'id' => $attachment->id
        ];
    }

    public function getGridReportXlsx($id, $where, $user = null)
    {
        $report = $this->getEntityManager()->getEntity('Report', $id);

        $entityType = $report->get('entityType');

        $groupCount = count($report->get('groupBy'));
        $columnList = $report->get('columns');

        $groupBy = $report->get('groupBy');

        $reportResult = null;

        if ($report->get('type') === 'JointGrid' || !$report->get('groupBy')) {
            $reportResult = $this->run($id, $where, null, [], $user);

            $columnList = $reportResult['columns'];

            $groupCount = count($reportResult['groupBy']);

            $groupBy = $reportResult['groupBy'];
        }

        if (!$reportResult) {
            $reportResult = $this->run($id, $where, null, [], $user);
        }

        $result = [];

        if ($groupCount === 2) {
            foreach ($reportResult['summaryColumnList'] as $column) {
                $resultItem = $this->getGridReportResultForExport($id, $where, $column, $user, $reportResult);
                $result[] = $resultItem;
            }
        } else {
            $result[] = $this->getGridReportResultForExport($id, $where, null, $user, $reportResult);
        }

        $columnTypes = [];

        foreach ($columnList as $item) {
            $columnData = $this->getDataFromColumnName($entityType, $item, $reportResult);
            $type = $this->getMetadata()->get(['entityDefs', $columnData->entityType, 'fields', $columnData->field, 'type']);

            if ($entityType === 'Opportunity' && $columnData->field === 'amountWeightedConverted') {
                $type = 'currencyConverted';
            }

            if ($columnData->function === 'COUNT') {
                $type = 'int';
            }

            $columnTypes[$item] = $type;
        }

        $columnLabels = [];

        if ($groupCount === 2) {
            foreach ($columnList as $column) {
                $columnLabels[$column] = $this->translateColumnName($entityType, $column);
            }
        }

        $exportParams = [
            'exportName' => $report->get('name'),
            'columnList' => $columnList,
            'columnTypes' => $columnTypes,
            'chartType' => $report->get('chartType'),
            'groupCount' => $groupCount,
            'groupByList' => $groupBy,
            'columnLabels' => $columnLabels,
            'is2d' => $groupCount === 2,
            'reportResult' => $reportResult,
        ];

        if ($groupCount) {
            $group = $report->get('groupBy')[count($report->get('groupBy')) - 1];
            $exportParams['groupLabel'] = $this->translateColumnName($entityType, $group);
        } else {
            $exportParams['groupLabel'] = '';
        }

        $exportClassName = 'Espo\\Modules\\Advanced\\Core\\Report\\ExportXlsx';

        if (!class_exists('PhpOffice\\PhpSpreadsheet\\Spreadsheet')) {
            $exportClassName .= 'Deprecated';
        }

        $exportObj = $this->getInjection('injectableFactory')->createByClassName($exportClassName);

        return $exportObj->process($entityType, $exportParams, $result);
    }

    protected function translateColumnName($entityType, $item)
    {
        if (strpos($item, ':(') !== false) {
            return '';
        }

        $field = $item;
        $function = null;
        if (strpos($item, ':') !== false) {
            list($function, $field) = explode(':', $item);
        }

        $groupLabel = '';
        $entityTypeLocal = $entityType;

        if (strpos($field, '.') !== false) {
            list($link, $field) = explode('.', $field);
            $entityTypeLocal = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);
            //$groupLabel .= $this->getInjection('language')->translate($link, 'links', $entityType);
            //$groupLabel .= '.';
        }

        if ($this->getMetadata()->get(['entityDefs', $entityTypeLocal, 'fields', $field, 'type']) == 'currencyConverted') {
            $field = str_replace('Converted', '', $field);
        }

        $groupLabel .= $this->getInjection('language')->translate($field, 'fields', $entityTypeLocal);

        if ($function) {
            $functionLabel = $this->getInjection('language')->translate($function, 'functions', 'Report');

            if ($function === 'COUNT' && $field === 'id') {
                return $functionLabel;
            }

            if ($function && $function !== 'SUM') {
                $groupLabel = $functionLabel . ': ' . $groupLabel;
            }
        }
        return $groupLabel;
    }

    public function populateTargetList($id, $targetListId)
    {
        $report = $this->getEntityManager()->getEntity('Report', $id);

        if (!$report) {
            throw new NotFound();
        }

        if (!$this->getAcl()->check($report, 'read')) {
            throw new Forbidden();
        }

        $targetList = $this->getEntityManager()->getEntity('TargetList', $targetListId);
        if (!$targetList) {
            throw new NotFound();
        }
        if (!$this->getAcl()->check($targetList, 'edit')) {
            throw new Forbidden();
        }

        if ($report->get('type') != 'List') {
            throw new Error("Report is not of 'List' type.");
        }

        $entityType = $report->get('entityType');

        switch ($entityType) {
            case 'Contact':
                $link = 'contacts';
                break;
            case 'Lead':
                $link = 'leads';
                break;
            case 'User':
                $link = 'users';
                break;
            case 'Account':
                $link = 'accounts';
                break;
            default:
                throw new Error();
        }

        $data = $report->get('data');

        if (empty($data)) {
            $data = new StdClass();
        }

        $data->orderBy = $report->get('orderBy');
        $data->columns = $report->get('columns');
        $data->entityType = $report->get('entityType');

        if (!$data->orderBy) $data->orderBy = [];

        if ($report->get('filtersData') && !$report->get('filtersDataList')) {
            $data->filtersWhere = $this->convertFiltersData($report->get('filtersData'));
        } else {
            $data->filtersWhere = $this->convertFiltersDataList($report->get('filtersDataList'), $entityType);
        }

        $rawParams = [];

        $selectManager = $this->getSelectManagerFactory()->create($entityType);

        $params = $selectManager->getSelectParams($rawParams);

        if (!empty($data->filtersWhere)) {
            $filtersWhere = json_decode(json_encode($data->filtersWhere), true);
            $this->handleFilters($filtersWhere, $entityType, $params);
        }

        $this->getEntityManager()->getRepository('TargetList')->massRelate($targetList, $link, $params);
    }

    public function syncTargetListWithReports(Entity $targetList)
    {
        if (!$this->getAcl()->check($targetList, 'edit')) {
            throw new Forbidden();
        }

        $targetListService = $this->getServiceFactory()->create('TargetList');

        if ($targetList->get('syncWithReportsUnlink')) {
            $targetListService->unlinkAll($targetList->id, 'contacts');
            $targetListService->unlinkAll($targetList->id, 'leads');
            $targetListService->unlinkAll($targetList->id, 'accounts');
            $targetListService->unlinkAll($targetList->id, 'users');
        }
        $reportList = $this->getEntityManager()->getRepository('TargetList')->findRelated($targetList, 'syncWithReports');

        foreach ($reportList as $report) {
            $this->populateTargetList($report->id, $targetList->id);
        }

        return true;
    }

    public function exportList($id, $where = null, array $params = null, $user = null)
    {
        $additionalParams = [
            'isExport' => true
        ];

        if (!array_key_exists('fieldList', $params)) {
            $additionalParams['fullSelect'] = true;
        } else {
            $additionalParams['customColumnList'] = $params['fieldList'];

            foreach ($additionalParams['customColumnList'] as $i => $item) {
                if (strpos($item, '_') !== false) {
                    $additionalParams['customColumnList'][$i] = str_replace('_', '.', $item);
                }
            }
        }

        if (!empty($params['ids']) && is_array($params['ids'])) {
            if (is_null($where)) {
                $where = [];
            }

            $where[] = [
                'type' => 'equals',
                'attribute' => 'id',
                'value' => $params['ids']
            ];
        }

        $reportParams = [
            'orderBy' => $params['orderBy'],
            'order' => $params['order'],
            'groupValue' => $params['groupValue'],
            'groupIndex' => $params['groupIndex'],
        ];

        if (array_key_exists('groupValue2', $params)) {
            $reportParams['groupValue2'] = $params['groupValue2'];
        }

        $resultData = $this->run($id, $where, $reportParams, $additionalParams, $user);

        $report = $this->getEntity($id);

        $entityType = $report->get('entityType');

        if (!array_key_exists('collection', $resultData)) {
            throw new Error();
        }

        $collection = $resultData['collection'];

        $service = $this->getRecordService($entityType);

        $exportParams = [];

        if (array_key_exists('attributeList', $params)) {
            $attributeList = [];

            foreach ($params['attributeList'] as $attribute) {
                if (strpos($attribute, '_')) {
                    list($link, $field) = explode('_', $attribute);

                    $foreignType = $this->getForeignFieldType($entityType, $link, $field);

                    if ($foreignType === 'link') {
                        $attributeList[] = $attribute . 'Id';
                        $attributeList[] = $attribute . 'Name';

                        continue;
                    }
                }

                $attributeList[] = $attribute;
            }

            $exportParams['attributeList'] = $attributeList;
        }

        if (array_key_exists('fieldList', $params)) {
            $exportParams['fieldList'] = $params['fieldList'];
        }

        if (array_key_exists('format', $params)) {
            $exportParams['format'] = $params['format'];
        }

        $exportParams['exportName'] = $report->get('name');

        $exportParams['fileName'] = $report->get('name') . ' ' . date('Y-m-d');

        return $service->exportCollection($exportParams, $collection);
    }

    protected function filterInput($data)
    {
        parent::filterInput($data);

        if ($this->getAcl()->get('portalPermission') === 'no') {
            unset($data->portalsIds);
        }
    }

    public function prepareEntityForOutput(Entity $entity)
    {
        parent::prepareEntityForOutput($entity);

        if ($this->getAcl()->get('portalPermission') === 'no') {
            $entity->clear('portalsIds');
            $entity->clear('portalsNames');
        }
    }

    public function getReportResultsTableData(string $id, $where = null, ?string $column = null, $userId = null)
    {
        $data = (object) [];

        $data->userId = $userId ?? $this->getUser()->id;
        $data->specificColumn = $column;

        $result = $this->run($id, $where);

        $result = (isset($result['collection']) && is_object($result['collection'])) ?
            $result['collection']->toArray() :
            $result;

        $report = $this->getEntityManager()->getEntity('Report', $id);

        if (!$report) {
            throw new NotFound();
        }

        $this->getServiceFactory()->create('ReportSending')->buildData($data, $result, $report);

        return $data->tableData ?? [];
    }

    public function exportPdf(string $id, $where = null, string $templateId, ?string $userId = null)
    {
        $report = $this->getEntityManager()->getEntity('Report', $id);
        $template = $this->getEntityManager()->getEntity('Template', $templateId);

        if (!$report || !$template) {
            throw new NotFound();
        }

        if ($userId) {
            $user = $this->getEntityManager()->getEntity('User', $userId);

            if (!$user) {
                throw new Error();
            }

            $aclManager = $this->getInjection('aclManager');

            if (!$aclManager->check($user, $report)) {
                throw new Forbidden();
            }

            if (!$aclManager->check($user, $template)) {
                throw new Forbidden();
            }
        }

        $additionalData = [
            'userId' => $userId,
            'reportWhere' => $where,
        ];

        $contents = $this->getServiceFactory()->create('Pdf')->buildFromTemplate($report, $template, false, $additionalData);

        $attachment = $this->getEntityManager()->createEntity('Attachment', [
            'contents' => $contents,
            'role' => 'Export File',
            'type' => 'application/pdf',
            'relatedId' => $id,
            'relatedType' => 'Report',
        ]);

        return $attachment->id;
    }

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);

        if ($entity->get('type') === 'Grid') {
            $chartDataList = $entity->get('chartDataList');

            if ($chartDataList && count($chartDataList)) {
                $y = $chartDataList[0]->columnList ?? null;
                $y2 = $chartDataList[0]->y2ColumnList ?? null;

                $entity->set('chartOneColumns', $y);
                $entity->set('chartOneY2Columns', $y2);
            }
        }
    }

    protected static function getAllAttributesFromComplexExpression(string $expression) : ?array
    {
        if (
            class_exists('Espo\\ORM\\QueryComposer\\Util') &&
            method_exists('Espo\\ORM\\QueryComposer\\Util', 'getAllAttributesFromComplexExpression')
        ) {
            return \Espo\ORM\QueryComposer\Util::getAllAttributesFromComplexExpression($expression);
        }

        if (
            class_exists('Espo\\ORM\\DB\\Query\\Base') &&
            method_exists('Espo\\ORM\\DB\\Query\\Base', 'getAllAttributesFromComplexExpression')
        ) {
            return \Espo\ORM\DB\Query\Base::getAllAttributesFromComplexExpression($expression);
        }

        return null;
    }

    protected function getCustomEntityFactory(): CustomEntityFactory
    {
        if (!$this->customEntityFactory) {
            $injectableFactory = $this->injectableFactory ?? $this->getContainer()->get('injectableFactory');

            $this->customEntityFactory = new CustomEntityFactory(
                $injectableFactory,
                $this->getEntityManager(),
                $this->getConfig()
            );
        }

        return $this->customEntityFactory;
    }
}
