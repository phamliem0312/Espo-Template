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

Espo.define('advanced:views/report/fields/chart-color-list', ['views/fields/array', 'advanced:report-helper', 'lib!Colorpicker'], function (Dep, ReportHelper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.reportHelper = new ReportHelper(
                this.getMetadata(),
                this.getLanguage(),
                this.getDateTime(),
                this.getConfig(),
                this.getPreferences()
            );

            this.translatedOptions = Espo.Utils.clone(this.model.get('chartColors') || {});

            this.on('change', this.initColorpicker);

            this.listenTo(this.model, 'change', function (m, o) {
                if (!o.ui) return;
                if (!m.hasChanged('groupBy') && !m.hasChanged('columns') && !m.hasChanged('chartType')) return;
                this.pupulateItems();
            }, this);

            this.events['change input.role'] = function (e) {
                var $target = $(e.currentTarget);
                $target.closest('.list-group-item').find('.colored-label').css('color', $target.val());
            }.bind(this);
        },

        getItemHtml: function (value) {
            var color;
            if (value in this.translatedOptions) {
                color = this.translatedOptions[value];
            } else {
                color = '#9395FA';
            }

            var chartType = this.model.get('chartType');

            var translatedValue = value;

            var columnList = this.model.get('columns') || [];

            if (~['Line', 'BarHorizontal', 'BarVertical'].indexOf(chartType)) {
                translatedValue = this.reportHelper.translateGroupName(value, this.model.get('entityType'));
            } else {
                var fieldData = this.getGroupFieldData(chartType === 'Pie');

                var entityType = fieldData.entityType;
                var field = fieldData.field;
                var fieldType = fieldData.fieldType;
                if (fieldType === 'enum') {
                    translatedValue = this.getLanguage().translateOption(value, field, entityType);
                }
            }

            var html = '' +
            '<div class="list-group-item link-with-role form-inline" data-value="' + value + '">' +
                '<div class="pull-left" style="width: 92%; display: inline-block;">' +
                    '<input data-name="translatedValue" data-value="' + value + '" class="role form-control input-sm pull-right" value="'+color+'" style="width: 80px">' +
                    '<div class="colored-label" style="color: '+color+'">' + translatedValue + '</div>' +
                '</div>' +
                '<div style="width: 8%; display: inline-block; vertical-align: top;">' +
                    '<a href="javascript:" class="pull-right" data-value="' + value + '" data-action="removeValue"><span class="fas fa-times"></a>' +
                '</div><br style="clear: both;" />' +
            '</div>';

            return html;
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);
            data.chartColors = {};
            (data[this.name] || []).forEach(function (value) {
                data.chartColors[value] = this.$el.find('input[data-name="translatedValue"][data-value="'+value+'"]').val() || value;
            }, this);

            return data;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.isEditMode()) {
                this.initColorpicker();
            }
        },

        initColorpicker: function () {
            this.$el.find('input.role').each(function (i, el) {
                if ($(el).hasClass('colorpicker-element')) return;
                $(el).colorpicker({
                    format: 'hex'
                });
            }.bind(this));
        },

        getGroupFieldData: function (isFirstIndex) {
            var groupByList = this.model.get('groupBy') || [];
            if (!isFirstIndex && groupByList.length < 2) return;
            if (isFirstIndex && groupByList.length < 1) return;

            var index = 1;

            if (isFirstIndex) index = 0;

            var groupBy = groupByList[index];
            var field = groupBy;

            var entityType = this.model.get('entityType');

            if (~groupBy.indexOf(':')) {
                field = groupBy.split(':')[1];
            }

            if (~groupBy.indexOf('.')) {
                var arr = field.split('.');
                field = arr[1];
                var link = arr[0];
                entityType = this.getMetadata().get(['entityDefs', entityType, 'links', link, 'entity']);
                if (!entityType) return;
            }

            var fieldType = this.getMetadata().get(['entityDefs', entityType, 'fields', field, 'type']);

            return {
                entityType: entityType,
                field: field,
                fieldType: fieldType
            };
        },

        pupulateItems: function () {
            var itemList = [];
            var chartColors = {};

            var chartType = this.model.get('chartType');

            var isFilled = false;

            var groupByList = this.model.get('groupBy') || [];
            if (groupByList.length <= 1) {
                if (~['Line', 'BarHorizontal', 'BarVertical'].indexOf(chartType)) {
                    itemList = Espo.Utils.clone(this.model.get('columns') || []).filter(function (item) {
                        return this.reportHelper.isColumnNumeric(item, this.model.get('entityType'));
                    }, this);

                    if (itemList.length == 1 && chartType) {
                        itemList = [];
                    }
                    isFilled = true;
                }
            }

            if (!isFilled) {
                var fieldData = this.getGroupFieldData(chartType === 'Pie');
                if (fieldData) {
                    var entityType = fieldData.entityType;
                    var fieldType = fieldData.fieldType;
                    var field = fieldData.field;

                    if (~['enum', 'varchar'].indexOf(fieldType)) {
                        var optionList = Espo.Utils.clone(this.getMetadata().get(['entityDefs', entityType, 'fields', field, 'options']) || []);
                        if (optionList.length) {
                            if (optionList.length <= 8) {
                                itemList = optionList;
                            }
                        }
                    }
                }
            }

            if (itemList.length <= 8) {
                var colorList = this.getThemeManager().getParam('chartColorList') || [];
                if (itemList.length <= 5) {
                    colorList = this.getThemeManager().getParam('chartColorAlternativeList') || [];
                }
                itemList.forEach(function (item, i) {
                    if (i > colorList.length - 1) return;
                    chartColors[item] = colorList[i];
                }, this);
            }

            this.translatedOptions = chartColors;

            this.model.set({
                chartColorList: itemList,
                chartColors: chartColors
            }, {ui: true});
            this.reRender();
        },

    });
});
