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

define('advanced:views/report-panel/record/panels/report-panel-side', [
    'views/record/panels/side',
    'advanced:views/dashlets/report',
    'advanced:report-helper'
], function (Dep, Dashlet, ReportHelper) {

    return Dep.extend({

        _template: '<div class="report-results-container"></div>',

        isPanel: true,

        totalFontSizeMultiplier: 1.3,

        totalLineHeightMultiplier: 1.1,

        totalMarginMultiplier: 0.4,

        totalOnlyFontSizeMultiplier: 3,

        totalLabelMultiplier: 0.7,

        total2LabelMultiplier: 0.5,

        defaultHeight: 250,

        rowActionsView: 'views/record/row-actions/view-only',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.collectionMaxSize = this.getConfig().get('recordsPerPageSmall');

            this.reportHelper = new ReportHelper(this.getMetadata(), this.getLanguage(), this.getDateTime(), this.getConfig(), this.getPreferences());
        },

        getOption: function (name) {
            if (name === 'entityType') {
                return this.defs.reportEntityType;
            }
            if (name === 'type') {
                return this.defs.reportType;
            }
            if (name === 'displayOnlyCount') {
                return this.defs.displayOnlyTotal;
            }
            if (name === 'displayTotal') {
                return this.defs.displayTotal;
            }
            if (name === 'reportId') {
                return this.defs.reportPanelId;
            }
            if (name === 'column') {
                return this.defs.column;
            }
            if (name === 'title') {
                return this.defs.title;
            }
            if (name === 'useSiMultiplier') {
                return this.defs.useSiMultiplier;
            }
            if (name === 'displayType') {
                return this.defs.displayType;
            }
        },

        getListLayout: function () {
            return Dashlet.prototype.getListLayout.call(this);
        },

        getContainerTotalHeight: function (withLabels) {
            return Dashlet.prototype.getContainerTotalHeight.call(this, withLabels);
        },

        displayTable: function (result, where) {
            return Dashlet.prototype.displayTable.call(this, result, where);
        },

        displayTotal: function (dataList, isWithChart) {
            return Dashlet.prototype.displayTotal.call(this, dataList, isWithChart);
        },

        controlTotalTextOverflow: function () {
            return Dashlet.prototype.controlTotalTextOverflow.call(this);
        },

        showSubReport: function (where, result, groupValue, groupIndex, groupValue2, column) {
            this.getCollectionFactory().create(this.getOption('entityType'), function (collection) {
                collection.url = 'ReportPanel/action/runList?id=' + this.getOption('reportId') + '&groupValue=' + encodeURIComponent(groupValue);
                if (groupIndex) {
                    collection.url += '&groupIndex=' + groupIndex;
                }
                if (groupValue2 !== undefined) {
                    collection.url += '&groupValue2=' + encodeURIComponent(groupValue2);
                }
                collection.url += '&parentId=' + this.model.id;
                collection.url += '&parentType=' + this.model.entityType;

                if (result.isJoint && column) {
                    collection.url += '&subReportId=' + result.columnReportIdMap[column];
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
            return 'ReportPanel/action/runList?id=' + this.defs.reportPanelId + '&parentType=' + this.model.name + '&parentId=' + this.model.id;
        },

        getGridReportUrl: function () {
            return 'ReportPanel/action/runGrid';
        },

        getGridReportRequestData: function () {
            return {
                id: this.defs.reportPanelId,
                parentType: this.model.name,
                parentId: this.model.id
            }
        },

        run: function () {
            return Dashlet.prototype.run.call(this);
        },

        setConteinerHeight: function () {
            var type = this.getOption('type');
            if (type === 'List') {
                this.$container.css('height', 'auto');
            } else {
                this.$container.css('height', '100%');
            }
        }

    });
});
