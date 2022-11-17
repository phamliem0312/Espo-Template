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

Espo.define('advanced:views/report/record/panels/report', ['view', 'advanced:report-helper'], function (Dep, ReportHelper) {

    return Dep.extend({

        template: 'advanced:report/record/panels/report',

        setup: function () {
            var reportHelper = new ReportHelper(
                this.getMetadata(),
                this.getLanguage(),
                this.getDateTime(),
                this.getConfig(),
                this.getPreferences()
            );

            var viewName = reportHelper.getReportView(this.model);

            this.createView('report', viewName, {
                el: this.options.el + ' .report-container',
                model: this.model,
                reportHelper: reportHelper
            });
        },

        actionRefresh: function () {
            var reportView = this.getView('report');
            reportView.run();
        }

    });
});