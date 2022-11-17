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

Espo.define('advanced:views/report/modals/sub-report', ['views/modal', 'advanced:report-helper'], function (Dep, ReportHelper) {

    return Dep.extend({

        cssName: 'sub-report',

        _template: '<div class="list-container">{{{list}}}</div>',

        className: 'dialog dialog-record',

        backdrop: true,

        setup: function () {
            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Close'
                }
            ];

            var result = this.options.result;

            var reportHelper = new ReportHelper(this.getMetadata(), this.getLanguage(), this.getDateTime(), this.getConfig(), this.getPreferences());
            var groupValue = this.options.groupValue;

            var name = this.options.reportName;

            if (!name && this.model) {
                name = this.model.get('name');
            }

            var groupIndex = this.options.groupIndex || 0;

            this.headerHtml =
                Handlebars.Utils.escapeExpression(name);

            if (result.groupBy.length) {
                this.headerHtml += ': ' + reportHelper.formatGroup(result.groupBy[groupIndex], groupValue, result);
            }

            if (this.options.groupValue2 !== undefined) {
                this.headerHtml += ', ' +
                    reportHelper.formatGroup(result.groupBy[1], this.options.groupValue2, result);
            }

            if (this.options.result.isJoint && this.options.column) {
                var label = this.options.result.columnSubReportLabelMap[this.options.column];
                this.headerHtml += ', ' + Handlebars.Utils.escapeExpression(label);
            }

            this.header = this.headerHtml;

            var reportId = this.options.reportId || this.model.id;

            this.wait(true);

            this.createView('list', 'advanced:views/record/list-for-report', {
                el: this.options.el + ' .list-container',
                collection: this.collection,
                type: 'listSmall',
                reportId: reportId,
                groupValue: groupValue,
                groupIndex: groupIndex,
                groupValue2: this.options.groupValue2,
                skipBuildRows: true,
            }, function (view) {
                view.getSelectAttributeList(function (selectAttributeList) {
                    if (selectAttributeList) {
                        this.collection.data.select = selectAttributeList.join(',');
                    }
                    this.listenToOnce(view, 'after:build-rows', function () {
                        this.wait(false);
                    }, this);
                    this.collection.fetch();
                }.bind(this));
            });
        },
    });
});
