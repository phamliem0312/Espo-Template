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

define('advanced:views/report/reports/tables/grid1', ['view', 'advanced:views/report/reports/tables/grid2'], function (Dep, Grid2) {

    return Dep.extend({

        STUB_KEY: '__STUB__',

        template: 'advanced:report/reports/tables/table',

        columnWidthPx: 130,

        setup: function () {
            this.column = this.options.column;
            this.result = this.options.result;
            this.reportHelper = this.options.reportHelper;
        },

        events: {
            'click [data-action="showSubReport"]': function (e) {
                var $target = $(e.currentTarget);

                var value = $target.attr('data-group-value');

                this.trigger(
                    'click-group',
                    value
                );
            },
        },

        formatCellValue: function (value, column, isTotal) {
            return Grid2.prototype.formatCellValue.call(this, value, column, isTotal);
        },

        formatNumber: function (value, isCurrency) {
            return Grid2.prototype.formatNumber.call(this, value, isCurrency);
        },

        calculateColumnWidth: function () {
            var columnCount = (this.result.columnList.length + 1);

            if (this.options.isLargeMode) {
                if (columnCount === 2) {
                    columnWidth = 22;
                } else if (columnCount === 3) {
                    columnWidth = 22;
                } else if (columnCount === 4) {
                    columnWidth = 20;
                } else {
                    columnWidth = 100 / columnCount;
                }
            } else {
                if (columnCount === 2) {
                    columnWidth = 35;
                } else if (columnCount === 3) {
                    columnWidth = 30;
                } else {
                    columnWidth = 100 / columnCount;
                }
            }

            return columnWidth;
        },

        afterRender: function () {
            var result = this.result;

            var groupBy = this.result.groupBy[0];

            var noGroup = false;

            if (this.result.groupBy.length === 0) {
                noGroup = true;
                groupBy = this.STUB_KEY;
            }

            var columnCount = (this.result.columns.length + 1);

            var columnWidth = this.calculateColumnWidth();

            var $table = $('<table style="table-layout: fixed;">')
                .addClass('table table-no-overflow')
                .addClass('table-bordered');

            var $tbody = $('<tbody>');

            $table.append($tbody);

            var columnWidthPx = this.columnWidthPx;

            if (columnCount > 4) {
                var tableWidthPx = columnWidthPx * columnCount;

                $table.css('min-width', tableWidthPx  + 'px');
            }

            if (!this.options.hasChart || this.options.isLargeMode) {
                $table.addClass('no-margin');
                this.$el.addClass('no-bottom-margin');
            }

            var $tr = $('<tr class="accented">');

            var hasSubListColumns = (this.result.subListColumnList || []).length;

            if (!noGroup) {
                var $th = $('<th>');

                if (!~groupBy.indexOf(':') && (this.result.isJoint || hasSubListColumns)) {
                    var columnData = this.reportHelper.getGroupFieldData(groupBy, this.result);

                    var columnString = null;

                    if (columnData.fieldType === 'link') {
                        var foreignEntityType = this.getMetadata().get(
                            ['entityDefs', columnData.entityType, 'links', columnData.field, 'entity']
                        );

                        if (foreignEntityType) {
                            columnString = this.translate(foreignEntityType, 'scopeNames');
                        }
                    }
                    if (columnString) {
                        columnString = '<strong class="text-soft">' + columnString + '</strong>';
                        $th.html(columnString);

                        if (this.options.isLargeMode && noGroup && this.result.columns.length < 3) {
                            $th.css('font-size', '125%');
                        }
                    }
                }

                $tr.append($th);
            }

            this.result.columns.forEach(function (col) {
                var columnString = this.reportHelper.formatColumn(col, this.result);

                columnString = '<strong class="text-soft">' + columnString + '</strong>';

                var $th = $('<th width="'+columnWidth+'%">').html(columnString + '&nbsp;');

                $th.css('font-weight', '600');

                if (this.options.isLargeMode && (noGroup && !hasSubListColumns) && this.result.columns.length < 3) {
                    $th.css('font-size', '125%');
                }

                $tr.append($th);
            }, this);

            $tbody.append($tr);

            this.result.grouping[0].forEach(function (gr) {
                var $tr = $('<tr>');

                if (hasSubListColumns) {
                    $tr.addClass('accented');
                }

                var groupTitle;

                if (!noGroup) {
                    groupTitle = this.reportHelper.formatGroup(groupBy, gr, this.result);

                    var html = groupTitle;

                    if (!this.result.isJoint) {
                        html = '<a href="javascript:" data-action="showSubReport"' +
                            ' data-group-value="' + Handlebars.Utils.escapeExpression(gr) + '">' +
                            html + '</a>&nbsp;';
                    }

                    var $td = $('<td>').html(html);

                    if (hasSubListColumns) {

                        $td.css('font-weight', '600');
                    }

                    $tr.append($td);

                    if (hasSubListColumns) {
                        this.result.columnList.forEach(function (col) {
                            var $td = $('<td>');

                            if (!this.options.reportHelper.isColumnNumeric(col, this.result)) {
                                var itemData = this.result.reportData[gr] || {};

                                var formattedValue = this.formatCellValue(
                                    itemData[col] || '',
                                    col
                                );

                                $td.html(formattedValue);
                                $td.attr('title', formattedValue);
                            }

                            $tr.append($td);
                        }, this);

                        $tbody.append($tr);

                        $tr = $('<tr>');

                        var $td = $('<td>');

                        $td.addClass('text-soft');

                        $td.html(this.translate('Group Total', 'labels', 'Report'));

                        $tr.append($td);
                    }
                }

                if (hasSubListColumns) {
                    var recordList = this.result.subListData[gr];

                    recordList.forEach(function (recordItem) {
                        var $tr = $('<tr>');

                        if (!noGroup) {
                            $tr.append('<td>');
                        }

                        this.result.columnList.forEach(function (col) {
                            var $td = $('<td>');

                            if (!~this.result.subListColumnList.indexOf(col)) {
                                $tr.append('<td>');

                                return;
                            }

                            if (this.options.reportHelper.isColumnNumeric(col, this.result)) {
                                $td.attr('align', 'right');
                            }

                            var value = recordItem[col];

                            var formattedValue = this.formatCellValue(value, col);

                            if (formattedValue === '') {
                                formattedValue = '&nbsp;'
                            }

                            $td.html(formattedValue);
                            $td.attr('title', formattedValue);

                            $tr.append($td);

                        }, this);

                        $tbody.append($tr);
                    }, this);
                }

                this.result.columnList.forEach(function (col) {
                    var value = null;

                    var toSkip = false;

                    if (gr in result.reportData) {
                        value = result.reportData[gr][col];
                    }

                    var $td = $('<td>');

                    if (this.options.reportHelper.isColumnNumeric(col, this.result)) {
                        $td.attr('align', 'right');
                    }

                    if (noGroup) {
                        $td.css('font-weight', '600');
                        $td.addClass('text-soft');

                        if (this.options.isLargeMode) {
                            $td.css('font-size', '175%');
                        }
                        else if (!hasSubListColumns) {
                            $td.css('font-size', '125%');
                        }
                    } else {
                        var columnString = this.reportHelper.formatColumn(col, this.result);

                        var title = this.unescapeString(groupTitle) + '\n' + this.unescapeString(columnString);

                        $td.attr('title', title);

                        if (hasSubListColumns && this.options.reportHelper.isColumnNumeric(col, this.result)) {
                            $td.css('font-weight', '600');
                            $td.addClass('text-soft');
                        }

                        if (hasSubListColumns && !this.options.reportHelper.isColumnNumeric(col, this.result)) {
                            toSkip = true;
                        }

                        if (hasSubListColumns && !this.options.reportHelper.isColumnAggregated(col, this.result)) {
                           toSkip = true;
                        }
                    }

                    var formattedValue = !toSkip ? this.formatCellValue(value, col) : '';

                    $td.html(formattedValue);

                    $tr.append($td);
                }, this);

                if (this.result.summaryColumnList.length !== 0) {
                    $tbody.append($tr);
                }
            }, this);

            if (!noGroup) {
                var $tr = $('<tr class="accented">');

                var $text = $('<span>' + this.translate('Total', 'labels', 'Report') + '</span>');

                var $td = $('<td>')
                    .html($text)
                    .addClass('text-soft')
                    .css('font-weight', '600');

                $tr.append($td);

                if (this.options.isLargeMode) {
                    $text.css('vertical-align', 'middle');
                }

                this.result.columns.forEach(function (col) {
                    value = result.sums[col];

                    var cellValue = value;

                    var columnString = this.reportHelper.formatColumn(col, this.result);

                    if (
                        this.options.reportHelper.isColumnNumeric(col, this.result) &&
                        this.options.reportHelper.isColumnAggregated(col, this.result)
                    ) {
                        value = value || 0;

                        cellValue = this.formatCellValue(value, col, true);
                    } else {
                        cellValue = '';
                    }

                    var $td = $('<td align="right">')
                        .css('font-weight', '600')
                        .html(cellValue);

                    if (this.options.isLargeMode) {
                        $td.css('font-size', '125%');
                    }

                    var title = this.unescapeString(columnString);

                    $td.attr('title', title);

                    $tr.append($td);
                }, this);

                $tbody.append($tr);
            }

            this.$el.find('.table-container').append($table);

            if (columnCount > 4) {
                this.$el.find('.table-container').css('overflow-y', 'auto');
            }
        },

        unescapeString: function (value) {
            return $('<div>').html(value).text();
        },

    });
});
