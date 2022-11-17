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

namespace Espo\Modules\Advanced\Core\Report;

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Chart\DataSeriesValues;
use PhpOffice\PhpSpreadsheet\Chart\DataSeries;
use PhpOffice\PhpSpreadsheet\Chart\Title;
use PhpOffice\PhpSpreadsheet\Chart\Legend;
use PhpOffice\PhpSpreadsheet\Chart\Axis;
use PhpOffice\PhpSpreadsheet\Chart\PlotArea;
use PhpOffice\PhpSpreadsheet\Chart\Chart;

use DateTime;
use DateTimeZone;

class ExportXlsx extends \Espo\Core\Injectable
{
    protected $dependencyList = [
        'language',
        'metadata',
        'config',
        'dateTime',
        'number',
        'fileManager',
    ];

    protected function getConfig()
    {
        return $this->getInjection('config');
    }

    protected function getNumber()
    {
        return $this->getInjection('number');
    }

    protected function getMetadata()
    {
        return $this->getInjection('metadata');
    }

    public function process($entityType, $params, $result)
    {
        $phpExcel = new Spreadsheet();

        if (isset($params['exportName'])) {
            $exportName = $params['exportName'];
        }
        else {
            $exportName = $this->getInjection('language')->translate($entityType, 'scopeNamesPlural');
        }

        $groupCount = count($params['groupByList']);

        foreach ($result as $sheetIndex => $dataList) {
            $currentColumn = null;

            if ($params['is2d']) {
                $currentColumn = $params['reportResult']['summaryColumnList'][$sheetIndex];
                $sheetName = $params['columnLabels'][$currentColumn];
            }
            else {
                $sheetName = $exportName;
            }

            $totalFunction = null;
            $totalFormat = null;

            $badCharList = ['*', ':', '/', '\\', '?', '[', ']'];

            foreach ($badCharList as $badChar) {
                $sheetName = str_replace($badCharList, ' ', $sheetName);
            }

            $sheetName = str_replace('\'', '', $sheetName);

            $sheetName = mb_substr($sheetName, 0, 30, 'utf-8');

            if ($sheetIndex > 0) {
                $sheet = $phpExcel->createSheet();
                $sheet->setTitle($sheetName);
                $sheet = $phpExcel->setActiveSheetIndex($sheetIndex);
            }
            else {
                $sheet = $phpExcel->setActiveSheetIndex($sheetIndex);
                $sheet->setTitle($sheetName);
            }

            $titleStyle = [
                'font' => [
                   'bold' => true,
                   'size' => 12
                ]
            ];

            $dateStyle = [
                'font'  => [
                   'size' => 12
                ]
            ];

            $now = new DateTime();
            $now->setTimezone(new DateTimeZone($this->getInjection('config')->get('timeZone', 'UTC')));

            $sheet->setCellValue('A1', $this->sanitizeCell($exportName));
            $sheet->setCellValue('B1', Date::PHPToExcel(strtotime($now->format('Y-m-d H:i:s'))));

            if ($currentColumn) {
                $sheet->setCellValue('A2', $params['columnLabels'][$currentColumn]);
                $sheet->getStyle('A2')->applyFromArray($titleStyle);
            }

            $sheet->getStyle('A1')->applyFromArray($titleStyle);
            $sheet->getStyle('B1')->applyFromArray($dateStyle);

            $sheet->getStyle('B1')
                ->getNumberFormat()
                ->setFormatCode($this->getInjection('dateTime')->getDateTimeFormat());

            $colCount = 1;

            foreach ($dataList as $i => $row) {
                foreach ($row as $j => $item) {
                    $colCount ++;
                }

                break;
            }

            $azRange = range('A', 'Z');
            $azRangeCopied = $azRange;

            $maxColumnIndex = count($dataList);

            if (isset($dataList[0]) && count($dataList[0]) > $maxColumnIndex) {
                $maxColumnIndex = count($dataList[0]);
            }

            $maxColumnIndex += 3;

            foreach ($azRangeCopied as $i => $char1) {
                foreach ($azRangeCopied as $j => $char2) {
                    $azRange[] = $char1 . $char2;

                    if ($i * 26 + $j > $maxColumnIndex) {
                        break 2;
                    }
                }
            }
            if (count($azRange) < $maxColumnIndex) {
                foreach ($azRangeCopied as $i => $char1) {
                    foreach ($azRangeCopied as $j => $char2) {
                        foreach ($azRangeCopied as $k => $char3) {
                            $azRange[] = $char1 . $char2 . $char3;

                            if (count($azRange) > $maxColumnIndex) {
                                break 3;
                            }
                        }
                    }
                }
            }

            $rowNumber = 2;

            if ($currentColumn) {
                $rowNumber++;
            }

            $col = $azRange[$i];

            $headerStyle = [
                'font' => [
                    'bold'  => true,
                    'size'  => 12
                ]
            ];

            $sheet->getStyle("A$rowNumber:$col$rowNumber")->applyFromArray($headerStyle);

            $headerRowNumber = $rowNumber + 1;

            $firstRowNumber = $rowNumber + 1;

            $currency = $this->getConfig()->get('defaultCurrency');
            $currencySymbol = $this->getMetadata()->get(['app', 'currency', 'symbolMap', $currency], '');

            $lastCol = null;

            $borderStyle = [
                'borders' => [
                    'allborders' => [
                        'style' => Border::BORDER_THIN
                    ]
                ]
            ];

            if ($params['is2d']) {
                $summaryRowCount = count($params['reportResult']['grouping'][1]);
                $firstSummaryColumn = $azRange[1 + count($params['reportResult']['group2NonSummaryColumnList'])];
            } else {
                $summaryRowCount = count($params['reportResult']['grouping'][0]);
                $firstSummaryColumn = 'B';
            }

            $totalRow = null;

            foreach ($dataList as $i => $row) {
                $rowNumber++;

                if ($groupCount && $i - 1 === $summaryRowCount) {
                    $totalRow = $row;
                    $rowNumber--;

                    continue;
                }

                if ($currentColumn) {
                    if (count($row) === 0) {
                        continue;
                    }

                    if ($i - 1 === $summaryRowCount) {
                        continue;
                    }
                }

                foreach ($row as $j => $item) {
                    $col = $azRange[$j];

                    if ($j === count($row) - 1) {
                        $lastCol = $col;
                    }

                    if ($i === 0) {
                        $sheet->getColumnDimension($col)->setAutoSize(true);

                        if ($j === 0) {
                            $lastCol = $azRange[count($row) - 1];
                            $lastRowNumber = $firstRowNumber + count($dataList) - 2;

                            $sheet->setAutoFilter("A$rowNumber:$lastCol$lastRowNumber");

                            if (!empty($params['groupLabel'])) {
                                $sheet->setCellValue("$col$rowNumber", $this->sanitizeCell($params['groupLabel']));
                            }

                            continue;
                        }
                        else {
                            if ($currentColumn) {
                                $gr = $params['groupByList'][0];

                                list($f2) = explode(':', $gr);

                                if ($f2) {
                                    $item = $this->handleGroupValue($f2, $item);
                                    $formatCode = $this->getGroupCellFormatCodeForFunction($f2);

                                    $sheet->setCellValue("$col$rowNumber", $this->sanitizeCell($item));

                                    if ($formatCode) {
                                        $sheet->getStyle("$col$rowNumber")
                                            ->getNumberFormat()
                                            ->setFormatCode($formatCode);

                                        $sheet->getStyle("$col$rowNumber")
                                            ->getAlignment()
                                            ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                                    }
                                }
                            }
                        }
                    }

                    $sheet->setCellValue("$col$rowNumber", $this->sanitizeCell($item));

                    $column = null;

                    if ($currentColumn) {
                        $column = $currentColumn;
                    }
                    else {
                        if ($j) {
                            $column = $params['columnList'][$j - 1];
                        }
                    }

                    if ($j === 0) {
                        if ($currentColumn) {
                            $gr = $params['groupByList'][1];
                        } else if ($groupCount) {
                            $gr = $params['groupByList'][0];
                        } else {
                            $gr = '__STUB__';
                        }

                        list($f1) = explode(':', $gr);

                        if ($f1) {
                            $item = $this->handleGroupValue($f1, $item);
                            $formatCode = $this->getGroupCellFormatCodeForFunction($f1);

                            $sheet->setCellValue("$col$rowNumber", $this->sanitizeCell($item));

                            if ($formatCode) {
                                $sheet->getStyle("$col$rowNumber")
                                    ->getNumberFormat()
                                    ->setFormatCode($formatCode);

                                $sheet->getStyle("$col$rowNumber")->getAlignment()
                                    ->setHorizontal(Alignment::HORIZONTAL_LEFT);
                            }
                        }
                    }

                    $cellColumn = $column;

                    if ($currentColumn) {
                        $cellColumn = null;

                        if ($i - 1 < $summaryRowCount) {
                            if ($j) {
                                if ($j > count($params['reportResult']['group2NonSummaryColumnList'])) {
                                    $cellColumn = $column;
                                }
                                else {
                                    if (count($params['reportResult']['group2NonSummaryColumnList'])) {
                                        $cellColumn = $params['reportResult']['group2NonSummaryColumnList'][$j - 1];
                                    }
                                }
                            }
                        }
                        else {
                            if (count($params['reportResult']['group1NonSummaryColumnList'])) {
                                $cellColumn =
                                    $params['reportResult']['group1NonSummaryColumnList'][$i - $summaryRowCount - 3];
                            }
                        }
                    }
                    if ($j && $i && $cellColumn && !empty($params['columnTypes'][$cellColumn])) {
                        $type = $params['columnTypes'][$column];

                        if ($type === 'currency' || $type === 'currencyConverted') {
                            $sheet->getStyle("$col$rowNumber")
                                ->getNumberFormat()
                                ->setFormatCode(
                                    $this->getCurrencyFormatCode($currencySymbol)
                                );
                        }
                        else if ($type === 'float') {
                            $sheet->getStyle("$col$rowNumber")
                                ->getNumberFormat()
                                ->setFormatCode('0.00');
                        }
                        else if ($type === 'int') {
                            $sheet->getStyle("$col$rowNumber")
                                ->getNumberFormat()
                                ->setFormatCode('#,##0');
                        }
                    }
                }
                if ($i === 0) {
                    $sheet->getStyle("A$rowNumber:$col$rowNumber")->applyFromArray($headerStyle);
                }

                if ($i && $lastCol && $currentColumn && $i < count($dataList)) {
                    $skipRowTotal = false;

                    if ($i - 1 > $summaryRowCount) {
                        $skipRowTotal = true;
                    }

                    $rightTotalCol = $azRange[$j + 2];

                    if ($i === 1) {
                        $sheet->getStyle($rightTotalCol . $headerRowNumber)->applyFromArray($headerStyle);
                        $sheet->setCellValue(
                            $rightTotalCol . $headerRowNumber,
                            $this->getInjection('language')->translate('Total', 'labels', 'Report')
                        );
                    }

                    if (!$skipRowTotal) {
                        if ($totalFunction) {
                            $function = $totalFunction;
                        } else {
                            list($function) = explode(':', $currentColumn);

                            if ($function === 'COUNT') {
                                $function = 'SUM';
                            } else if ($function === 'AVG') {
                                $function = 'AVERAGE';
                            }

                            $totalFunction = $function;
                        }

                        $value = '='. $function . "(". $firstSummaryColumn .$rowNumber.":".$lastCol.($rowNumber).")";

                        $totalCell = $rightTotalCol . ($rowNumber);

                        if (!$totalFormat) {
                            $type = $params['columnTypes'][$currentColumn];

                            if ($type === 'currency' || $type === 'currencyConverted') {
                                $totalFormat = $this->getCurrencyFormatCode($currencySymbol);
                            } else if ($function === 'AVERAGE' || $type === 'float') {
                                $totalFormat = '0.00';
                            } else if ($type === 'int') {
                                $totalFormat = '#,##0';
                            }
                        }

                        $sheet->getColumnDimension($rightTotalCol)->setAutoSize(true);
                        $sheet->setCellValue($totalCell, $value);
                        $sheet->getStyle($totalCell)->getNumberFormat()->setFormatCode($totalFormat);
                    }
                }
            }

            if ($groupCount && $lastCol && $totalRow) {
                $rowNumber++;
                $row = $totalRow;

                foreach ($row as $j => $item) {
                    if ($j === 0) {
                        continue;
                    }

                    if ($row[$j] !== 0 && empty($row[$j])) {
                        continue;
                    }

                    $col = $azRange[$j];

                    if ($currentColumn) {
                        $column = $currentColumn;
                    }
                    else {
                        $column = $params['columnList'][$j - 1];
                    }

                    if (!in_array($column, $params['reportResult']['numericColumnList'])) {
                        continue;
                    }

                    if (strpos($column, ':')) {
                        list($function) = explode(':', $column);

                        if ($function === 'COUNT') {
                            $function = 'SUM';
                        } else if ($function === 'AVG') {
                            $function = 'AVERAGE';
                        }
                    }
                    else {
                        $function = 'SUM';
                    }

                    $value = '='. $function . "(".$col.($firstRowNumber + 1) . ":" .
                        $col . ($firstRowNumber + $summaryRowCount).")";

                    $sheet->setCellValue($col . "" . ($rowNumber + 1), $value);

                    $type = $params['columnTypes'][$column];

                    if ($type === 'currency' || $type === 'currencyConverted') {
                        $sheet->getStyle($col . "" . ($rowNumber + 1))
                            ->getNumberFormat()
                            ->setFormatCode(
                                $this->getCurrencyFormatCode($currencySymbol)
                            );
                    }
                    else if ($function === 'AVERAGE' || $type === 'float') {
                        $sheet->getStyle($col . "" . ($rowNumber + 1))
                            ->getNumberFormat()
                            ->setFormatCode('0.00');
                    }
                    else if ($type === 'int') {
                        $sheet->getStyle($col . "" . ($rowNumber + 1))
                            ->getNumberFormat()
                            ->setFormatCode('#,##0');
                    }
                }

                $sheet->getStyle("A".($rowNumber + 1))->applyFromArray($headerStyle);

                $sheet->setCellValue(
                    "A".($rowNumber + 1),
                    $this->getInjection('language')->translate('Total', 'labels', 'Report')
                );
            }

            if ($lastCol) {
                $borderRange = "A$firstRowNumber:$lastCol" . ($rowNumber + 1);

                if ($currentColumn && isset($rightTotalCol)) {
                    $borderRange = "A$firstRowNumber:$rightTotalCol" . ($rowNumber + 1);

                    if ($totalFunction) {
                        $superTotalCell = $rightTotalCol . ($rowNumber + 1);

                        $superTotalValue = '='.
                            $totalFunction . "(". $firstSummaryColumn . ($rowNumber + 1) .
                            ":" . $lastCol . ($rowNumber + 1) . ")";

                        $sheet->setCellValue($superTotalCell, $superTotalValue);
                        $sheet->getStyle($superTotalCell)->getNumberFormat()->setFormatCode($totalFormat);
                    }
                }

                $sheet->getStyle($borderRange)->applyFromArray($borderStyle);

                $chartStartRow = $rowNumber + 3;

                if (!$groupCount) {
                    $dataLastRowNumber = $rowNumber;
                }
                else {
                    $dataLastRowNumber = $firstRowNumber + $summaryRowCount;
                }

                if (!empty($params['chartType'])) {
                    if (!$currentColumn) {
                        $columnGroupList = $this->getColumnGroupList($params);

                        foreach ($columnGroupList as $columnIndexList) {
                            $this->drawChart1(
                                $params,
                                $dataList,
                                $sheet,
                                $sheetName,
                                $azRange,
                                $firstRowNumber,
                                $dataLastRowNumber,
                                $lastCol,
                                $chartStartRow,
                                $columnIndexList
                            );
                        }
                    }
                    else {
                        $column = $currentColumn;

                        $this->drawChart2(
                            $params,
                            $dataList,
                            $sheet,
                            $sheetName,
                            $azRange,
                            $firstRowNumber,
                            $dataLastRowNumber,
                            $lastCol,
                            $chartStartRow,
                            $i,
                            $firstSummaryColumnIndex = 1 + count($params['reportResult']['group2NonSummaryColumnList']),
                            $firstSummaryColumn
                        );
                    }
                }
            }
        }

        $objWriter = IOFactory::createWriter($phpExcel, 'Xlsx');

        $objWriter->setIncludeCharts(true);
        $objWriter->setPreCalculateFormulas(true);

        if (!$this->getInjection('fileManager')->isDir('data/cache/')) {
            $this->getInjection('fileManager')->mkdir('data/cache/');
        }

        $tempFileName = 'data/cache/' . 'export_' . substr(md5(rand()), 0, 7);

        $objWriter->save($tempFileName);

        $fp = fopen($tempFileName, 'r');

        $xlsx = stream_get_contents($fp);

        $this->getInjection('fileManager')->unlink($tempFileName);

        return $xlsx;
    }

    protected function getColumnGroupList($params)
    {
        $list = [];

        $countGroup = [];
        $sumCurrencyGroup = [];
        $currencyGroup = [];

        if ($params['chartType'] == 'Pie') {
            foreach ($params['columnList'] as $j => $column) {
                $list[] = [$j];
            }

            return $list;
        }

        foreach ($params['columnList'] as $j => $column) {
            if (!in_array($column, $params['reportResult']['numericColumnList'])) {
                continue;
            }

            if (strpos($column, 'COUNT:') === 0) {
                $countGroup[] = $j;

                continue;
            }

            if (
                (
                    strpos($column, 'SUM:') === 0
                    ||
                    strpos($column, ':') === false && strpos($column, '.') !== false
                )
                &&
                $params['columnTypes'][$column] == 'currencyConverted'
            ) {
                $sumCurrencyGroup[] = $j;

                continue;
            }

            if ($params['columnTypes'][$column] == 'currencyConverted') {
                $currencyGroup[] = $j;

                continue;
            }

            $list[] = [$j];
        }

        if (count($currencyGroup)) {
            array_unshift($list, $currencyGroup);
        }

        if (count($countGroup)) {
            array_unshift($list, $countGroup);
        }

        if (count($sumCurrencyGroup)) {
            array_unshift($list, $sumCurrencyGroup);
        }

        return $list;
    }

    protected function drawChart1(
        $params,
        $dataList,
        $sheet,
        $sheetName,
        $azRange,
        $firstRowNumber,
        $dataLastRowNumber,
        $lastCol,
        &$chartStartRow,
        $columnIndexList
    )
    {
        $chartType = $params['chartType'];
        $groupCount = count($params['groupByList']);

        if ($groupCount === 0 && count($columnIndexList) === 1) {
            return;
        }

        $labelSeries = [];
        $valueSeries = [];

        $dataValues = [];
        foreach ($dataList as $k => $row) {
            if ($k === 0) {
                continue;
            }

            if ($k === count($dataList) - 1) {
                continue;
            }

            $dataValues[] = $row[0];
        }

        if ($groupCount) {
            list($f1) = explode(':', $params['groupByList'][0]);

            foreach ($dataValues as $k => $item) {
                if ($f1) {
                    $item = $this->handleGroupValueForChart($f1, $item);
                    $dataValues[$k] = $item;
                }
            }
        }

        foreach ($columnIndexList as $j) {
            $i = $j + 1;

            $col = $azRange[$i];
            $titleString = $dataList[0][$i];

            $labelSeries[] = new DataSeriesValues(
                'String',
                "'" . $sheetName . "'" . "!" ."\$" . $col . "\$" . $firstRowNumber,
                null,
                1
            );

            $valueSeries[] = new DataSeriesValues(
                'Number',
                "'" . $sheetName . "'" . "!\$".$col."\$".($firstRowNumber + 1).":\$".$col. "\$".$dataLastRowNumber,
                null,
                count($dataValues)
            );
        }

        $chartHeight = 18;

        $title = new Title($titleString);

        $legentPosition = null;
        $excelChartType = DataSeries::TYPE_BARCHART;

        if ($chartType === 'Line') {
            $excelChartType = DataSeries::TYPE_LINECHART;
        }
        else if ($chartType === 'Pie') {
            $excelChartType = DataSeries::TYPE_PIECHART;
            $legentPosition = Legend::POSITION_RIGHT;
        }

        if ($chartType != 'Pie' && count($columnIndexList) > 1) {
            $legentPosition = Legend::POSITION_BOTTOM;
            $title = null;
        }

        $categorySeries = [
            new DataSeriesValues(
                'String',
                "'" . $sheetName . "'" . "!\$A\$".($firstRowNumber + 1) . ':' . "\$A\$" . $dataLastRowNumber,
                null,
                count($dataValues)
            )
        ];

        $legend = null;

        if ($legentPosition) {
            $legend = new Legend($legentPosition, null, false);
        }

        $dataSeries = new DataSeries(
            $excelChartType,
            DataSeries::GROUPING_STANDARD,
            range(0, count($valueSeries) - 1),
            $labelSeries,
            $categorySeries,
            $valueSeries
        );

        if ($chartType === 'BarHorizontal') {
            $chartHeight = count($dataList) + 10;
            $dataSeries->setPlotDirection(DataSeries::DIRECTION_BAR);
        }
        else if ($chartType === 'BarVertical') {
            $dataSeries->setPlotDirection(DataSeries::DIRECTION_COL);
        }

        $chartEndRow = $chartStartRow + $chartHeight;

        $yAxis = null;
        if ($chartType === 'BarHorizontal') {
            $yAxis = new Axis();
            $yAxis->setAxisOrientation(
                Axis::ORIENTATION_REVERSED
            );
        }

        $plotArea = new PlotArea(null, [$dataSeries]);
        $chart = new Chart(
            'chart1',
            $title,
            $legend,
            $plotArea,
            true,
            'gap',
            null,
            null,
            null,
            $yAxis
        );

        $chart->setTopLeftPosition('A' . $chartStartRow);
        $chart->setBottomRightPosition('E' . $chartEndRow);

        $sheet->addChart($chart);

        $chartStartRow = $chartEndRow + 2;
    }

    protected function drawChart2(
        $params,
        $dataList,
        $sheet,
        $sheetName,
        $azRange,
        $firstRowNumber,
        $dataLastRowNumber,
        $lastCol,
        &$chartStartRow,
        $index,
        $firstSummaryColumnIndex,
        $firstSummaryColumn
    )
    {
        $chartType = $params['chartType'];

        $chartHeight = count($dataList) + 10;

        $legentPosition = Legend::POSITION_BOTTOM;

        $labelSeries = [];
        $valueSeries = [];

        $dataValues = [];
        foreach ($dataList[0] as $k => $item) {
            if ($k === 0) {
                continue;
            }

            if ($k < $firstSummaryColumnIndex) {
                continue;
            }

            $dataValues[] = $item;
        }

        list($f1) = explode(':', $params['groupByList'][0]);

        foreach ($dataValues as $k => $item) {
            if ($f1) {
                $item = $this->handleGroupValueForChart($f1, $item);
                $dataValues[$k] = $item;
            }
        }

        if (!count($dataValues)) {
            return;
        }

        for ($i = $firstRowNumber + 1; $i <= $dataLastRowNumber; $i++) {
            $labelSeries[] = new DataSeriesValues(
                'String',
                "'" . $sheetName . "'" . "!" ."\$A" . "\$" .($i),
                null,
                1
            );

            $valueSeries[] = new DataSeriesValues(
                'Number',
                "'" . $sheetName . "'" . "!" ."\$" . $firstSummaryColumn . "\$" .($i) . ":\$" . $lastCol . "\$" . ($i),
                null,
                count($dataValues)
            );
        }

        $categorySeries = [
            new DataSeriesValues(
                'String',
                "'" . $sheetName . "'" . "!" ."\$" . $firstSummaryColumn . "\$" .($firstRowNumber) .
                ":\$" . $lastCol . "\$" . ($firstRowNumber),
                null,
                count($dataValues)
            )
        ];

        $legend = null;

        if ($legentPosition) {
            $legend = new Legend($legentPosition, null, false);
        }

        $excelChartType = DataSeries::TYPE_BARCHART;

        if ($chartType === 'Line') {
            $excelChartType = DataSeries::TYPE_LINECHART;
        }
        else if ($chartType === 'Pie') {
            return;
        }

        $groupingType = DataSeries::GROUPING_STACKED;

        if ($chartType === 'BarGroupedVertical' || $chartType === 'BarGroupedHorizontal') {
            $groupingType = DataSeries::GROUPING_CLUSTERED;
        }

        $dataSeries = new DataSeries(
            $excelChartType,
            $groupingType,
            range(0, count($valueSeries) - 1),
            $labelSeries,
            $categorySeries,
            $valueSeries
        );

        if ($chartType === 'BarHorizontal' || $chartType === 'BarGroupedHorizontal') {
            $chartHeight = count($dataList) + 10;

            $dataSeries->setPlotDirection(DataSeries::DIRECTION_BAR);
        }
        else if ($chartType === 'BarVertical' || $chartType === 'BarGroupedVertical') {
            $dataSeries->setPlotDirection(DataSeries::DIRECTION_COL);
        }

        $chartEndRow = $chartStartRow + $chartHeight;

        $yAxis = null;

        if ($chartType === 'BarHorizontal' || $chartType === 'BarGroupedHorizontal') {
            $yAxis = new Axis();

            $yAxis->setAxisOrientation(
                Axis::ORIENTATION_REVERSED
            );
        }

        $plotArea = new PlotArea(null, [$dataSeries]);
        $chart = new Chart(
            'chart1',
            null,
            $legend,
            $plotArea,
            true,
            'gap',
            null,
            null,
            null,
            $yAxis
        );

        $chart->setTopLeftPosition('A' . $chartStartRow);

        $chart->setBottomRightPosition($lastCol . $chartEndRow);
        $sheet->addChart($chart);
    }

    protected function handleGroupValueForChart($function, $value)
    {
        if ($function === 'MONTH') {
            list($year, $month) = explode('-', $value);
            $monthNamesShort = $this->getInjection('language')->get('Global.lists.monthNamesShort');
            $monthLabel = $monthNamesShort[intval($month) - 1];
            $value = $monthLabel . ' ' . $year;
        }
        else if ($function === 'DAY') {
            $value = $this->getInjection('dateTime')->convertSystemDateToGlobal($value);
        }

        return $value;
    }

    protected function handleGroupValue($function, $value)
    {
        if ($function === 'MONTH') {
            return Date::PHPToExcel(strtotime($value . '-01'));
        }
        else if ($function === 'YEAR') {
            return Date::PHPToExcel(strtotime($value . '-01-01'));
        }
        else if ($function === 'DAY') {
            return Date::PHPToExcel(strtotime($value));
        }

        return $value;
    }

    protected function getGroupCellFormatCodeForFunction($function)
    {
        if ($function === 'MONTH') {
            return 'MMM YYYY';
        }
        else if ($function === 'YEAR') {
            return 'YYYY';
        }
        else if ($function === 'DAY') {
            return $this->getInjection('dateTime')->getDateFormat();
        }

        return null;
    }

    protected function getCurrencyFormatCode($currencySymbol)
    {
        $currencyFormat = $this->getConfig()->get('currencyFormat') ?? 2;

        if ($currencyFormat == 3) {
            return '#,##0.00_-"' . $currencySymbol . '"';
        }

        return '[$'.$currencySymbol.'-409]#,##0.00;-[$'.$currencySymbol.'-409]#,##0.00';
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    private function sanitizeCell($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        if ($value === '') {
            return $value;
        }

        if (in_array($value[0], ['+', '-', '@', '='])) {
            return "'" . $value;
        }

        return $value;
    }
}
