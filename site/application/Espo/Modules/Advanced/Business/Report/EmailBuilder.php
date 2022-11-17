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

namespace Espo\Modules\Advanced\Business\Report;

use Espo\ORM\Entity;
use Espo\Core\Utils\DateTime;
use Espo\Core\Exceptions\Error;

use Espo\Core\{
    FileStorage\Manager as FileStorageManager,
};

class EmailBuilder
{
    protected $entityManager;

    protected $smtpParams;

    protected $mailSender;

    protected $config;

    protected $dateTime;

    protected $metadata;

    protected $language;

    protected $htmlizer;

    protected $user;

    protected $preferences;

    protected $templateFileManager;

    protected $reportService;

    protected $fileStorageManager;

    protected $isLocal = false;

    public function __construct(
        $metadata,
        $entityManager,
        $smtpParams,
        $mailSender,
        $config,
        $language,
        $htmlizer,
        $templateFileManager,
        $reportService,
        ?FileStorageManager $fileStorageManager = null
    ) {
        $this->metadata = $metadata;
        $this->entityManager = $entityManager;
        $this->smtpParams = $smtpParams;
        $this->mailSender = $mailSender;
        $this->config = $config;
        $this->language = $language;
        $this->htmlizer = $htmlizer;
        $this->templateFileManager = $templateFileManager;
        $this->reportService = $reportService;
        $this->fileStorageManager = $fileStorageManager;
    }

    protected function getHtmlizer()
    {
        return $this->htmlizer;
    }

    protected function getEntityManager()
    {
        return $this->entityManager;
    }

    protected function getTemplateFileManager()
    {
        return $this->templateFileManager;
    }

    protected function getLanguage()
    {
        return $this->language;
    }

    protected function getMetadata()
    {
        return $this->metadata;
    }

    protected function getConfig()
    {
        return $this->config;
    }

    /**
     * Images will be included.
     */
    public function setIsLocal(bool $isLocal)
    {
        $this->isLocal = $isLocal;
    }

    protected function initForUserById($userId)
    {
        $this->user = $this->getEntityManager()->getEntity('User', $userId);
        if (!$this->user) {
            throw Error('Report Sending Builder: No User with id = ' . $userId);
        }
        $this->preferences = $this->getEntityManager()->getEntity('Preferences', $userId);
        $this->language->setLanguage($this->getPreference('language'));
        $this->dateTime = new DateTime(
            $this->getPreference('dateFormat'),
            $this->getPreference('timeFormat'),
            $this->getPreference('timeZone'));
    }

    protected function getPreference($attribute)
    {
        $hasAttr = true;
        switch ($attribute) {
            case 'weekStart': $hasAttr = ($this->preferences->get($attribute) == -1) ? false : true;
            default: $hasAttr = ($this->preferences->get($attribute) == '') ? false : true;
        }
        return ($hasAttr) ? $this->preferences->get($attribute) : $this->getConfig()->get($attribute);
    }

    public function buildEmailData(&$data, $reportResult, $report)
    {
        if (!is_object($report)) {
            throw new Error('Report Sending Builder: no report.');
        }
        if (!is_object($data) || !isset($data->userId)) {
            throw new Error('Report Sending Builder: Not enough data for sending email.');
        }
        $this->initForUserById($data->userId);

        $type = $report->get('type');
        switch ($type) {
            case 'Grid':
            case 'JointGrid':
                $this->buildEmailGridData($data, $reportResult, $report);
                break;
            case 'List':
                $this->buildEmailListData($data, $reportResult, $report);
                break;
        }

        return true;
    }

    protected function buildEmailGridData(&$data, $reportResult, $report)
    {
        $depth = (int) $reportResult['depth'];
        $methodName = 'buildEmailGrid' . $depth. 'Data';

        if (method_exists($this, $methodName)) {
            $this->$methodName($data, $reportResult, $report);
        } else {
             throw new Error("Report Sending Builder: Unavailable grid type [$depth]");
        }
    }

    protected function buildEmailListData(&$data, $reportResult, $report)
    {
        $entityType = $report->get('entityType');
        $columns = $report->get('columns');
        $columnsDataValue = $report->get('columnsData');
        if ($columnsDataValue instanceof \StdClass) {
            $columnsData = get_object_vars($columnsDataValue);
        } else if (is_array($columnsDataValue)) {
            $columnsData = $columnsDataValue;
        } else {
            $columnsData = [];
        }

        $entity = $this->getEntityManager()->getEntity($entityType);

        if (empty($entity)) {
            throw new Error('Report Sending Builder: Entity type "' . $entityType . '" is not available');
        }

        $fields = $this->getMetadata()->get(['entityDefs', $entityType, 'fields']);

        $columnAttributes = [];
        foreach ($columns as $column) {
            $columnData = (isset($columnsData[$column])) ? $columnsData[$column] : null;
            $attrs = [];
            if (is_object($columnData)) {
                if (isset($columnData->width)) {
                    $attrs['width'] = $columnData->width . '%';
                }
                if (isset($columnData->align)) {
                    $attrs['align'] = $columnData->align;
                }
            }
            $columnAttributes[$column] = $attrs;
        }

        $columnTitles = [];
        foreach ($columns as $column) {
            $field = $column;
            $scope = $entityType;
            $isForeign = false;
            if (strpos($column, '.') !== false) {
                $isForeign = true;
                list($link, $field) = explode('.', $column);
                $scope = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);
                $fields[$column] = $this->getMetadata()->get(['entityDefs', $scope, 'fields', $field]);
                $fields[$column]['scope'] = $scope;
                $fields[$column]['isForeign'] = true;
            }
            $label = $this->language->translate($field, 'fields', $scope);
            if ($isForeign) {
                $label = $this->language->translate($link, 'links', $entityType) . '.' . $label;
            }

            $columnTitles[] = [
                'label' => $label,
                'attrs' => $columnAttributes[$column],
                'value' => $label,
                'isBold' => true,
            ];
        }

        $rows = [];

        foreach ($reportResult as $recordKey => $record) {
            foreach ($columns as $columnKey => $column) {
                $type = (isset($fields[$column])) ? $fields[$column]['type'] : '';
                $columnInRecord = str_replace('.', '_', $column);

                $value = $record[$columnInRecord] ?? null;
                $value = (!is_null($value) && is_scalar($value)) ? (string) $record[$columnInRecord] : '';

                switch ($type) {
                    case 'date':
                        if (!empty($value)) {
                            $value = $this->dateTime->convertSystemDate($value);
                        }
                        break;
                    case 'datetime':
                    case 'datetimeOptional':
                        if (!empty($value)) {
                            $value = $this->dateTime->convertSystemDateTime($value);
                        }
                        break;
                    case 'link':
                    case 'linkParent':
                        if (!empty($record[$columnInRecord . 'Name'])) {
                            $value = $record[$columnInRecord . 'Name'];
                        }
                        break;
                    case 'linkMultiple':
                        if (!empty($record[$columnInRecord . 'Names'])) {
                            $value = implode(', ', array_values( (array) $record[$columnInRecord . 'Names']));
                        }
                        break;
                    case 'jsonArray': break;
                    case 'bool': $value = ($value) ? '1' : '0'; break;
                    case 'enum':
                        if (isset($fields[$column]['isForeign']) && $fields[$column]['isForeign']) {
                            list($link, $field) = explode('.', $column);
                            $value = $this->language->translateOption($value, $field, $fields[$column]['scope']);
                        } else {
                            $value = $this->language->translateOption($value, $column, $entityType);
                        }
                        break;
                    case 'int':
                        $value = $this->formatInt($value);
                        break;
                    case 'float':
                        $isCurrency = isset($fields[$column]['view']) && strpos($fields[$column]['view'], 'currency-converted');
                        $value = ($isCurrency) ? $this->formatCurrency($value) : $this->formatFloat($value);
                        break;
                    case 'currency':
                        $value = $this->formatCurrency($value, $record[$columnInRecord . 'Currency']);
                        break;
                    case 'currencyConverted':
                        $value = $this->formatCurrency($value);
                        break;
                    case 'address':
                        $value = '';
                        if (!empty($record[$columnInRecord . 'Street'])) {
                            $value = $value .= $record[$columnInRecord.'Street'];
                        }
                        if (!empty($record[$columnInRecord.'City']) || !empty($record[$columnInRecord.'State']) || !empty($record[$columnInRecord.'PostalCode'])) {
                            if ($value) {
                                $value .= "  ";
                            }
                            if (!empty($record[$columnInRecord.'City'])) {
                                $value .= $record[$columnInRecord.'City'];
                                if (
                                    !empty($record[$columnInRecord.'State']) || !empty($record[$columnInRecord.'PostalCode'])
                                ) {
                                    $value .= ', ';
                                }
                            }
                            if (!empty($record[$columnInRecord.'State'])) {
                                $value .= $record[$columnInRecord.'State'];
                                if (!empty($record[$columnInRecord.'PostalCode'])) {
                                    $value .= ' ';
                                }
                            }
                            if (!empty($record[$columnInRecord.'PostalCode'])) {
                                $value .= $record[$columnInRecord.'PostalCode'];
                            }
                        }
                        if (!empty($record[$columnInRecord.'Country'])) {
                            if ($value) {
                                $value .= "  ";
                            }
                            $value .= $record[$columnInRecord.'Country'];
                        }
                            break;
                        case 'array':
                        case 'multiEnum':
                        case 'checklist':
                            $value = $record[$columnInRecord] ?? [];
                            if (is_array($value)) {
                                $value = implode(", ", $value);
                            } else {
                                $value = '';
                            }
                            break;

                    case 'image':
                        if (!$this->isLocal) {
                            break;
                        }

                        $attachmentId = $record[$columnInRecord . 'Id'] ?? null;

                        if ($attachmentId) {
                            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);

                            if ($attachment) {
                                $filePath = $this->fileStorageManager->getLocalFilePath($attachment);

                                $value = "<img src=\"{$filePath}\">";
                            }
                        }

                        break;
                    }

                $rows[$recordKey][$columnKey] = [
                    'value' => $value,
                    'attrs' => $columnAttributes[$column],
                ];
            }
        }
        $bodyData = [
            'columnList' => $columnTitles,
            'rowList' => $rows,
            'noDataLabel' => $this->getLanguage()->translate('No Data')
        ];

        $subject = $this->htmlizeTemplate($report, 'reportSendingList', 'subject');
        $body = $this->htmlizeTemplate($report, 'reportSendingList', 'body', $bodyData);

        $data->emailSubject = $subject;
        $data->emailBody = $body;

        $data->tableData = array_merge([$columnTitles], $rows);


        return true;
    }

    protected function buildEmailGrid0Data(&$data, $reportResult, $report)
    {
        return $this->buildEmailGrid1Data($data, $reportResult, $report);
    }

    protected function buildEmailGrid1Data(&$data, $reportResult, $report)
    {
        $reportData = $reportResult['reportData'];

        $rows = [];

        $groupCount = count($reportResult['groupBy']);

        if ($groupCount) {
            $groupName = $reportResult['groupBy'][0];
        } else {
            $groupName = '__STUB__';
        }

        $row = [];

        $row[] = ['value' => ''];

        $columnTypes = [];

        $hasSubListColumns = count($reportResult['subListColumnList'] ?? []) > 0;

        foreach ($reportResult['columnList'] as $column) {
            $allowedTypeList = ['int', 'float', 'currency', 'currencyConverted'];

            $columnType = null;
            $function = null;

            if (strpos($column, ':')) {
                list($function, $field) = explode(':', $column);
            }
            else {
                $field = $column;
            }

            $fieldEntityType = $report->get('entityType');

            $columnType = $reportResult['columnTypeMap'][$column] ?? null;

            if (strpos($column, '.')) {
                list($link, $field) = explode('.', $column);

                $fieldEntityType = $this->getMetadata()->get(
                    ['entityDefs', $fieldEntityType, 'links', $link, 'entity']
                );
            }

            if (!$columnType) {
                if ($function === 'COUNT') {
                    $columnType = 'int';
                }
                else {
                    $columnType = $this->getMetadata()->get(
                        ['entityDefs', $fieldEntityType, 'fields', $field, 'type']
                    );
                }
            }

            $columnTypes[$column] = (in_array($columnType, $allowedTypeList)) ? $columnType : 'float';

            $label = $column;

            if (isset($reportResult['columnNameMap'][$column])) {
                $label = $reportResult['columnNameMap'][$column];
            }

            $row[] = [
                'value' => $label,
                'isBold' => true,
            ];
        }

        $rows[] = $row;

        foreach ($reportResult['grouping'][0] as $gr) {
            $rows[] = $this->buildEmailGrid1GroupingRow(
                $gr, $groupName, $reportData, $reportResult, $columnTypes
            );

            if ($hasSubListColumns) {
                $rows = array_merge(
                    $rows,
                    $this->buildEmailGrid1SubListRowList($gr, $reportResult, $columnTypes)
                );

                $rows[] = $this->buildEmailGrid1GroupingRow(
                    $gr, $groupName, $reportData, $reportResult, $columnTypes, true
                );
            }
        }

        if ($groupCount) {
            $row = [];

            $totalLabel = $this->getLanguage()->translate('Total', 'labels', 'Report');

            $row[] = [
                'value' => $totalLabel,
                'isBold' => true,
            ];

            foreach ($reportResult['columns'] as $column) {
                if (
                    in_array($column, $reportResult['numericColumnList']) &&
                    (
                        !isset($reportResult['aggregatedColumnList']) ||
                        in_array($column, $reportResult['aggregatedColumnList'])
                    )
                ) {
                    $sum = 0;

                    if (isset($reportResult['sums'][$column])) {
                        $sum = $reportResult['sums'][$column];

                        switch ($columnTypes[$column]) {
                            case 'int': $sum = $this->formatInt($sum); break;
                            case 'float': $sum = $this->formatFloat($sum); break;
                            case 'currency': ;
                            case 'currencyConverted': $sum = $this->formatCurrency($sum); break;
                        }
                    }
                }
                else {
                    $sum = '';
                }

                $row[] = [
                    'value' => $sum,
                    'isBold' => true,
                    'attrs' => ['align' => 'right'],
                ];
            }

            $rows[] = $row;
        }
        else {
            foreach ($rows as &$row) {
                unset($row[0]);
            }
        }

        $bodyData = [
            'rowList' => $rows,
        ];

        $subject = $this->htmlizeTemplate($report, 'reportSendingGrid1', 'subject');
        $body = $this->htmlizeTemplate($report, 'reportSendingGrid1', 'body', $bodyData);

        $data->emailSubject = $subject;
        $data->emailBody = $body;

        $data->tableData = $rows;

        return true;
    }

    protected function buildEmailGrid1GroupingRow(
        string $gr,
        string $groupName,
        object $reportData,
        array $reportResult,
        array $columnTypes,
        bool $onlyNumeric = false
    ) : array {

        $row = [];

        $hasSubListColumns = count($reportResult['subListColumnList'] ?? []) > 0;

        $label = $gr;

        if (empty($label)) {
            $label = $this->getLanguage()->translate('-Empty-', 'labels', 'Report');
        }
        else if (isset($reportResult['groupValueMap'][$groupName][$gr])) {
            $label = $reportResult['groupValueMap'][$groupName][$gr];
        }

        if (strpos($groupName , ':')) {
            list($function, $field) = explode(':', $groupName);

            $label = $this->handleDateGroupValue($function, $label);
        }

        if ($hasSubListColumns && $onlyNumeric) {
            $label = $this->getLanguage()->translate('Group Total', 'labels', 'Report');
        }

        $row[] = [
            'value' => $label,
            'isBold' => $hasSubListColumns && !$onlyNumeric,
        ];

        foreach ($reportResult['columnList'] as $column) {
            $isNumericValue = in_array($column, $reportResult['numericColumnList']);

            if ($hasSubListColumns && !$onlyNumeric && $isNumericValue) {
                $row[] = [];

                continue;
            }

            if ($hasSubListColumns && $onlyNumeric && !$isNumericValue) {
                $row[] = [];

                continue;
            }

            if (
                $isNumericValue &&
                (
                    isset($reportResult['aggregatedColumnList']) &&
                    !in_array($column, $reportResult['aggregatedColumnList'])
                )
            ) {
                $row[] = [];

                continue;
            }

            if ($isNumericValue) {
                $value = $this->formatColumnValue(
                    $reportData->$gr->$column ?? 0,
                    $columnTypes[$column]
                );
            }
            else {
                $value = $reportData->$gr->$column ?? '';
            }

            $row[] = [
                'value' => $value,
                'attrs' => [
                    'align' => $isNumericValue ? 'right' : 'left',
                ],
            ];
        }

        return $row;
    }

    protected function formatColumnValue($value, ?string $type) : string
    {
        switch ($type) {
            case 'int':
                return $this->formatInt($value);

            case 'float':
                return $this->formatFloat($value);

            case 'currency':
            case 'currencyConverted':
                return $this->formatCurrency($value);
        }

        return (string) $value;
    }

    protected function buildEmailGrid1SubListRowList(string $gr, array $reportResult, array $columnTypes) : array
    {
        $itemList = $reportResult['subListData']->$gr;

        $rowList = [];

        foreach ($itemList as $item) {
            $rowList[] = $this->buildEmailGrid1SubListRow($item, $reportResult, $columnTypes);
        }

        return $rowList;
    }

    protected function buildEmailGrid1SubListRow(object $item, array $reportResult, array $columnTypes) : array
    {
        $row = [];

        $row[] = [
            'value' => '',
        ];

        foreach ($reportResult['columnList'] as $column) {
            if (!in_array($column, $reportResult['subListColumnList'])) {
                $row[] = [
                    'value' => '',
                ];

                continue;
            }

            $isNumericValue = in_array($column, $reportResult['numericColumnList']);

            if ($isNumericValue) {
                $value = $this->formatColumnValue(
                    $item->$column ?? 0,
                    $columnTypes[$column]
                );
            }
            else {
                $value = $item->$column ?? '';
            }

            $row[] = [
                'value' => $value,
                'attrs' => ['align' => $isNumericValue ? 'right' : 'left'],
            ];
        }

        return $row;
    }

    protected function buildEmailGrid2Data(&$data, $reportResult, $report)
    {
        $reportData = $reportResult['reportData'];

        $allowedTypeList = ['int', 'float', 'currency', 'currencyConverted'];

        $specificColumn = $data->specificColumn ?? null;

        $grids = [];

        foreach ($reportResult['summaryColumnList'] as $column) {
            $groupName1 = $reportResult['groupBy'][0];
            $groupName2 = $reportResult['groupBy'][1];

            if ($specificColumn && $specificColumn !== $column) {
                continue;
            }

            $group1NonSummaryColumnList = [];
            $group2NonSummaryColumnList = [];

            if (isset($reportResult['group1NonSummaryColumnList'])) {
                $group1NonSummaryColumnList = $reportResult['group1NonSummaryColumnList'];
            }

            if (isset($reportResult['group2NonSummaryColumnList'])) {
                $group2NonSummaryColumnList = $reportResult['group2NonSummaryColumnList'];
            }

            $columnType = 'int';

            if (strpos($column , ':')) {
                list($function, $field) = explode(':', $column);

                if ($function != 'COUNT') {
                    $columnType = $this->getMetadata()->get(
                        ['entityDefs', $report->get('entityType'), 'fields', $field, 'type']
                    );
                }
            }
            if ($columnType == "float") {
                $view = $this->getMetadata()->get(
                    ['entityDefs', $report->get('entityType'), 'fields', $field, 'view']
                );

                if ($view && strpos($view, 'currency-converted')) {
                    $columnType = 'currencyConverted';
                }

            }

            $columnTypes[$column] = (in_array($columnType, $allowedTypeList)) ? $columnType : 'int';

            $grid = [];

            $row = [];

            $row[] = '';

            foreach ($group2NonSummaryColumnList as $c) {
                $text = $reportResult['columnNameMap'][$c];
                $row[] = ['value' => $text];
            }

            foreach ($reportResult['grouping'][0] as $gr1) {
                $label = $gr1;

                if (empty($label)) {
                    $label = $this->getLanguage()->translate('-Empty-', 'labels', 'Report');
                } else if (!empty($reportResult['groupValueMap'][$groupName1][$gr1])) {
                    $label = $reportResult['groupValueMap'][$groupName1][$gr1];
                }

                if (strpos($groupName1 , ':')) {
                    list($function, $field) = explode(':', $groupName1);
                    $label = $this->handleDateGroupValue($function, $label);
                }

                $row[] = ['value' => $label];
            }

            if (isset($reportResult['group2Sums'])) {
                $row[] = ['value' => $this->getLanguage()->translate('Total', 'labels', 'Report'), 'isBold' => true];
            }

            $grid[] = $row;

            foreach ($reportResult['grouping'][1] as $gr2) {
                $row = [];
                $label = $gr2;

                if (empty($label)) {
                    $label = $this->getLanguage()->translate('-Empty-', 'labels', 'Report');
                }
                else if (isset($reportResult['groupValueMap'][$groupName2][$gr2])) {
                    $label = $reportResult['groupValueMap'][$groupName2][$gr2];
                }

                if (strpos($groupName2 , ':')) {
                    list($function, $field) = explode(':', $groupName2);

                    $label = $this->handleDateGroupValue($function, $label);
                }

                $row[] = [
                    'value' => $label,
                    'isBold' => true,
                ];

                foreach ($group2NonSummaryColumnList as $c) {
                    $value = $this->reportService->getCellDisplayValueFromResult(1, $gr2, $c, $reportResult);

                    $cData = $this->reportService->getDataFromColumnName(
                        $reportResult['entityType'], $c, $reportResult
                    );

                    $align = 'left';

                    switch ($cData->fieldType) {
                        case 'int': $value = $this->formatInt($value); $align = 'right'; break;
                        case 'float': $value = $this->formatFloat($value); $align = 'right'; break;
                        case 'currency':
                        case 'currencyConverted': $value = $this->formatCurrency($value); $align = 'right'; break;
                    }

                    $row[] = ['value' => $value, 'attrs' => ['align' => $align]];
                }

                foreach ($reportResult['grouping'][0] as $gr1) {
                    $value = 0;

                    if (isset($reportData->$gr1) && isset($reportData->$gr1->$gr2)) {
                        if (isset($reportData->$gr1->$gr2->$column)) {
                            $value = $reportData->$gr1->$gr2->$column;

                            switch ($columnType) {
                                case 'int': $value = $this->formatInt($value); break;
                                case 'float': $value = $this->formatFloat($value); break;
                                case 'currency': ;
                                case 'currencyConverted': $value = $this->formatCurrency($value); break;
                            }
                        }
                    }

                    $row[] = [
                        'value' => $value,
                        'attrs' => ['align' => 'right'],
                    ];
                }

                if (isset($reportResult['group2Sums'])) {
                    $value = $reportResult['group2Sums'][$gr2][$column];

                    switch ($columnType) {
                        case 'int': $value = $this->formatInt($value); break;
                        case 'float': $value = $this->formatFloat($value); break;
                        case 'currency': ;
                        case 'currencyConverted': $value = $this->formatCurrency($value); break;
                    }
                    $row[] = [
                        'value' => $value,
                        'attrs' => ['align' => 'right'],
                        'isBold' => true,
                    ];
                }

                $grid[] = $row;
            }

            $row = [];

            $row[] = ['value' => $this->getLanguage()->translate('Total', 'labels', 'Report'), 'isBold' => true];

            foreach ($group2NonSummaryColumnList as $c2) {
                $row[] = ['value' => ''];
            }

            foreach ($reportResult['grouping'][0] as $gr1) {
                $sum = 0;

                if (!empty($reportResult['group1Sums'][$gr1])) {
                    if (!empty($reportResult['group1Sums'][$gr1][$column])) {

                        $sum = $reportResult['group1Sums'][$gr1][$column];

                        switch ($columnType) {
                            case 'int': $sum = $this->formatInt($sum); break;
                            case 'float': $sum = $this->formatFloat($sum); break;
                            case 'currency': ;
                            case 'currencyConverted': $sum = $this->formatCurrency($sum); break;
                        }
                    }
                }
                $row[] = ['value' => $sum, 'isBold' => true, 'attrs' => ['align' => 'right']];
            }

            if (isset($reportResult['group2Sums'])) {
                $value = $reportResult['sums']->$column;

                switch ($columnType) {
                    case 'int': $value = $this->formatInt($value); break;
                    case 'float': $value = $this->formatFloat($value); break;
                    case 'currency': ;
                    case 'currencyConverted': $value = $this->formatCurrency($value); break;
                }

                $row[] = [
                    'value' => $value,
                    'attrs' => ['align' => 'right'],
                    'isBold' => true,
                ];
            }
            $grid[] = $row;

            if (count($group1NonSummaryColumnList)) {
                $row = [];

                foreach ($group2NonSummaryColumnList as $c2) {
                    $row[] = '';
                }

                foreach ($reportResult['grouping'][0] as $gr1) {
                    $row[] = '';
                }

                $grid[] = $row;
            }

            foreach ($group1NonSummaryColumnList as $c) {
                $row = [];
                $text = $reportResult['columnNameMap'][$c];
                $row[] = ['value' => $text];

                foreach ($group2NonSummaryColumnList as $c2) {
                    $row[] = '';
                }

                foreach ($reportResult['grouping'][0] as $gr1) {
                    $value = $this->reportService->getCellDisplayValueFromResult(0, $gr1, $c, $reportResult);
                    $cData = $this->reportService->getDataFromColumnName($reportResult['entityType'], $c, $reportResult);
                    $align = 'left';

                    switch ($cData->fieldType) {
                        case 'int': $value = $this->formatInt($value); $align = 'right'; break;
                        case 'float': $value = $this->formatFloat($value); $align = 'right'; break;
                        case 'currency':
                        case 'currencyConverted': $value = $this->formatCurrency($value); $align = 'right'; break;
                    }

                    $row[] = ['value' => $value, 'attrs' => ['align' => $align]];
                }
                $grid[] = $row;
            }
            $rows = $grid;

            $grids[] = [
                'rowList' => $rows,
                'header' => $reportResult['columnNameMap'][$column],
            ];
        }

        $bodyData = [
            'gridList' => $grids
        ];

        $subject = $this->htmlizeTemplate($report, 'reportSendingGrid2', 'subject');
        $body = $this->htmlizeTemplate($report, 'reportSendingGrid2', 'body', $bodyData);

        $data->emailSubject = $subject;
        $data->emailBody = $body;

        if (count($grids)) {
            $data->tableData = $grids[0]['rowList'];
        } else {
            $data->tableData = [];
        }

        return true;
    }

    public function sendEmail($data)
    {
        if (!is_object($data) || !isset($data->userId) || !isset($data->emailSubject) || !isset($data->emailBody)) {
            throw new Error(
                'Report Sending Builder[sendEmail]:  Not enough data for sending email. ' . print_r($data, true)
            );
        }

        $user = $this->getEntityManager()->getEntity('User', $data->userId);

        if (empty($user)) {
            throw new Error('Report Sending Builder[sendEmail]: No user with id ' . $data->userId);
        }

        $emailAddress = $user->get('emailAddress');

        if (empty($emailAddress)) {
            throw new Error('Report Sending Builder[sendEmail]: User has no email address');
        }

        $email = $this->getEntityManager()->getEntity('Email');

        $email->set([
            'to' => $emailAddress,
            'subject' => $data->emailSubject,
            'body' => $data->emailBody,
            'isHtml' => true,
        ]);

        $emailSender = $this->mailSender;

        if ($this->smtpParams) {
            $emailSender->useSmtp($this->smtpParams);
        }

        $message = null;

        $attachmentList = [];
        if (isset($data->attachmentId)) {
            $attachment = $this->getEntityManager()->getEntity('Attachment', $data->attachmentId);
            $attachmentList[] = $attachment;
        }

        try {
            $emailSender->send($email, [], $message, $attachmentList);
        }
        catch (\Exception $e) {
            if (isset($attachment)) {
                $this->getEntityManager()->removeEntity($attachment);
            }

            throw new Error("Report Email Sending:" . $e->getMessage());
        }

        if (isset($attachment)) {
            $this->getEntityManager()->removeEntity($attachment);
        }
    }

    protected function formatCurrency($value, $currency = null, $showCurrency = true)
    {
        if ($value === "") {
            return $value;
        }

        $userThousandSeparator = $this->getPreference('thousandSeparator');
        $userDecimalMark = $this->getPreference('decimalMark');

        $currencyFormat = (int) $this->getConfig()->get('currencyFormat');

        if (!$currency) {
            $currency = $this->getConfig()->get('defaultCurrency');
        }

        if ($currencyFormat) {
            $pad = (int) $this->getConfig()->get('currencyDecimalPlaces');
            $value = number_format($value, $pad, $userDecimalMark, $userThousandSeparator);
        }
        else {
            $value = $this->formatFloat($value);
        }

        if ($showCurrency) {
            switch ($currencyFormat) {
                case 1:
                    $value = $value . ' ' . $currency;

                    break;

                case 2:
                    $currencySign = $this->getMetadata()->get(['app', 'currency', 'symbolMap', $currency]);

                    $value = $currencySign . $value;

                    break;

                case 3:
                    $currencySign = $this->getMetadata()->get(['app', 'currency', 'symbolMap', $currency]);

                    $value = $value . ' ' .$currencySign;

                    break;
            }
        }
        return $value;
    }

    protected function formatInt($value)
    {
        if ($value === "") {
            return $value;
        }

        $userThousandSeparator = $this->getPreference('thousandSeparator');
        $userDecimalMark = $this->getPreference('decimalMark');

        return number_format($value, 0, $userDecimalMark, $userThousandSeparator);
    }

    protected function formatFloat($value)
    {
        if ($value === "") {
            return $value;
        }

        $userThousandSeparator = $this->getPreference('thousandSeparator');
        $userDecimalMark = $this->getPreference('decimalMark');

        return rtrim(rtrim(number_format($value, 8, $userDecimalMark, $userThousandSeparator), '0'), $userDecimalMark);
    }

    protected function htmlizeTemplate($entity, $templateName, $type, array $data = [])
    {
        $systemLanguage = $this->getConfig()->get('language');

        $tpl = $this->getTemplateFileManager()->getTemplate($templateName, $type, null, 'Advanced');
        $tpl = str_replace(["\n", "\r"], '', $tpl);

        return $this->getHtmlizer()->render($entity, $tpl, 'report-sending-' . $templateName . '-' . $systemLanguage, $data, true);
    }

    protected function handleDateGroupValue($function, $value)
    {
        if ($function === 'MONTH') {
            list($year, $month) = explode('-', $value);

            $monthNamesShort = $this->language->get('Global.lists.monthNamesShort');
            $monthLabel = $monthNamesShort[intval($month) - 1];

            $value = $monthLabel . ' ' . $year;
        }
        else if ($function === 'DAY') {
            $value = $this->dateTime->convertSystemDateToGlobal($value);
        }
        else if ($function === 'QUARTER') {
            list($year, $quarter) = explode('_', $value);

            $value = 'Q' . $quarter . ' ' . $year;
        }
        else if ($function === 'YEAR_FISCAL') {
            $value = $value . '-' . ($value + 1);
        }

        return $value;
    }
}
