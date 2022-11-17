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

define('advanced:views/workflow/condition-fields/subjects/link-parent', ['view', 'advanced:workflow-helper'], function (Dep, Helper) {

    return Dep.extend({

        _template: '<div class="field-container" style="display: inline-block">{{{field}}}</div>',

        data: function () {
            return {
                list: this.getMetadata().get('entityDefs.' + this.options.entityType + '.fields.' + this.options.field + '.options') || [],
                field: this.options.field,
                value: this.options.value,
                entityType: this.options.entityType,
                readOnly: this.options.readOnly
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.field = this.options.field;
            this.entityType = this.options.entityType;
            this.conditionData = this.options.conditionData || {};

            var helper = new Helper(this.getMetadata());

            var entityType = helper.getComplexFieldEntityType(this.field, this.entityType);
            var field = helper.getComplexFieldFieldPart(this.field);

            this.realField = field;

            this.idName = this.realField + 'Id';
            this.nameName = this.realField + 'Name';
            this.typeName = this.realField + 'Type';

            this.wait(true);

            this.getModelFactory().create(entityType, function (model) {
                model.set(this.idName, this.conditionData.value);
                model.set(this.nameName, this.conditionData.valueName);
                model.set(this.typeName, this.conditionData.valueType);

                var foreignScopeList = this.getMetadata().get(['entityDefs', entityType, 'fields', field, 'entityList']) || [];

                if (!foreignScopeList.length) {
                    foreignScopeList = Object.keys(this.getMetadata().get(['scopes'])).filter(function (item) {
                        return !!this.getMetadata().get(['scopes', item, 'object']);
                    }, this);
                    foreignScopeList.push('CampaignTrackingUrl');
                    foreignScopeList = foreignScopeList.sort(function (v1, v2) {
                        return this.translate(v1, 'scopeNames').localeCompare(this.translate(v2, 'scopeNames'));
                    }.bind(this));
                }

                this.createView('field', 'views/fields/link-parent', {
                    el: this.options.el + ' .field-container',
                    mode: 'edit',
                    model: model,
                    readOnly: this.options.readOnly,
                    readOnlyDisabled: !this.options.readOnly,
                    inlineEditDisabled: this.options.readOnly,
                    defs: {
                        name: this.realField
                    },
                    foreignScopeList: foreignScopeList,
                }, function (view) {
                    if (!this.options.readOnly && view.readOnly) {
                        view.readOnlyLocked = false
                        view.readOnly = false;
                        view.setMode('edit');
                        view.reRender();
                    }
                    this.wait(false);
                });
            }, this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.$el.find('input').addClass('input-sm');
            this.$el.find('select').addClass('input-sm');
            this.$el.find('.btn').addClass('btn-sm');
        },

        fetch: function () {
            var view = this.getView('field');
            var data = view.fetch();
            var fieldValueMap = {};
            fieldValueMap[this.idName] = data[this.idName];
            fieldValueMap[this.typeName] = data[this.typeName];

            return {
                value: data[this.idName],
                valueName: data[this.nameName],
                valueType: data[this.typeName],
                fieldValueMap: fieldValueMap
            };
        }

    });
});
