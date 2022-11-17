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

Espo.define('advanced:views/report/modals/result', ['views/modal', 'advanced:report-helper', 'views/modals/detail'], function (Dep, ReportHelper, Detail) {

    return Dep.extend({

        template: 'advanced:report/modals/result',

        backdrop: true,

        setup: function () {
            var reportHelper = this.reportHelper = new ReportHelper(
                this.getMetadata(),
                this.getLanguage(),
                this.getDateTime(),
                this.getConfig(),
                this.getPreferences()
            );

            this.createRecordView();

            if (this.model && this.model.collection && !this.navigateButtonsDisabled) {
                this.buttonList.push({
                    name: 'previous',
                    html: '<span class="fas fa-chevron-left"></span>',
                    title: this.translate('Previous Entry'),
                    pullLeft: true,
                    className: 'btn-icon',
                    disabled: true
                });
                this.buttonList.push({
                    name: 'next',
                    html: '<span class="fas fa-chevron-right"></span>',
                    title: this.translate('Next Entry'),
                    pullLeft: true,
                    className: 'btn-icon',
                    disabled: true
                });
                this.indexOfRecord = this.model.collection.indexOf(this.model);
            } else {
                this.navigateButtonsDisabled = true;
            }


            this.on('after:render', function () {
                this.$el.find('.modal-body').css({
                    'overflow-x': 'hidden',
                    'overflow-y': 'auto',
                });
            }, this);
        },

        createRecordView: function (callback) {
            this.headerHtml = this.header =
                '<a data-action="link" class="action" href="#Report/view/'+this.model.id+'">' +
                Handlebars.Utils.escapeExpression(this.model.get('name')) + '</a>';

            var viewName = this.reportHelper.getReportView(this.model);

            this.createView('record', viewName, {
                el: this.options.el + ' .report-container',
                model: this.model,
                reportHelper: this.reportHelper,
                showChartFirst: true,
                isLargeMode: true,
            }, callback, this);
        },

        afterRender: function () {
            this.$el.find('.modal-body').addClass('panel-body');

            setTimeout(function () {
                this.$el.children(0).scrollTop(0);
            }.bind(this), 50);

            if (!this.navigateButtonsDisabled) {
                this.controlNavigationButtons();
            }
        },

        actionLink: function () {
            this.trigger('navigate-to-detail', this.model);
        },

        actionPrevious: function () {
            Detail.prototype.actionPrevious.call(this);
        },

        actionNext: function () {
            Detail.prototype.actionNext.call(this);
        },

        controlNavigationButtons: function () {
            Detail.prototype.controlNavigationButtons.call(this);
        },

        switchToModelByIndex: function (indexOfRecord) {
            Detail.prototype.switchToModelByIndex.call(this, indexOfRecord);
        },

    });
});
