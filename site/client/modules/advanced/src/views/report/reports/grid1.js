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

Espo.define('advanced:views/report/reports/grid1', 'advanced:views/report/reports/base', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.initReport();
        },

        export: function () {
            var where = this.getRuntimeFilters();

            var o = {
                scope: this.model.get('entityType'),
                reportType: 'Grid'
            };

            var url;
            var data = {
                id: this.model.id,
                where: where
            };

            this.createView('dialogExport', 'advanced:views/report/modals/export-grid', o, function (view) {
                view.render();
                this.listenToOnce(view, 'proceed', function (dialogData) {
                    data.where = where;

                    if (dialogData.format === 'csv') {
                        url = 'Report/action/exportGridCsv';
                    } else if (dialogData.format === 'xlsx') {
                        url = 'Report/action/exportGridXlsx';
                    }

                    Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
                    this.ajaxPostRequest(url, data, {timeout: 0}).then(function (response) {
                        Espo.Ui.notify(false);
                        if ('id' in response) {
                            window.location = this.getBasePath() + '?entryPoint=download&id=' + response.id;
                        }
                    }.bind(this));

                }, this);
            }, this);
        },

        run: function () {
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            var $container = this.$el.find('.report-results-container');
            $container.empty();
            var where = this.getRuntimeFilters();

            Espo.Ajax.getRequest('Report/action/run', {
                id: this.model.id,
                where: where
            }, {timeout: 0}).then(function (result) {
                this.notify(false);

                this.result = result;

                this.storeRuntimeFilters();

                $tableContainer = $('<div>').addClass('report-table').addClass('section');

                if (!this.options.showChartFirst) {
                    $container.append($tableContainer);
                }

                if (this.chartType) {
                    var headerTag = this.options.isLargeMode ? 'h4' : 'h5';
                    var headerMarginTop = this.options.isLargeMode ? 60 : 0;

                    var columnGroupList = this.options.reportHelper.getChartColumnGroupList(result);

                    columnGroupList.forEach(function (item, i) {
                        var column = item.column;

                        var $column = $('<div>').addClass('section').addClass('column-' + i);

                        if (column) {
                            var $header = $('<'+headerTag+' style="margin-bottom: 25px">' + this.options.reportHelper.formatColumn(column, result) + '</'+headerTag+'>');
                            if (headerMarginTop && i) {
                                $header.css('marginTop', headerMarginTop);
                            }
                            $column.append($header);
                        }
                        var $chartContainer = $('<div>').addClass('section').addClass('report-chart').addClass('report-chart-' + i);

                        $column.append($chartContainer);
                        $container.append($column);
                    }, this);
                }

                if (this.options.showChartFirst) {
                    $container.append($tableContainer);
                }

                this.createView('reportTable', 'advanced:views/report/reports/tables/grid1', {
                    el: this.options.el + ' .report-results-container .report-table',
                    result: result,
                    reportHelper: this.options.reportHelper,
                    hasChart: !!this.chartType,
                    isLargeMode: this.options.isLargeMode,
                }, function (view) {
                    view.render();
                });

                if (this.chartType) {
                    columnGroupList.forEach(function (item, i) {
                        var column = item.column;
                        var columnList = item.columnList;
                        var secondColumnList = item.secondColumnList;

                        this.createView('reportChart' + i, 'advanced:views/report/reports/charts/grid1' + Espo.Utils.camelCaseToHyphen(this.chartType), {
                            el: this.options.el + ' .report-results-container .column-' + i + ' .report-chart',
                            column: column,
                            columnList: columnList,
                            secondColumnList: secondColumnList,
                            result: result,
                            reportHelper: this.options.reportHelper,
                            colors: result.chartColors || {},
                            color: result.chartColor || null
                        }, function (view) {
                            view.render();

                            this.listenTo(view, 'click-group', function (groupValue, s1, s2, column) {
                                this.showSubReport(groupValue, undefined, undefined, column);
                            }, this);
                        }, this);
                    }, this);
                }
            }.bind(this));
        },

        getPDF: function (id, where) {
            this.getRouter();
        }

    });
});
