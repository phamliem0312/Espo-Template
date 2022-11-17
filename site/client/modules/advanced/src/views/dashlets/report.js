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

define('advanced:views/dashlets/report', ['views/dashlets/abstract/base', 'search-manager', 'advanced:report-helper'],
    function (Dep, SearchManager, ReportHelper) {

    return Dep.extend({

        name: 'Report',

        optionsView: 'advanced:views/dashlets/options/report',

        _template: '<div class="report-results-container" style="height: 100%;"></div>',

        totalFontSizeMultiplier: 1.5,

        totalLineHeightMultiplier: 1.1,

        totalMarginMultiplier: 0.4,

        totalOnlyFontSizeMultiplier: 4,

        rowActionsView: false,

        totalLabelMultiplier: 0.6,

        total2LabelMultiplier: 0.4,

        init: function () {
            this.hasShowReport = false;
            var version = this.getConfig().get('version') || '';
            var arr = version.split('.');
            if (version === 'dev' || arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) >= 506)
                this.hasShowReport = true;

            Dep.prototype.init.call(this);
        },

        setup: function () {
            this.optionsFields['report'] = {
                type: 'link',
                entity: 'Report',
                required: true,
                view: 'advanced:views/report/fields/dashlet-select'
            };
            this.optionsFields['column'] = {
                'type': 'enum',
                'options': []
            };

            this.reportHelper = new ReportHelper(this.getMetadata(), this.getLanguage(), this.getDateTime(), this.getConfig(), this.getPreferences());
        },

        afterAdding: function () {
            this.getParentView().actionOptions();
        },

        getListLayout: function () {
            var scope = this.getOption('entityType')
            var layout = [];

            var columnsData = Espo.Utils.cloneDeep(this.columnsData || {});
            (this.columns || []).forEach(function (item) {
                var o = columnsData[item] || {};
                o.name = item;

                if (~item.indexOf('.')) {
                    var a = item.split('.');
                    o.name = item.replace('.', '_');
                    o.notSortable = true;

                    var link = a[0];
                    var field = a[1];

                    var foreignScope = this.getMetadata().get('entityDefs.' + scope + '.links.' + link + '.entity');
                    var label = this.translate(link, 'links', scope) + '.' + this.translate(field, 'fields', foreignScope);

                    o.customLabel = label;

                    var type = this.getMetadata().get('entityDefs.' + foreignScope + '.fields.' + field + '.type');

                    if (type === 'enum') {
                        o.view = 'advanced:views/fields/foreign-enum';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'image') {
                        o.view = 'views/fields/image';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'file') {
                        o.view = 'views/fields/file';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'date') {
                        o.view = 'views/fields/date';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'datetime') {
                        o.view = 'views/fields/datetime';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    }
                }
                layout.push(o);
            }, this);
            return layout;
        },

        displayError: function (msg) {
            msg = msg || 'error';
            this.$el.find('.report-results-container').html(this.translate(msg, 'errorMessages', 'Report'));
        },

        displayTotal: function (dataList, isWithChart) {
            var fontSize = this.getThemeManager().getParam('fontSize') || 14;

            var cellWidth = 100 / dataList.length;

            if (!isWithChart) {
                this.$container.empty();

                var totalFontSize = fontSize * this.totalOnlyFontSizeMultiplier;

                if (dataList.length > 1) {
                    totalFontSize = Math.round(totalFontSize / (Math.log(dataList.length + 1) / Math.log(2.3)));
                    var labelFontSize = Math.round(totalFontSize * this.total2LabelMultiplier);
                }

                this.$container.css('height', '100%');

                var $div = $('<div>').css('text-align', 'center')
                                     .css('table-layout', 'fixed')
                                     .css('display', 'table')
                                     .css('width', '100%')
                                     .css('height', '100%');

                dataList.forEach(function (item) {
                    var value = item.stringValue;
                    var color = item.color;

                    var $cell = $('<div>');
                    $cell
                        .css('display', 'table-cell')
                        .css('padding-bottom', fontSize * 1.5 + 'px')
                        .css('vertical-align', 'middle');

                    if (cellWidth < 100) {
                        $cell.css('width', cellWidth.toString() + '%');
                    }

                    var $text = $('<div class="total-value-text">')
                        .css('font-size', totalFontSize + 'px')
                        .html(value.toString());

                    if (item.stringOriginalValue) {
                        $text.attr('title', item.stringOriginalValue);
                    }

                    if (color) {
                        $text.css('color', color);
                    } else {
                        if (!isWithChart) {
                            $text.addClass('text-primary');
                        }
                    }

                    if (dataList.length > 1) {
                        var $label = $('<div>')
                            .css('font-size', labelFontSize.toString() + 'px')
                            .css('max-height', '1.3em')
                            .css('overflow', 'hidden')
                            .addClass('text-muted')
                            .html(item.columnLabel);

                        $cell.append($label);
                    }

                    $cell.append($text);

                    $div.append($cell);
                }, this);

                this.$container.append($div);


                this.totalFontSize = totalFontSize;
                this.controlTotalTextOverflow();

                this.stopListening(this, 'resize', this.controlTotalTextOverflow.bind(this));
                this.listenTo(this, 'resize', this.controlTotalTextOverflow.bind(this));

            } else {
                var totalFontSize = fontSize * this.totalFontSizeMultiplier;

                if (dataList.length > 1) {
                    var labelFontSize = Math.round(totalFontSize * this.totalLabelMultiplier);
                }

                var heightCss = this.getContainerTotalHeight(dataList.length > 1) + 'px';

                var $div = $('<div>').css('text-align', 'center')
                                     .css('display', 'table')
                                     .css('width', '100%');

                this.$totalContainer.css('height', heightCss);
                this.$container.css('height', 'calc(100% - '+heightCss+')');

                dataList.forEach(function (item, i) {
                    var value = item.stringValue;

                    var $text = $('<div>').html(value.toString());

                    var title = '';
                    if (item.stringOriginalValue) {
                        title = item.stringOriginalValue;
                    }

                    if (dataList.length === 1) {
                        $text.addClass('pull-right');
                        var totalPart = title;
                        title = this.translate('Total', 'labels', 'Report');
                        if (totalPart) {
                            title = title + ': ' + totalPart;
                        }
                    }

                    $text.attr('title', title);

                    $text.addClass('text-primary');

                    $text
                        .css('font-size', Math.ceil(totalFontSize) + 'px');

                    if (dataList.length === 1) {
                        $text.css('line-height', heightCss);
                    }

                    var $cell = $('<div>')
                        .css('display', 'table-cell');

                    if (cellWidth < 100) {
                        $cell.css('width', cellWidth.toString() + '%');
                    }

                    if (dataList.length > 1) {
                        var $label = $('<div>')
                            .css('font-size', labelFontSize.toString() + 'px')
                            .css('max-height', '1.2em')
                            .css('overflow', 'hidden')
                            .addClass('text-muted')
                            .html(item.columnLabel);

                        $cell.append($label);
                    }

                    $cell.append($text);

                    $div.append($cell);
                }, this);

                this.$totalContainer.append($div);
            }
        },

        controlTotalTextOverflow: function () {
            var totalFontSizeAdj = this.totalFontSize;

            var $text = this.$el.find('.total-value-text');
            $text.css('font-size', totalFontSizeAdj + 'px');

            var controlOverflow = function () {
                var isOverflown = false;
                $text.each(function (i, el) {
                    var $el = $(el);
                    if (el.scrollWidth > el.clientWidth) {
                        isOverflown = true;
                    }
                });
                if (isOverflown) {
                    totalFontSizeAdj--;
                    $text.css('font-size', totalFontSizeAdj + 'px');
                    controlOverflow();
                }
            }.bind(this);

            controlOverflow();
        },

        getContainerTotalHeight: function (withLabels) {
            var fontSize = this.getThemeManager().getParam('fontSize') || 14;

            var totalFontSize = fontSize * this.totalFontSizeMultiplier;
            var totalPadding = fontSize * this.totalMarginMultiplier;



            var height = Math.ceil(totalFontSize * this.totalLineHeightMultiplier + totalPadding);

            if (withLabels) {
                height = height + height * this.totalLabelMultiplier;
            }

            return height;
        },

        actionRefresh: function () {
            if (this.hasView('reportChart')) {
                this.clearView('reportChart');
            }
            this.reRender();
        },

        afterRender: function () {
            this.$container = this.$el.find('.report-results-container');
            this.run();
        },

        getCollectionUrl: function () {
            return 'Report/action/runList?id=' + this.getOption('reportId');
        },

        getGridReportUrl: function () {
            return 'Report/action/run';
        },

        getGridReportRequestData: function (where) {
            return {
                id: this.getOption('reportId'),
                where: where
            }
        },

        setConteinerHeight: function () {
            var type = this.getOption('type');
            if (type === 'List') {
                this.$container.css('height', 'auto');
            } else {
                this.$container.css('height', '100%');
            }
        },

        run: function () {
            var reportId = this.getOption('reportId');
            if (!reportId) {
                this.displayError('selectReport');
                return;
            };

            var entityType = this.getOption('entityType');
            if (!entityType) {
                this.displayError();
                return;
            };

            var type = this.getOption('type');
            if (!type) {
                this.displayError();
                return;
            }

            this.setConteinerHeight();

            this.getCollectionFactory().create(entityType, function (collection) {
                var searchManager = new SearchManager(collection, 'report', null, this.getDateTime());
                var where = null;
                if (this.getOption('filtersData')) {
                    searchManager.setAdvanced(this.getOption('filtersData'));
                    where = searchManager.getWhere();
                }

                switch (type) {
                    case 'List':
                        collection.url = this.getCollectionUrl();
                        collection.where = where;

                        collection.sortBy = null;
                        collection.asc = null;

                        collection.orderBy = null;
                        collection.order = null;

                        if (this.collectionMaxSize) {
                            collection.maxSize = this.collectionMaxSize;
                        }

                        var collectionData = {
                            where: collection.getWhere(),
                            offset: collection.offset,
                            maxSize: collection.maxSize
                        };

                        this.ajaxGetRequest(collection.url, collectionData).then(function (response) {
                            var columns = this.columns = response.columns;

                            this.columnsData = response.columnsData || {};

                            collection.set(collection.parse(response));

                            if (!columns) {
                                this.displayError();

                                return;
                            }

                            if (this.getOption('displayOnlyCount')) {
                                var totalString = this.reportHelper.formatNumber(
                                    collection.total, false, this.getOption('useSiMultiplier')
                                );

                                var o = {stringValue: totalString};

                                if (this.getOption('useSiMultiplier')) {
                                    o.stringOriginalValue = this.reportHelper.formatNumber(collection.total, false);
                                }

                                this.displayTotal([o]);

                                return;
                            }

                            this.createView('list', 'views/record/list', {
                                el: this.options.el + ' .report-results-container',
                                collection: collection,
                                listLayout: this.getListLayout(),
                                checkboxes: false,
                                rowActionsView: this.rowActionsView,
                            }, function (view) {
                                view.render();
                            });

                        }.bind(this));

                        break;

                    case 'Grid':
                    case 'JointGrid':
                        Espo.Ajax.getRequest(
                            this.getGridReportUrl(), this.getGridReportRequestData(where)
                        ).then(function (result) {
                            if (!result.depth && result.depth !== 0) {
                                this.displayError();

                                return;
                            };

                            var chartType = result.chartType || 'BarHorizontal';

                            var height;

                            var fitHeight = false;

                            if (!this.isPanel) {
                                height = '100%';

                                if (result.depth === 2 || ~['Pie'].indexOf(chartType)) {
                                    fitHeight = true;
                                }
                            }

                            var column = this.getOption('column');
                            var columnList, secondColumnList;

                            var totalColumn = column;

                            if (!column) {
                                var columnGroupList = this.reportHelper.getChartColumnGroupList(result);

                                if (columnGroupList.length) {
                                    columnList = columnGroupList[0].columnList;
                                    secondColumnList = columnGroupList[0].secondColumnList;
                                    column = columnGroupList[0].column;

                                    if (!column) {
                                        if (!this.isPanel) {
                                            fitHeight = true;
                                        }
                                    }
                                }
                            }

                            var totalContainerHeight;

                            var totalColumnList = result.numericColumnList || result.columns;

                            var totalDataList = [];

                            if (this.getOption('displayType') === 'Table') {
                                this.displayTable(result, where);

                                return;
                            }

                            if (
                                totalColumnList.length &&
                                (this.getOption('displayOnlyCount') || this.getOption('displayTotal'))
                            ) {
                                totalColumnList.forEach(function (totalColumn) {
                                    var total;
                                    if (result.depth === 1 || result.depth === 0) {
                                        total = result.sums[totalColumn] || 0;
                                    }
                                    else {
                                        total = 0;

                                        for (var i in result.group1Sums) {
                                            total += result.group1Sums[i][totalColumn];
                                        }
                                    }

                                    var totalString = this.reportHelper.formatCellValue(
                                        total,
                                        totalColumn,
                                        result,
                                        this.getOption('useSiMultiplier')
                                    );

                                    var totalColor = result.chartColor;

                                    if ((result.chartColors || {})[totalColumn]) {
                                        totalColor = (result.chartColors || {})[totalColumn]
                                    }

                                    if (!result.chartType) {
                                        totalColor = null;
                                    }

                                    var stringOriginalValue = null;

                                    if (this.getOption('useSiMultiplier')) {
                                            stringOriginalValue = this.reportHelper.formatCellValue(
                                            total,
                                            totalColumn,
                                            result
                                        );
                                    }

                                    totalDataList.push({
                                        column: totalColumn,
                                        color: totalColor,
                                        stringValue: totalString,
                                        columnLabel: this.reportHelper.formatColumn(totalColumn, result),
                                        stringOriginalValue: stringOriginalValue,
                                    });

                                }, this);
                            }

                            if (totalColumnList.length && this.getOption('displayOnlyCount')) {
                                this.displayTotal(totalDataList);

                                return;
                            }

                            if (totalColumnList.length && this.getOption('displayTotal')) {
                                this.$totalContainer = $('<div class="report-total-container"></div>');

                                this.$totalContainer.insertBefore(this.$container);

                                this.displayTotal(totalDataList, true);

                                totalContainerHeight = this.getContainerTotalHeight();
                            }

                            this.$el.closest('.panel-body').css({
                                'overflow-y': 'visible',
                                'overflow-x': 'visible',
                            });

                            this.createView('reportChart',
                                'advanced:views/report/reports/charts/grid' +
                                result.depth + Espo.Utils.camelCaseToHyphen(chartType),
                            {
                                el: this.options.el + ' .report-results-container',
                                column: column,
                                columnList: columnList,
                                secondColumnList: secondColumnList,
                                result: result,
                                reportHelper: this.reportHelper,
                                height: height,
                                fitHeight: fitHeight,
                                colors: result.chartColors || {},
                                color: result.chartColor || null,
                                defaultHeight: this.defaultHeight,
                                isDashletMode: true,
                            }, function (view) {
                                view.render();

                                this.on('resize', function () {view.trigger('resize')});

                                this.listenTo(view, 'click-group', function (groupValue, groupIndex, groupValue2, column) {
                                    this.showSubReport(where, result, groupValue, groupIndex, groupValue2, column);
                                }, this);
                            });
                        }.bind(this));

                        break;
                }
            }, this);
        },

        showSubReport: function (where, result, groupValue, groupIndex, groupValue2, column) {
            var reportId = this.getOption('reportId');
            var entityType = this.getOption('entityType');

            if (result.isJoint) {
                reportId = result.columnReportIdMap[column];
                entityType = result.columnEntityTypeMap[column];
            }

            this.getCollectionFactory().create(entityType, function (collection) {
                collection.url = 'Report/action/runList?id=' + reportId + '&groupValue=' + encodeURIComponent(groupValue);

                if (groupIndex) {
                    collection.url += '&groupIndex=' + groupIndex;
                }

                if (groupValue2 !== undefined) {
                    collection.url += '&groupValue2=' + encodeURIComponent(groupValue2);
                }

                if (where) {
                    collection.where = where;
                }

                collection.maxSize = this.getConfig().get('recordsPerPage');

                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                this.createView('subReport', 'advanced:views/report/modals/sub-report', {
                    reportId: this.getOption('reportId'),
                    reportName: this.getOption('title'),
                    result: result,
                    groupValue: groupValue,
                    groupIndex: groupIndex,
                    groupValue2: groupValue2,
                    collection: collection,
                    column: column,
                }, function (view) {
                    Espo.Ui.notify(false);

                    view.render();
                });

            }, this);
        },

        setupActionList: function () {
            var action = this.hasShowReport ? 'show' : 'view';

            this.actionList.unshift({
                'name': 'viewReport',
                'html': this.translate('View Report', 'labels', 'Report'),
                'url': '#Report/'+action+'/' + this.getOption('reportId'),
                iconHtml: '<span class="fas fa-chart-bar"></span>',
            });
        },

        displayTable: function (result, where) {
            var viewName = 'advanced:views/report/reports/tables/grid1';

            if (result.depth === 2) {
                viewName = 'advanced:views/report/reports/tables/grid2';
            }

            this.createView(
                'table',
                viewName,
                {
                    el: this.options.el + ' .report-results-container',
                    result: result,
                    reportHelper: this.reportHelper,
                    column: this.getOption('column'),
                }
            )
            .then(
                function (view) {
                    view.render();

                    this.listenTo(view, 'click-group', function (groupValue, groupIndex) {
                        this.showSubReport(where, result, groupValue, groupIndex, undefined, this.getOption('column'));
                    }, this);
                }.bind(this)
            );
        },

        actionViewReport: function () {
            var action = this.hasShowReport ? 'show' : 'view';
            var reportId = this.getOption('reportId');

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            this.getModelFactory().create('Report', function (model) {
                model.id = reportId;
                model.fetch().then(function () {
                    Espo.Ui.notify(false);

                    this.createView('resultModal', 'advanced:views/report/modals/result', {
                        model: model,
                    }, function (view) {
                        view.render();

                        this.listenToOnce(view, 'navigate-to-detail', function (model) {
                            this.getRouter().navigate('#Report/view/' + model.id, {trigger: false});
                            this.getRouter().dispatch('Report', 'view', {id: model.id, model: model});
                            view.close();
                        }, this);
                    }, this);
                }.bind(this));
            }, this);
        },

    });
});
