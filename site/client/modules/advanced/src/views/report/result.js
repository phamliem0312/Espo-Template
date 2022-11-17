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

define('advanced:views/report/result', ['views/main', 'advanced:report-helper'], function (Dep, ReportHelper) {

    return Dep.extend({

        template: 'advanced:report/result',

        name: 'result',

        setup: function () {
            var reportHelper = new ReportHelper(
                this.getMetadata(),
                this.getLanguage(),
                this.getDateTime(),
                this.getConfig(),
                this.getPreferences()
            );

            var viewName = reportHelper.getReportView(this.model);

            this.setupHeader();

            this.createView('report', viewName, {
                el: this.options.el + ' .report-container',
                model: this.model,
                reportHelper: reportHelper,
                showChartFirst: true,
                isLargeMode: true,
            });
        },

        setupHeader: function () {
            this.createView('header', 'views/header', {
                model: this.model,
                el: '#main > .header',
                scope: this.scope
            });
        },

        getHeader: function () {
            var name = Handlebars.Utils.escapeExpression(this.model.get('name'));

            if (name === '') {
                name = this.model.id;
            }

            var rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;

            var headerIconHtml = this.getHeaderIconHtml();

            return this.buildHeaderHtml([
                headerIconHtml + '<a href="' + rootUrl + '" class="action" data-action="navigateToRoot">' + this.getLanguage().translate(this.scope, 'scopeNamesPlural') + '</a>',
               '<a href="#' + this.scope + '/view/' + this.model.id + '" class="action" data-action="backToView">' + name + '</a>'
            ]);
        },

        actionBackToView: function () {
            var options = {
                id: this.model.id,
                model: this.model,
            };

            options.rootUrl = this.options.rootUrl || this.options.params.rootUrl;

            this.getRouter().navigate('#' + this.scope + '/view/' + this.model.id, {trigger: false});
            this.getRouter().dispatch(this.scope, 'view', options);
        },

    });
});
