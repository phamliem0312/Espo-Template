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

Espo.define('advanced:views/bpmn-flowchart-element/fields/timer', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        detailTemplate: 'advanced:bpmn-flowchart-element/fields/timer/detail',

        editTemplate: 'advanced:bpmn-flowchart-element/fields/timer/edit',

        data: function () {
            var data = {};

            data.timerBaseTranslatedValue = this.translatetimerBaseValue(this.model.get('timerBase'));
            data.timerShiftOperatorTranslatedValue = this.getLanguage().translateOption(this.model.get('timerShiftOperator'), 'timerShiftOperator', 'BpmnFlowchartElement');
            data.timerShiftUnitsTranslatedValue = this.getLanguage().translateOption(this.model.get('timerShiftUnits'), 'timerShiftUnits', 'BpmnFlowchartElement');

            data.timerShiftValue = this.model.get('timerShift');

            data.hasShift = this.model.get('timerShift') !== 0 && this.model.get('timerBase') !== 'formula';

            data.hasFormula = this.model.get('timerBase') === 'formula';

            if (this.mode === 'edit') {
                data.timerBaseOptionDataList = [];
                this.timerBaseOptionList.forEach(function (item) {
                    data.timerBaseOptionDataList.push({
                        value: item,
                        label: this.translatetimerBaseValue(item),
                        isSelected: item === this.model.get('timerBase')
                    });
                }, this);

                data.timerShiftOperatorOptionDataList = [];
                this.timerShiftOperatorOptionList.forEach(function (item) {
                    data.timerShiftOperatorOptionDataList.push({
                        value: item,
                        label: this.getLanguage().translateOption(item, 'timerShiftOperator', 'BpmnFlowchartElement'),
                        isSelected: item === this.model.get('timerShiftOperator')
                    });
                }, this);

                data.timerShiftUnitsOptionDataList = [];
                this.timerShiftUnitsOptionList.forEach(function (item) {
                    data.timerShiftUnitsOptionDataList.push({
                        value: item,
                        label: this.getLanguage().translateOption(item, 'timerShiftUnits', 'BpmnFlowchartElement'),
                        isSelected: item === this.model.get('timerShiftUnits')
                    });
                }, this);
            }

            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.timerBaseOptionList = ['moment', 'formula'];

            this.entityType = this.model.targetEntityType;

            this.timerShiftOperatorOptionList = ['plus', 'minus'];
            this.timerShiftUnitsOptionList = ['minutes', 'seconds', 'hours', 'days', 'months'];

            this.setupBaseOptionList();

            this.createView('timerFormula', 'views/fields/formula', {
                name: 'timerFormula',
                model: this.model,
                mode: this.mode,
                height: 50,
                el: this.getSelector() + ' .formula-container',
                inlineEditDisabled: true,
                targetEntityType: this.model.targetEntityType
            });
        },

        setupBaseOptionList: function () {
            var dateTimeFieldList = [];
            var typeList = ['date', 'datetime'];

            var fieldDefs = this.getMetadata().get(['entityDefs', this.entityType, 'fields']) || {};
            Object.keys(fieldDefs).forEach(function (field) {
                if ((~typeList.indexOf(fieldDefs[field].type))) {
                    dateTimeFieldList.push(field);
                }
            }, this);
            var linkDefs = this.getMetadata().get(['entityDefs', this.entityType, 'links']) || {};
            Object.keys(linkDefs).forEach(function (link) {
                if (linkDefs[link].type == 'belongsTo') {
                    var foreignEntityType = linkDefs[link].entity;
                    if (!foreignEntityType) {
                        return;
                    }
                    var fieldDefs = this.getMetadata().get(['entityDefs', foreignEntityType, 'fields']);
                    Object.keys(fieldDefs).forEach(function (field) {
                        if (~typeList.indexOf(fieldDefs[field].type)) {
                            dateTimeFieldList.push(link + '.' + field);
                        }
                    }, this);
                }
            }, this);

            dateTimeFieldList.forEach(function (item) {
                this.timerBaseOptionList.push('field:' + item);
            }, this);
        },

        afterRender: function () {
            this.$timerBase = this.$el.find('[data-name="timerBase"]');
            this.$timerShiftUnits = this.$el.find('[data-name="timerShiftUnits"]');
            this.$timerShiftOperator = this.$el.find('[data-name="timerShiftOperator"]');
            this.$timerShift = this.$el.find('[data-name="timerShift"]');

            this.$timerFormulaContainer = this.$el.find('.formula-container');

            this.$el.find('[data-name="timerBase"]').on('change', function () {
                this.trigger('change');
                this.controlVisibility();
            }.bind(this));
            this.controlVisibility();
        },

        controlVisibility: function () {
            if (this.model.get('timerBase') === 'formula') {
                this.$timerShiftUnits.addClass('hidden');
                this.$timerShiftOperator.addClass('hidden');
                this.$timerShift.addClass('hidden');
                this.$timerFormulaContainer.removeClass('hidden');
            } else {
                this.$timerShiftUnits.removeClass('hidden');
                this.$timerShiftOperator.removeClass('hidden');
                this.$timerShift.removeClass('hidden');
                this.$timerFormulaContainer.addClass('hidden');
            }
        },

        fetch: function () {
            var timerBase = this.$el.find('[data-name="timerBase"]').val();
            var timerShiftUnits = this.$el.find('[data-name="timerShiftUnits"]').val();
            var timerShiftOperator = this.$el.find('[data-name="timerShiftOperator"]').val();
            var timerShift = parseInt(this.$el.find('[data-name="timerShift"]').val());

            if (timerBase === 'moment') {
                timerBase = null;
            }

            var timerFormula = null;
            if (timerBase === 'formula') {
                timerFormula = this.getView('timerFormula').fetch().timerFormula;
                timerShiftOperator = null;
                timerShift = null;
                timerShiftUnits = null;
            }

            return {
                'timerBase': timerBase,
                'timerShiftUnits': timerShiftUnits,
                'timerShiftOperator': timerShiftOperator,
                'timerShift': timerShift,
                'timerFormula': timerFormula
            };
        },

        translatetimerBaseValue: function (value) {
            if (value === null || value === 'moment') {
                return this.getLanguage().translateOption('moment', 'timerBase', 'BpmnFlowchartElement');
            }
            if (value === 'formula') {
                return this.getLanguage().translateOption('formula', 'timerBase', 'BpmnFlowchartElement');
            }
            var label;

            if (value.indexOf('field:') === 0) {
                var part = value.substr(6);
                var field;

                var entityType = this.entityType;
                if (~part.indexOf('.')) {
                    var arr = part.split('.');
                    var link = arr[0];
                    field = arr[1];
                    entityType = this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'entity']);
                    label = this.translate(link, 'links', this.entityType) + '.' + this.translate(field, 'fields', entityType);
                } else {
                    field = part;
                    label = this.translate(field, 'fields', entityType);
                }

                return this.translate('Field', 'labels', 'BpmnFlowchartElement') + ': ' + label;

            }

            return value;
        }

    });

});