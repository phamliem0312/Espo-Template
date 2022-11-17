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

define('advanced:views/report/record/edit', ['views/record/edit', 'advanced:views/report/record/detail', 'advanced:report-helper'], function (Dep, Detail, ReportHelper) {

    return Dep.extend({

        saveAndContinueEditingAction: true,

        setup: function () {
            if (!this.model.get('type')) {
                throw new Error();
            }
            if (this.model.get('isInternal')) {
                this.layoutName = 'detail';
            } else {
                this.layoutName = 'detail' + this.model.get('type');
            }

            this.reportHelper = new ReportHelper(
                this.getMetadata(),
                this.getLanguage(),
                this.getDateTime(),
                this.getConfig(),
                this.getPreferences()
            );

            if (this.model.get('type') == 'List' && this.model.isNew() && !this.model.has('columns')) {
                if (this.getMetadata().get('entityDefs.' + this.model.get('entityType') + '.fields.name')) {
                    this.model.set('columns', ['name']);
                }
            }

            Dep.prototype.setup.call(this);

            this.controlChartColorsVisibility();
            this.listenTo(this.model, 'change', function () {
                if (
                    this.model.hasChanged('chartType') ||
                    this.model.hasChanged('groupBy') ||
                    this.model.hasChanged('columns')
                ) {
                    this.controlChartColorsVisibility();
                }
            }, this);

            if (this.model.get('type') == 'Grid') {
                this.controlOrderByField();
                this.listenTo(this.model, 'change:groupBy', this.controlOrderByField);

                this.controlChartColumnsFields();
                this.listenTo(this.model, 'change', function (m, o) {
                    if (
                        this.model.hasChanged('chartType') ||
                        this.model.hasChanged('groupBy') ||
                        this.model.hasChanged('columns')
                    ) {
                        this.controlChartColumnsFields(o.ui);
                    }
                }, this);
            }

            if (
                this.getMetadata().get(['scopes', 'ReportCategory', 'disabled'])
                ||
                !this.getAcl().checkScope('ReportCategory', 'read')
            ) {
                this.hideField('category');
            }

            this.setupEmailSendingFieldsVisibility();

            if (this.getAcl().get('portalPermission') === 'no') {
                this.hideField('portals');
            }

            this.controlChartTypeFieldOptions();
            this.listenTo(this.model, 'change:groupBy', this.controlChartTypeFieldOptions);
        },

        controlChartTypeFieldOptions: function () {
            var countString = (this.model.get('groupBy') || []).length.toString();
            var optionList = this.getMetadata().get(['entityDefs', 'Report', 'fields', 'chartType', 'optionListMap', countString]);
            this.setFieldOptionList('chartType', optionList);
        },

        setupEmailSendingFieldsVisibility: function () {
            Detail.prototype.setupEmailSendingFieldsVisibility.call(this);
        },

        controlEmailSendingIntervalField: function () {
            Detail.prototype.controlEmailSendingIntervalField.call(this);
        },

        controlChartColorsVisibility: function () {
            var chartType = this.model.get('chartType');

            if (!chartType || chartType === '') {
                this.hideField('chartColor');
                this.hideField('chartColorList');
                return;
            }

            if ((this.model.get('groupBy') || []).length > 1) {
                this.hideField('chartColor');
                this.showField('chartColorList');
                return;
            }

            if (chartType === 'Pie') {
                this.hideField('chartColor');
                this.showField('chartColorList');
                return;
            }

            if (~['Line', 'BarHorizontal', 'BarVertical'].indexOf(chartType)) {
                var columnList = (this.model.get('columns') || []).filter(function (item) {
                    return this.reportHelper.isColumnNumeric(item, this.model.get('entityType'));
                }, this);
                if (columnList.length > 1) {
                    this.hideField('chartColor');
                    this.showField('chartColorList');
                    return;
                }
            }

            this.showField('chartColor');
            this.hideField('chartColorList');
        },

        controlOrderByField: function () {
            var count = (this.model.get('groupBy') || []).length;

            if (count === 0) {
                this.hideField('orderBy');
            } else {
                this.showField('orderBy');
            }
        },

        controlChartColumnsFields: function (isChangedFromUi) {
            var chartType = this.model.get('chartType');

            var columnList = this.model.get('columns') || [];
            var groupBy = this.model.get('groupBy') || [];


            var toShow = true;

            this.setFieldOptionList('chartOneColumns', columnList);
            this.setFieldOptionList('chartOneY2Columns', columnList);

            if (columnList.length === 0 || columnList.length === 1 || groupBy.length > 1) {
                if (isChangedFromUi) {
                    this.model.set('chartOneColumns', []);
                    this.model.set('chartOneY2Columns', []);
                }

                toShow = false;
            } else {
                toShow = true;
            }

            if (!~['BarVertical', 'BarHorizontal', 'Line'].indexOf(chartType)) {
                toShow = false;
            }

            if (toShow) {
                this.showField('chartOneColumns');
                this.showField('chartOneY2Columns');
            } else {
                this.hideField('chartOneColumns');
                this.hideField('chartOneY2Columns');
            }

            if (isChangedFromUi && columnList.length > 1) {
                var yList = Espo.Utils.clone(this.model.get('chartOneColumns') || []);
                var y2List = Espo.Utils.clone(this.model.get('chartOneY2Columns') || []);

                yList = yList.filter(function (item) {
                    return ~columnList.indexOf(item);
                });

                y2List = y2List.filter(function (item) {
                    return ~columnList.indexOf(item);
                });

                this.model.set('chartOneColumns', yList, {ui: true});
                this.model.set('chartOneY2Columns', y2List, {ui: true});
            }
        },

    });
});
