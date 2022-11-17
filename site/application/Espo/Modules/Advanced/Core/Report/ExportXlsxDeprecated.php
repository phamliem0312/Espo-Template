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

use \Espo\ORM\Entity;
use \Espo\Core\Exceptions\Error;

class ExportXlsxDeprecated extends \Espo\Core\Injectable
{
    protected $dependencyList = [
        'language',
        'metadata',
        'config',
        'dateTime',
        'number',
        'fileManager'
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
        $phpExcel = new \PHPExcel();

        if (isset($params['exportName'])) {
            $exportName = $params['exportName'];
        } else {
            $exportName = $this->getInjection('language')->translate($entityType, 'scopeNamesPlural');
        }

        foreach ($result as $sheetIndex => $dataList) {
            $currentColumn = null;
            if ($params['is2d']) {
                $currentColumn = $params['columnList'][$sheetIndex];
                $sheetName = $params['columnLabels'][$currentColumn];
            } else {
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
            } else {
                $sheet = $phpExcel->setActiveSheetIndex($sheetIndex);
                $sheet->setTitle($sheetName);
            }

            $titleStyle = array(
                'font' => array(
                   'bold' => true,
                   'size' => 12
                )
            );
            $dateStyle = array(
                'font'  => array(
                   'size' => 12
                )
            );

            $sheet->setCellValue('A1', $exportName);
            $sheet->setCellValue('B1', \PHPExcel_Shared_Date::PHPToExcel(strtotime(date('Y-m-d H:i:s'))));

            if ($currentColumn) {
                $sheet->setCellValue('A2', $params['columnLabels'][$currentColumn]);
                $sheet->getStyle('A2')->applyFromArray($titleStyle);
            }

            $sheet->getStyle('A1')->applyFromArray($titleStyle);
            $sheet->getStyle('B1')->applyFromArray($dateStyle);

            $sheet->getStyle('B1')->getNumberFormat()
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
                    if ($i * 26 + $j > $maxColumnIndex) break 2;
                }
            }
            if (count($azRange) < $maxColumnIndex) {
                foreach ($azRangeCopied as $i => $char1) {
                    foreach ($azRangeCopied as $j => $char2) {
                        foreach ($azRangeCopied as $k => $char3) {
                            $azRange[] = $char1 . $char2 . $char3;
                            if (count($azRange) > $maxColumnIndex) break 3;
                        }
                    }
                }
            }

            $rowNumber = 2;
            if ($currentColumn) {
                $rowNumber++;
            }

            $lastIndex = 0;

            $col = $azRange[$i];

            $headerStyle = array(
                'font'  => array(
                    'bold'  => true,
                    'size'  => 12
                )
            );

            $sheet->getStyle("A$rowNumber:$col$rowNumber")->applyFromArray($headerStyle);

            $firstRowNumber = $rowNumber + 1;

            $currency = $this->getConfig()->get('defaultCurrency');
            $currencySymbol = $this->getMetadata()->get(['app', 'currency', 'symbolMap', $currency], '');

            $lastCol = null;

            $borderStyle = array(
                'borders' => array(
                    'allborders' => array(
                        'style' => \PHPExcel_Style_Border::BORDER_THIN
                    )
                )
            );

            foreach ($dataList as $i => $row) {
                $rowNumber++;

                if ($i === count($dataList) - 1) {
                    if ($i < 2) break;
                    foreach ($row as $j => $item) {
                        if ($j === 0) continue;
                        $col = $azRange[$j];

                        if ($currentColumn) {
                            $column = $currentColumn;
                        } else {
                            $column = $params['columnList'][$j - 1];
                        }
                        list($function) = explode(':', $column);

                        if ($function === 'COUNT') {
                            $function = 'SUM';
                        } else if ($function === 'AVG') {
                            $function = 'AVERAGE';
                        }

                        $value = '='. $function . "(".$col.($firstRowNumber + 1).":".$col.($firstRowNumber + $i - 1).")";

                        $sheet->setCellValue($col . "" . ($rowNumber + 1), $value);

                        $type = $params['columnTypes'][$column];
                        if ($type === 'currency' || $type === 'currencyConverted') {
                            $sheet->getStyle($col . "" . ($rowNumber + 1))
                                ->getNumberFormat()
                                ->setFormatCode('[$'.$currencySymbol.'-409]#,##0.00;-[$'.$currencySymbol.'-409]#,##0.00');
                        } else if ($function === 'AVERAGE' || $type === 'float') {
                            $sheet->getStyle($col . "" . ($rowNumber + 1))
                                ->getNumberFormat()
                                ->setFormatCode('0.00');
                        } else if ($type === 'int') {
                            $sheet->getStyle($col . "" . ($rowNumber + 1))
                                ->getNumberFormat()
                                ->setFormatCode('#,##0');
                        }
                    }
                    $sheet->getStyle("A".($rowNumber + 1))->applyFromArray($headerStyle);
                    $sheet->setCellValue("A".($rowNumber + 1), $this->getInjection('language')->translate('Total', 'labels', 'Report'));
                    break;
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
                                $sheet->setCellValue("$col$rowNumber", $params['groupLabel']);
                            }
                            continue;
                        } else {
                            if ($currentColumn) {
                                $gr = $params['groupByList'][0];
                                list($f2) = explode(':', $gr);
                                if ($f2) {
                                    $item = $this->handleGroupValue($f2, $item);
                                    $formatCode = $this->getGroupCellFormatCodeForFunction($f2);

                                    $sheet->setCellValue("$col$rowNumber", $item);
                                    if ($formatCode) {
                                        $sheet->getStyle("$col$rowNumber")->getNumberFormat()->setFormatCode($formatCode);

                                        $sheet->getStyle("$col$rowNumber")->getAlignment()
                                            ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                                    }
                                }
                            }
                        }
                    }
                    $sheet->setCellValue("$col$rowNumber", $item);

                    $column = null;
                    if ($currentColumn) {
                        $column = $currentColumn;
                    } else {
                        if ($j) {
                            $column = $params['columnList'][$j - 1];
                        }
                    }

                    if ($j === 0) {
                        if ($currentColumn) {
                            $gr = $params['groupByList'][1];
                        } else {
                            $gr = $params['groupByList'][0];
                        }
                        list($f1) = explode(':', $gr);
                        if ($f1) {
                            $item = $this->handleGroupValue($f1, $item);
                            $formatCode = $this->getGroupCellFormatCodeForFunction($f1);

                            $sheet->setCellValue("$col$rowNumber", $item);
                            if ($formatCode) {
                                $sheet->getStyle("$col$rowNumber")->getNumberFormat()->setFormatCode($formatCode);

                                $sheet->getStyle("$col$rowNumber")->getAlignment()
                                    ->setHorizontal(\PHPExcel_Style_Alignment::HORIZONTAL_LEFT);
                            }
                        }
                    }
                    if ($j && $i && $column && !empty($params['columnTypes'][$column])) {
                        $type = $params['columnTypes'][$column];
                        if ($type === 'currency' || $type === 'currencyConverted') {
                            $sheet->getStyle("$col$rowNumber")
                                ->getNumberFormat()
                                ->setFormatCode('[$'.$currencySymbol.'-409]#,##0.00;-[$'.$currencySymbol.'-409]#,##0.00');
                        } else if ($type === 'float') {
                            $sheet->getStyle("$col$rowNumber")
                                ->getNumberFormat()
                                ->setFormatCode('0.00');
                        } else if ($type === 'int') {
                            $sheet->getStyle("$col$rowNumber")
                                ->getNumberFormat()
                                ->setFormatCode('#,##0');
                        }
                    }
                }
                if ($i === 0) {
                    $sheet->getStyle("A$rowNumber:$col$rowNumber")->applyFromArray($headerStyle);
                }

                if ($i && $lastCol && $currentColumn && $i < count($dataList) - 1) {

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
                    $value = '='. $function . "(B".($rowNumber).":".$lastCol.($rowNumber).")";

                    $rightTotalCol = $azRange[$j + 2];
                    $totalCell = $rightTotalCol . ($rowNumber);

                    if (!$totalFormat) {
                        $type = $params['columnTypes'][$currentColumn];
                        if ($type === 'currency' || $type === 'currencyConverted') {
                            $totalFormat = '[$'.$currencySymbol.'-409]#,##0.00;-[$'.$currencySymbol.'-409]#,##0.00';
                        } else if ($function === 'AVERAGE' || $type === 'float') {
                            $totalFormat = '0.00';
                        } else if ($type === 'int') {
                            $totalFormat = '#,##0';
                        }
                    }
                    $sheet->getColumnDimension($rightTotalCol)->setAutoSize(true);
                    $sheet->setCellValue($totalCell, $value);
                    $sheet->getStyle($totalCell)->getNumberFormat()->setFormatCode($totalFormat);

                    if ($i === 1) {
                        $sheet->getStyle($rightTotalCol . $firstRowNumber)->applyFromArray($headerStyle);
                        $sheet->setCellValue($rightTotalCol . $firstRowNumber, $this->getInjection('language')->translate('Total', 'labels', 'Report'));
                    }
                }
            }

            if ($lastCol) {
                $borderRange = "A$firstRowNumber:$lastCol" . ($rowNumber + 1);
                if ($currentColumn && isset($rightTotalCol)) {
                    $borderRange = "A$firstRowNumber:$rightTotalCol" . ($rowNumber + 1);

                    if ($totalFunction) {
                        $superTotalCell = $rightTotalCol . ($rowNumber + 1);
                        $superTotalValue = '='. $totalFunction . "(B".($rowNumber + 1).":".$lastCol.($rowNumber + 1).")";
                        $sheet->setCellValue($superTotalCell, $superTotalValue);
                        $sheet->getStyle($superTotalCell)->getNumberFormat()->setFormatCode($totalFormat);
                    }
                }
                $sheet->getStyle($borderRange)->applyFromArray($borderStyle);

                $chartStartRow = $rowNumber + 3;

                if (!empty($params['chartType'])) {
                    $chartType = $params['chartType'];
                    if (!$currentColumn) {

                        foreach ($params['columnList'] as $j => $column) {
                            $i = $j + 1;
                            $col = $azRange[$i];
                            $titleString = $dataList[0][$i];
                            $chartHeight = 18;

                            $title = new \PHPExcel_Chart_Title($titleString);

                            $legentPosition = null;
                            $excelChartType = \PHPExcel_Chart_DataSeries::TYPE_BARCHART;
                            if ($chartType === 'Line') {
                                $excelChartType = \PHPExcel_Chart_DataSeries::TYPE_LINECHART;
                            } else if ($chartType === 'Pie') {
                                $excelChartType = \PHPExcel_Chart_DataSeries::TYPE_PIECHART;
                                $legentPosition = \PHPExcel_Chart_Legend::POSITION_RIGHT;
                            }

                            $labelSeries = [
                                new \PHPExcel_Chart_DataSeriesValues(
                                    'String',
                                    "'" . $sheetName . "'" . "!" ."\$" . $col . "\$" . $firstRowNumber,
                                    null,
                                    1
                                )
                            ];

                            $dataValues = [];
                            foreach ($dataList as $k => $row) {
                                if ($k === 0) continue;
                                if ($k === count($dataList) - 1) continue;
                                $dataValues[] = $row[0];
                            }
                            list($f1) = explode(':', $params['groupByList'][0]);
                            foreach ($dataValues as $k => $item) {
                                if ($f1) {
                                    $item = $this->handleGroupValueForChart($f1, $item);
                                    $dataValues[$k] = $item;
                                }
                            }

                            $categorySeries = [
                                new \PHPExcel_Chart_DataSeriesValues(
                                    'String',
                                    "'" . $sheetName . "'" . "!\$A\$".($firstRowNumber + 1) . ':' . "\$A\$" . ($rowNumber - 1),
                                    null,
                                    count($dataValues)
                                )
                            ];

                            $valueSeries = [
                                new \PHPExcel_Chart_DataSeriesValues(
                                    'Number',
                                    "'" . $sheetName . "'" . "!\$".$col."\$".($firstRowNumber + 1).":\$".$col. "\$".($rowNumber - 1),
                                    null,
                                    count($dataValues)
                                )
                            ];

                            $legend = null;

                            if ($legentPosition) {
                                $legend = new \PHPExcel_Chart_Legend($legentPosition, null, false);
                            }

                            $dataSeries = new \PHPExcel_Chart_DataSeries(
                                $excelChartType,
                                \PHPExcel_Chart_DataSeries::GROUPING_STANDARD,
                                range(0, count($valueSeries) - 1),
                                $labelSeries,
                                $categorySeries,
                                $valueSeries
                            );

                            if ($chartType === 'BarHorizontal') {
                                $chartHeight = count($dataList) + 10;
                                $dataSeries->setPlotDirection(\PHPExcel_Chart_DataSeries::DIRECTION_BAR);
                            } else if ($chartType === 'BarVertical') {
                                $dataSeries->setPlotDirection(\PHPExcel_Chart_DataSeries::DIRECTION_COL);
                            }

                            $chartEndRow = $chartStartRow + $chartHeight;

                            $plotArea = new \PHPExcel_Chart_PlotArea(null, array($dataSeries));
                            $chart = new \PHPExcel_Chart(
                                'chart1',
                                $title,
                                $legend,
                                $plotArea,
                                true,
                                0,
                                null
                            );

                            $chart->setTopLeftPosition('A' . $chartStartRow);
                            $chart->setBottomRightPosition('E' . $chartEndRow);

                            $sheet->addChart($chart);

                            $chartStartRow = $chartEndRow + 2;
                        }
                    } else {
                        $column = $currentColumn;

                        $col = $azRange[$i];

                        $chartHeight = count($dataList) + 10;

                        $legentPosition = \PHPExcel_Chart_Legend::POSITION_BOTTOM;

                        $labelSeries = [];
                        $valueSeries = [];

                        $dataValues = [];
                        foreach ($dataList[0] as $k => $item) {
                            if ($k === 0) continue;
                            $dataValues[] = $item;
                        }
                        list($f1) = explode(':', $params['groupByList'][0]);
                        foreach ($dataValues as $k => $item) {
                            if ($f1) {
                                $item = $this->handleGroupValueForChart($f1, $item);
                                $dataValues[$k] = $item;
                            }
                        }

                        foreach ($dataList as $i => $row) {
                            if ($i == 0) continue;
                            if ($i == count($dataList) - 1) continue;

                            $labelSeries[] = new \PHPExcel_Chart_DataSeriesValues(
                                'String',
                                "'" . $sheetName . "'" . "!" ."\$A" . "\$" .($firstRowNumber + $i),
                                null,
                                1
                            );

                            $valueSeries[] = new \PHPExcel_Chart_DataSeriesValues(
                                'Number',
                                "'" . $sheetName . "'" . "!" ."\$B" . "\$" .($firstRowNumber + $i) . ":\$" . $lastCol . "\$" . ($firstRowNumber + $i),
                                null,
                                count($dataValues)
                            );
                        }

                        $categorySeries = [
                            new \PHPExcel_Chart_DataSeriesValues(
                                'String',
                                "'" . $sheetName . "'" . "!" ."\$B" . "\$" .($firstRowNumber) . ":\$" . $lastCol . "\$" . ($firstRowNumber),
                                null,
                                count($dataValues)
                            )
                        ];

                        $legend = null;
                        if ($legentPosition) {
                            $legend = new \PHPExcel_Chart_Legend($legentPosition, null, false);
                        }

                        $excelChartType = \PHPExcel_Chart_DataSeries::TYPE_BARCHART;
                        if ($chartType === 'Line') {
                            $excelChartType = \PHPExcel_Chart_DataSeries::TYPE_LINECHART;
                        } else if ($chartType === 'Pie') {
                            continue;
                        }

                        $dataSeries = new \PHPExcel_Chart_DataSeries(
                            $excelChartType,
                            \PHPExcel_Chart_DataSeries::GROUPING_STACKED,
                            range(0, count($valueSeries) - 1),
                            $labelSeries,
                            $categorySeries,
                            $valueSeries
                        );

                        if ($chartType === 'BarHorizontal') {
                            $chartHeight = count($dataList) + 10;
                            $dataSeries->setPlotDirection(\PHPExcel_Chart_DataSeries::DIRECTION_BAR);
                        } else if ($chartType === 'BarVertical') {
                            $dataSeries->setPlotDirection(\PHPExcel_Chart_DataSeries::DIRECTION_COL);
                        }

                        $chartEndRow = $chartStartRow + $chartHeight;

                        $axis =  new \PHPExcel_Chart_Axis();

                        $plotArea = new \PHPExcel_Chart_PlotArea(null, array($dataSeries));
                        $chart = new \PHPExcel_Chart(
                            'chart1',
                            null,
                            $legend,
                            $plotArea,
                            true,
                            0,
                            null,
                            null
                        );

                        $chart->setTopLeftPosition('A' . $chartStartRow);

                        $chart->setBottomRightPosition($lastCol . $chartEndRow);
                        $sheet->addChart($chart);
                    }
                }
            }

        }

        $objWriter = \PHPExcel_IOFactory::createWriter($phpExcel, 'Excel2007');

        $objWriter->setIncludeCharts(true);
        $objWriter->setPreCalculateFormulas();


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

    protected function handleGroupValueForChart($function, $value)
    {
        if ($function === 'MONTH') {
            list($year, $month) = explode('-', $value);
            $monthNamesShort = $this->getInjection('language')->get('Global.lists.monthNamesShort');
            $monthLabel = $monthNamesShort[intval($month) - 1];
            $value = $monthLabel . ' ' . $year;
        } else if ($function === 'DAY') {
            $value = $this->getInjection('dateTime')->convertSystemDateToGlobal($value);
        }

        return $value;
    }

    protected function handleGroupValue($function, $value)
    {
        if ($function === 'MONTH') {
            return \PHPExcel_Shared_Date::PHPToExcel(strtotime($value . '-01'));
        } else if ($function === 'YEAR') {
            return \PHPExcel_Shared_Date::PHPToExcel(strtotime($value . '-01-01'));
        } else if ($function === 'DAY') {
            return \PHPExcel_Shared_Date::PHPToExcel(strtotime($value));
        }
        return $value;
    }

    protected function getGroupCellFormatCodeForFunction($function)
    {
        if ($function === 'MONTH') {
            return 'MMM YYYY';
        } else if ($function === 'YEAR') {
            return 'YYYY';
        } else if ($function === 'DAY') {
            return $this->getInjection('dateTime')->getDateFormat();
        }
        return null;
    }

}