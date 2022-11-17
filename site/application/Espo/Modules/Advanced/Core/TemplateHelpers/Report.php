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

namespace Espo\Modules\Advanced\Core\TemplateHelpers;

use Espo\Core\Exceptions\Error;

class Report
{
    public static function reportTable()
    {
        $args = func_get_args();
        $context = $args[count($args) - 1];

        $color = $context['hash']['color'] ?? null;
        $fontSize = $context['hash']['fontSize'] ?? null;
        $border = $context['hash']['border'] ?? 1;
        $borderColor = $context['hash']['borderColor'] ?? null;
        $cellpadding = $context['hash']['cellpadding'] ?? 2;
        $column = $context['hash']['column'] ?? null;
        $flip = (boolean) ($context['hash']['flip'] ?? false);

        $serviceFactory = $context['data']['root']['__serviceFactory'];
        $entityManager = $context['data']['root']['__entityManager'];

        $html = '';

        $id = $context['_this']['id'];

        $where = $context['_this']['reportWhere'] ?? null;
        $userId = $context['_this']['userId'] ?? null;

        $report = $entityManager->getEntity('Report', $id);

        if (!$report) {
            throw new Error();
        }

        if ($report->get('type') === 'Grid' || $report->get('type') === 'JointGrid') {
            if ($report->get('groupBy') && count($report->get('groupBy')) == 2) {
                if ($column && $report->get('columns') && count($report->get('columns'))) {
                    $column = $report->get('columns')[0];
                }
            }
        }

        $tableStyle = '';

        $data = $serviceFactory->create('Report')->getReportResultsTableData($id, $where, $column, $userId);

        if ($flip) {
            $flipped = [];

            foreach ($data as $key => $row) {
                foreach ($row as $subKey => $value) {
                     $flipped[$subKey][$key] = $value;
                }
            }

            $data = $flipped;
        }

        if ($borderColor) {
            $tableStyle .= "border-color: {$borderColor};";
        }

        $html .= "<table border=\"{$border}\" cellpadding=\"{$cellpadding}\" style=\"{$tableStyle}\">";

        foreach ($data as $i => $row) {
            $html .= '<tr>';

            foreach ($row as $item) {
                $attributes = $item['attrs'] ?? [];
                $align = $attributes['align'] ?? 'left';
                $isBold = $item['isBold'] ?? false;

                $cellStyle = "";

                $width = $attributes['width'] ?? null;
                $widthPart = '';

                if ($i == 0) {
                    $widthLeft = 100;
                    $noWidthCount = count($row);

                    foreach ($row as $item2) {
                        $attributes2 = $item2['attrs'] ?? [];
                        $width2 = $attributes2['width'] ?? null;

                        if ($width2) {
                            $widthLeft -= intval(substr($width2, 0, -1));
                            $noWidthCount --;
                        }
                    }

                    if (!$width) {
                        $width = ($widthLeft / $noWidthCount) . '%';
                    }

                    $widthPart = 'width = "'.(string) $width.'"';
                }

                $value = $item['value'] ?? '';

                if ($isBold) {
                    $value = '<strong>' . $value . '</strong>';
                }

                $style = "";

                if ($fontSize) {
                    $style .= "font-size: {$fontSize}px;";
                }

                if ($color) {
                    $style .= "color: {$color};";
                }

                if ($borderColor) {
                    $style .= "border-color: {$borderColor};";
                }

                $value = "<span style=\"{$style}\">{$value}</span>";

                $html .= "<td align=\"{$align}\" {$widthPart} style=\"{$cellStyle}\">" . $value . '</td>';

            }

            $html .= '</tr>';
        }

        $html .= '</table>';

        return new LightnCandy\SafeString($html);
    }
}
