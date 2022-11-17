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

Espo.define('advanced:views/workflow/action-modals/update-entity', [
        'advanced:views/workflow/action-modals/base',
        'advanced:views/workflow/action-modals/create-entity',
        'model'
    ], function (Dep, CreateEntity, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/update-entity',

        data: function () {
            return _.extend({
                scope: this.scope,
            }, Dep.prototype.data.call(this));
        },

        events: {
            'click [data-action="addField"]': function (e) {
                var $target = $(e.currentTarget);
                var field = $target.data('field');

                if (!~this.actionData.fieldList.indexOf(field)) {

                    this.actionData.fieldList.push(field);
                    this.actionData.fields[field] = {};

                    this.addField(field, false, true);
                }
            },
            'click [data-action="removeField"]': function (e) {
                var $target = $(e.currentTarget);
                var field = $target.data('field');
                this.clearView('field-' + field);

                delete this.actionData.fields[field];

                var index = this.actionData.fieldList.indexOf(field);
                this.actionData.fieldList.splice(index, 1);

                $target.parent().remove();
            }

        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.$fieldDefinitions = this.$el.find('.field-definitions');

            this.$formulaCell = this.$el.find('.cell[data-name="formula"]');

            (this.actionData.fieldList || []).forEach(function (field) {
                this.addField(field, this.actionData.fields[field]);
            }, this);

            if (this.hasFormulaAvailable) {
                this.$formulaCell.removeClass('hidden');
            } else {
                this.$formulaCell.addClass('hidden');
            }

            this.setupFormulaView();
        },

        setupFormulaView: function () {
            var model = new Model;
            if (this.hasFormulaAvailable) {
                model.set('formula', this.actionData.formula || null);

                this.createView('formula', 'views/fields/formula', {
                    name: 'formula',
                    model: model,
                    mode: this.readOnly ? 'detail' : 'edit',
                    height: 100,
                    el: this.getSelector() + ' .field[data-name="formula"]',
                    inlineEditDisabled: true,
                    targetEntityType: this.scope
                }, function (view) {
                    view.render();
                }, this);
            }
        },

        setupScope: function (callback) {
            var scope = this.entityType;
            this.scope = scope;

            this.getModelFactory().create(scope, function (model) {
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
                    scope: this.entityType,
                    fieldList: this.getFieldList(),
                });
                this.wait(false);

            }.bind(this));
        },


        addField: function (field, fieldData, isNew) {
            var fieldType = this.getMetadata().get('entityDefs.' + this.scope + '.fields.' + field + '.type') || 'base';
            var type = this.getMetadata().get('entityDefs.Workflow.fieldDefinitions.' + fieldType) || 'base';

            fieldData = fieldData || false;

            var fieldNameHtml = '<label>' + this.translate(field, 'fields', this.scope) + '</label>';
            var removeLinkHtml = '<a href="javascript:" class="pull-right" data-action="removeField" data-field="' + field + '"><span class="fas fa-times"></span></a>';
            var html = '<div class="margin clearfix field-row" data-field="' + field + '" style="margin-left: 20px;">' + removeLinkHtml + fieldNameHtml + '<div class="field-container field" data-field="' + field + '"></div></div>';

            this.$fieldDefinitions.append($(html));

            this.createView('field-' + field, 'advanced:views/workflow/field-definitions/' + Espo.Utils.camelCaseToHyphen(type), {
                el: this.options.el + ' .field-container[data-field="' + field + '"]',
                fieldData: fieldData,
                model: this.model,
                field: field,
                entityType: this.entityType,
                scope: this.scope,
                type: type,
                fieldType: fieldType,
                isNew: isNew
            }, function (view) {
                view.render();
            });
        },

        getFieldList: function () {
            return CreateEntity.prototype.getFieldList.call(this);
        },

        fetch: function () {
            var isValid = true;
            (this.actionData.fieldList || []).forEach(function (field) {
                isValid = this.getView('field-' + field).fetch();
                this.actionData.fields[field] = this.getView('field-' + field).fieldData;
            }, this);

            if (this.hasFormulaAvailable) {
                var formulaView = this.getView('formula');
                if (formulaView) {
                    this.actionData.formula = formulaView.fetch().formula;
                }
            }

            return isValid;
        },

    });
});
