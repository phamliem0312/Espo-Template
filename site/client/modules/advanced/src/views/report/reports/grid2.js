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

Espo.define('advanced:views/report/reports/grid2', 'advanced:views/report/reports/base', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.initReport();
        },

        export: function () {
            var where = this.getRuntimeFilters();

            var columnsTranslation = {};
            var entityType = this.model.get('entityType');

            var columnList = (this.model.get('columns') || []).filter(function (item) {
                return this.options.reportHelper.isColumnSummary(item);
            }, this);

            columnList.forEach(function (item) {
                columnsTranslation[item] = this.options.reportHelper.translateGroupName(item, entityType);
            }, this);

            var o = {
                scope: entityType,
                reportType: 'Grid',
                columnList: columnList,
                columnsTranslation: columnsTranslation
            };

            var url;
            var data = {
                id: this.model.id,
                where: where
            };

            this.createView('dialogExport', 'advanced:views/report/modals/export-grid', o, function (view) {
                view.render();
                this.listenToOnce(view, 'proceed', function (dialogData) {
                    data.column = dialogData.column;

                    if (dialogData.format === 'csv') {
                        url = 'Report/action/exportGridCsv';
                        data.column = dialogData.column;
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
            this.notify('Please wait...');

            $container = this.$el.find('.report-results-container');
            $container.empty();

            var where = this.getRuntimeFilters();

            Espo.Ajax.getRequest('Report/action/run', {
                id: this.model.id,
                where: where,
            }, {timeout: 0}).then(function (result) {
                this.notify(false);
                this.result = result;

                this.storeRuntimeFilters();

                var headerTag = this.options.isLargeMode ? 'h4' : 'h5';
                var headerMarginTop = this.options.isLargeMode ? 60 : 50;

                var summaryColumnList = result.summaryColumnList || result.columnList;

                summaryColumnList.forEach(function (column, i) {
                    var $column = $('<div>').addClass('column-' + i).addClass('section').addClass('sections-container');
                    var $header = $('<'+headerTag+' style="margin-bottom: 25px">' + this.options.reportHelper.formatColumn(column, result) + '</'+headerTag+'>');

                    if (!this.options.isLargeMode) {
                        $header.addClass('text-soft');
                    }
                    if (headerMarginTop && i) {
                        $header.css('marginTop', headerMarginTop);
                    }

                    var $tableContainer = $('<div>').addClass('report-table clearfix').addClass('report-table-' + i).addClass('section');
                    var $chartContainer = $('<div>').addClass('report-chart').addClass('report-chart-' + i).addClass('section');

                    if (this.chartType) {
                        $tableContainer.addClass('margin-bottom');
                    }

                    $column.append($header);
                    if (!this.options.showChartFirst) {
                        $column.append($tableContainer);
                    }
                    $column.append($chartContainer);
                    if (this.options.showChartFirst) {
                        $column.append($tableContainer);
                    }
                    $container.append($column);
                }, this);

                summaryColumnList.forEach(function (column, i) {
                    this.createView('reportTable' + i, 'advanced:views/report/reports/tables/grid2', {
                        el: this.options.el + ' .report-results-container .column-' + i + ' .report-table',
                        column: column,
                        result: result,
                        reportHelper: this.options.reportHelper,
                        hasChart: !!this.chartType,
                        isLargeMode: this.options.isLargeMode,
                        showChartFirst: this.options.showChartFirst,
                    }, function (view) {
                        view.render();
                    });

                    if (this.chartType) {
                        this.createView('reportChart' + i, 'advanced:views/report/reports/charts/grid2' +  Espo.Utils.camelCaseToHyphen(this.chartType), {
                            el: this.options.el + ' .report-results-container .column-' + i + ' .report-chart',
                            column: column,
                            result: result,
                            reportHelper: this.options.reportHelper,
                            colors: result.chartColors || {},
                            color: result.chartColor || null,
                        }, function (view) {
                            view.render();
                            this.listenTo(view, 'click-group', function (groupValue, groupIndex, groupValue2) {
                                this.showSubReport(groupValue, groupIndex, groupValue2);
                            }, this);
                        });
                    }
                }, this);

            }.bind(this));
        },
    });
});
