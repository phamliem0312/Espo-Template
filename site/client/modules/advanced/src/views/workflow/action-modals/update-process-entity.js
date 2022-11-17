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

Espo.define('advanced:views/workflow/action-modals/update-process-entity', [
        'advanced:views/workflow/action-modals/update-entity',
        'advanced:views/workflow/action-modals/create-entity',
        'model'
    ], function (Dep, CreateEntity, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/update-entity',

        ignoreFieldList: [
            'endedAt', 'status', 'targetType', 'target', 'flowchartData', 'flowchart', 'flowchartElementsDataHash', 'flowchartVisualization',
            'createdEntitiesData', 'variables', 'modifiedAt', 'endedAt', 'createdBy', 'modifiedBy', 'createdAt'
        ],

        setupScope: function (callback) {
            this.scope = 'BpmnProcess';
            this.getModelFactory().create(this.scope, function (model) {
                this.model = model;

                (this.actionData.fieldList || []).forEach(function (field) {
                    var attributes = (this.actionData.fields[field] || {}).attributes || {};
                    model.set(attributes, {silent: true});
                }, this);

                callback();
            }, this);
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.hasFormulaAvailable = !!this.getMetadata().get('app.formula.functionList');

            this.wait(true);
            this.setupScope(function () {
                this.createView('addField', 'advanced:views/workflow/action-fields/add-field', {
                    el: this.options.el + ' .add-field-container',
                    scope: this.scope,
                    fieldList: this.getFieldList(),
                });
                this.wait(false);

            }.bind(this));
        },

        getFieldList: function () {
            var fieldDefs = this.getMetadata().get('entityDefs.' + this.scope + '.fields') || {};
            var fieldList = Object.keys(fieldDefs).filter(function(field) {
                var type = fieldDefs[field].type;
                if (fieldDefs[field].disabled) return false;
                if (~this.ignoreFieldList.indexOf(field)) return;
                return true;
            }.bind(this)).sort(function (v1, v2) {
                 return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));
            return fieldList;
        },
    });
});
