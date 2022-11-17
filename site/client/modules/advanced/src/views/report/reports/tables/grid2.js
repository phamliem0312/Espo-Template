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

define('advanced:views/report/reports/tables/grid2', 'view', function (Dep) {

    return Dep.extend({

        template: 'advanced:report/reports/tables/table',

        columnWidthPx: 110,

        columnWidth2Px: 140,

        firstColumnWidthPx: 170,

        nonSummaryColumnWidthPx: 150,

        setup: function () {
            this.column = this.options.column;
            this.result = this.options.result;
            this.reportHelper = this.options.reportHelper;

            var formatData = this.reportHelper.getFormatData(this.getConfig(), this.getPreferences());
            this.decimalMark = formatData.decimalMark;
            this.thousandSeparator = formatData.thousandSeparator;
            this.currencyDecimalPlaces = formatData.currencyDecimalPlaces;
            this.currencySymbol = formatData.currencySymbol;
            this.currency = formatData.currency;
        },

        events: {
            'click [data-action="showSubReport"]': function (e) {
                var $target = $(e.currentTarget);

                var value = $target.attr('data-group-value');
                var index = parseInt($target.attr('data-group-index') || 0);

                this.trigger(
                    'click-group',
                    value,
                    index
                );
            },
        },

        formatGroup: function (i, value) {
            var gr = this.result.groupBy[i];

            return this.reportHelper.formatGroup(gr, value, this.result);
        },

        formatCellValue: function (value, column, isTotal) {
            var entityType = this.result.entityType;

            if (!this.options.reportHelper.isColumnNumeric(column, this.result)) {
                if (this.result.cellValueMaps && this.result.cellValueMaps[column]) {
                    value = this.result.cellValueMaps[column][value] || value || '';
                }

                return value;
            }
            else {
                value = value || 0;
            }

            var isCurrency = false;

            var arr = column.split(':');
            if (arr.length === 1) {
                arr = ['', column];
            }

            if (arr.length > 1) {
                var data = this.reportHelper.getGroupFieldData(column, this.result);

                var entityType = data.entityType;
                var field = data.field;
                var fieldType = data.fieldType;

                isCurrency = !!~['currency', 'currencyConverted'].indexOf(fieldType);
                if (!isCurrency && entityType === 'Opportunity' && field === 'amountWeightedConverted') {
                    isCurrency = true;
                }
            }

            if (!isTotal && value == 0) {
                if (~column.indexOf('COUNT:')) {
                    return '<span class="text-muted">' + 0 + '</span>';
                }

                return '<span class="text-muted">' + this.formatNumber(0) + '</span>';
            }

            if (~column.indexOf('COUNT:')) {
                return this.formatNumber(value);
            }

            return this.formatNumber(value, isCurrency);
        },

        formatNumber: function (value, isCurrency) {
            return this.reportHelper.formatNumber(value, isCurrency);
        },

        formatNumber1: function (value, isCurrency) {
            if (!this.decimalMark) {
                if (this.getPreferences().has('decimalMark')) {
                    this.decimalMark = this.getPreferences().get('decimalMark');
                } else {
                    if (this.getConfig().has('decimalMark')) {
                        this.decimalMark = this.getConfig().get('decimalMark');
                    }
                }
                if (this.getPreferences().has('thousandSeparator')) {
                    this.thousandSeparator = this.getPreferences().get('thousandSeparator');
                } else {
                    if (this.getConfig().has('thousandSeparator')) {
                        this.thousandSeparator = this.getConfig().get('thousandSeparator');
                    }
                }
            }

            if (value !== null) {
                var maxDecimalPlaces = 2;
                var currencyDecimalPlaces = this.getConfig().get('currencyDecimalPlaces');

                if (isCurrency) {
                    if (currencyDecimalPlaces === 0) {
                        value = Math.round(value);
                    } else if (currencyDecimalPlaces) {
                        value = Math.round(value * Math.pow(10, currencyDecimalPlaces)) / (Math.pow(10, currencyDecimalPlaces));
                    } else {
                        value = Math.round(value * Math.pow(10, maxDecimalPlaces)) / (Math.pow(10, maxDecimalPlaces));
                    }
                } else {
                    var maxDecimalPlaces = 4;
                    value = Math.round(value * Math.pow(10, maxDecimalPlaces)) / (Math.pow(10, maxDecimalPlaces));
                }

                var parts = value.toString().split(".");
                parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, this.thousandSeparator);

                if (isCurrency) {
                    if (currencyDecimalPlaces === 0) {
                        delete parts[1];
                    } else if (currencyDecimalPlaces) {
                        var decimalPartLength = 0;
                        if (parts.length > 1) {
                            decimalPartLength = parts[1].length;
                        } else {
                            parts[1] = '';
                        }

                        if (currencyDecimalPlaces && decimalPartLength < currencyDecimalPlaces) {
                            var limit = currencyDecimalPlaces - decimalPartLength;
                            for (var i = 0; i < limit; i++) {
                                parts[1] += '0';
                            }
                        }
                    }
                }

                return parts.join(this.decimalMark);
            }
            return '';
        },

        afterRender: function () {
            var result = this.result;

            var group1NonSummaryColumnList = [];
            var group2NonSummaryColumnList = [];

            if (this.result.nonSummaryColumnList) {
                this.result.nonSummaryColumnList.forEach(function (column) {
                    var group = this.result.nonSummaryColumnGroupMap[column];
                    if (group == this.result.groupByList[0]) {
                        group1NonSummaryColumnList.push(column);
                    }
                    if (group == this.result.groupByList[1]) {
                        group2NonSummaryColumnList.push(column);
                    }
                }, this);
            }

            var columnCount = (this.result.grouping[0].length + 1) + group2NonSummaryColumnList.length;

            var summaryColumnCount = this.result.grouping[0].length;
            if (this.result.group2Sums) {
                summaryColumnCount++;
            }
            var nonSummaryColumnCount = group2NonSummaryColumnList.length;

            var columnWidthPx = this.columnWidthPx;

            var columnData = this.reportHelper.getGroupFieldData(this.column, result);
            if (columnData && columnData.fieldType !== 'int' && columnData.function !== 'COUNT') {
                columnWidthPx = this.columnWidth2Px;
            }
            if (group1NonSummaryColumnList.length) {
                columnWidthPx = this.nonSummaryColumnWidthPx;
            }

            var ratio1 = this.firstColumnWidthPx / columnWidthPx;
            var ratio2 = this.nonSummaryColumnWidthPx / columnWidthPx;

            var summaryColumnWidth = 100 / (ratio1 + ratio2 * nonSummaryColumnCount + summaryColumnCount);

            var nonSummaryColumnWidth = summaryColumnWidth * ratio2;

            var firstColumnWidth = 100 - nonSummaryColumnWidth * nonSummaryColumnCount -
                summaryColumnWidth * summaryColumnCount;

            var firstColumnWidthPx = summaryColumnWidth * ratio1;

            var $table = $('<table style="table-layout: fixed;">')
                .addClass('table table-no-overflow')
                .addClass('table-bordered');

            var $tbody = $('<tbody>');

            $table.append($tbody);

            var summaryColumnWidthPx = columnWidthPx;

            if (columnCount > 7) {
                var tableWidthPx =
                    summaryColumnWidthPx * summaryColumnCount +
                    this.nonSummaryColumnWidthPx * nonSummaryColumnCount + this.firstColumnWidthPx;

                $table.css('min-width', tableWidthPx  + 'px');
            }

            if (!this.options.hasChart || this.options.isLargeMode) {
                $table.addClass('no-margin');
            }

            if (!this.options.hasChart || this.options.showChartFirst) {
                this.$el.addClass('no-bottom-margin');
            }

            var $tr = $('<tr class="accented">');

            var $th = $('<th width="'+ firstColumnWidth.toString() +'%">');

            $th.css({'word-wrap': 'break-word'});

            $th.html('&nbsp;');
            $tr.append($th);

            group2NonSummaryColumnList.forEach(function (column) {
                var columnTitle = this.reportHelper.formatColumn(column, this.result);
                var $th = $('<th width="'+nonSummaryColumnWidth+'%">').html(columnTitle)

                $th.addClass('text-soft');
                $th.css({'word-wrap': 'break-word'});
                $th.css({'font-weight': '600'});

                $tr.append($th);
            }, this);

            this.result.grouping[0].forEach(function (gr1) {
                var $a = $(
                    '<a href="javascript:" data-action="showSubReport" data-group-value="'+
                    Handlebars.Utils.escapeExpression(gr1)+'">' + this.formatGroup(0, gr1) + '</a>'
                );

                var $th = $('<th width="'+summaryColumnWidth+'%">').html($a)

                $th.css({'word-wrap': 'break-word'});

                $tr.append($th);
            }, this);

            if (this.result.group2Sums) {
                var totalText = this.translate('Total', 'labels', 'Report');
                var $th = $('<th class="text-soft">').css({'font-weight': '600'}).html(totalText);

                $tr.append($th);
            }

            $tbody.append($tr);

            var reportData = this.options.reportData;

            if (group1NonSummaryColumnList.length) {
                group1NonSummaryColumnList.forEach(function (column) {
                    var $tr = $('<tr class="accented">');
                    var columnTitle = this.reportHelper.formatColumn(column, this.result);

                    var $td = $('<td>').html(columnTitle);

                    $td.addClass('text-soft');
                    $td.css({'font-weight': '600'});
                    $tr.append($td);
                    $td.addClass('accented');

                    group2NonSummaryColumnList.forEach(function (column) {
                        $tr.append('<td class="accented">');
                    }, this);

                    this.result.grouping[0].forEach(function (gr1) {
                        var group1Title = this.formatGroup(0, gr1);
                        var value = null;
                        var dataMap = result.nonSummaryData[result.groupByList[0]];

                        if ((gr1 in dataMap) && (column in dataMap[gr1])) {
                            value = dataMap[gr1][column];
                        }

                        var align = this.reportHelper.isColumnNumeric(column, result) ? 'right' : '';
                        var $td = $('<td align="'+align+'">').html(this.formatCellValue(value, column));
                        var title = this.unescapeString(group1Title) + '\n' + this.unescapeString(columnTitle);

                        $td.attr('title', title);
                        $td.css({'word-wrap': 'break-word'});

                        $tr.append($td);
                    }, this);

                    if (this.result.group2Sums) {
                        $tr.append('<td class="accented">');
                    }

                    $tbody.append($tr);
                }, this);
            }

            this.result.grouping[1].forEach(function (gr2) {
                var $tr = $('<tr>');
                var group2Title = this.formatGroup(1, gr2);

                var $a =  $(
                    '<a href="javascript:" data-action="showSubReport" data-group-index="1" data-group-value="'+
                    Handlebars.Utils.escapeExpression(gr2)+'">' + group2Title + '</a>');

                var $td = $('<td>').html($a);

                $td.addClass('accented');
                $td.css({'word-wrap': 'break-word'});
                $tr.append($td);

                group2NonSummaryColumnList.forEach(function (column) {
                    var value = null;
                    var columnTitle = this.reportHelper.formatColumn(column, this.result);
                    var dataMap = result.nonSummaryData[result.groupByList[1]];

                    if ((gr2 in dataMap) && (column in dataMap[gr2])) {
                        value = dataMap[gr2][column];
                    }

                    var align = this.reportHelper.isColumnNumeric(column, result) ? 'right' : '';

                    var $td = $('<td class="accented" align="'+align+'" width="'+nonSummaryColumnWidth+'%">')
                        .html(this.formatCellValue(value, column));

                    var title = this.unescapeString(group2Title) + '\n' + this.unescapeString(columnTitle);

                    $td.attr('title', title);
                    $td.css({'word-wrap': 'break-word'});

                    $tr.append($td);
                }, this);

                this.result.grouping[0].forEach(function (gr1) {
                    var group1Title = this.formatGroup(0, gr1);
                    var value = 0;

                    if ((gr1 in result.reportData) && (gr2 in result.reportData[gr1])) {
                        value = result.reportData[gr1][gr2][this.column];
                    }

                    var title = this.unescapeString(group1Title) + '\n' + this.unescapeString(group2Title);

                    var $td = $('<td align="right" width="'+summaryColumnWidthPx+'%">')
                        .html(this.formatCellValue(value, this.column));

                    $td.attr('title', title);
                    $td.css({'word-wrap': 'break-word'});

                    $tr.append($td);
                }, this);

                if (this.result.group2Sums) {
                    var value = 0;

                    if (gr2 in result.group2Sums) {
                        value = result.group2Sums[gr2][this.column];
                    }

                    var $td = $('<td class="accented" align="right">').css('font-weight', '600');
                    var text = this.formatCellValue(value, this.column, true);

                    $td.html(text);

                    var title = this.unescapeString(group2Title);

                    $td.attr('title', title);
                    $td.addClass('text-soft');
                    $tr.append($td);
                }

                $tbody.append($tr);
            }, this);

            var $tr = $('<tr class="accented">');

            var $totalText = $(
                '<strong class="text-soft">' + this.translate('Total', 'labels', 'Report') + '</strong>'
            );

            $tr.append($('<td>').html($totalText));

            group2NonSummaryColumnList.forEach(function () {
                $tr.append('<td>');
            });

            this.result.grouping[0].forEach(function (gr1) {
                var group1Title = this.formatGroup(0, gr1);
                var value = 0;

                if (gr1 in result.group1Sums) {
                    value = result.group1Sums[gr1][this.column];
                }

                var title = this.unescapeString(group1Title);
                var $text = $('<strong>' + this.formatCellValue(value, this.column, true) + '</strong>');
                var $td = $('<td align="right">').html($text);

                $td.css({'word-wrap': 'break-word'});
                $td.addClass('text-soft');
                $td.attr('title', title);
                $tr.append($td);
            }, this);

            if (this.result.group2Sums) {
                var $td = $('<td class="accented" align="right">').css('font-weight', '600');
                var value = 0;

                if (this.column in result.sums) {
                    value = result.sums[this.column];
                }

                var text = this.formatCellValue(value, this.column, true);
                $td.html(text);
                $tr.append($td);
            }

            $tbody.append($tr);

            this.$tableContainer = this.$el.find('.table-container');

            this.$tableContainer.append($table);

            if (columnCount > 7) {
                this.$tableContainer.css('overflow-y', 'auto');
            }
        },

        unescapeString: function (value) {
            return $('<div>').html(value).text();
        },

    });
});
