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

define('advanced:views/workflow/condition-fields/subjects/link-parent-is-type-of',
    ['view', 'advanced:workflow-helper'], function (Dep, Helper) {

    return Dep.extend({

        _template: '<div class="field-container" style="display: inline-block">{{{field}}}</div>',

        data: function () {
            return {
                list: this.getMetadata().get('entityDefs.' + this.options.entityType + '.fields.' + this.options.field + '.options')
                    || [],
                field: this.options.field,
                value: this.options.value,
                entityType: this.options.entityType,
                readOnly: this.options.readOnly,
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

            this.typeName = this.realField + 'Type';

            var foreignEntityTypeList = this.getMetadata().get(['entityDefs', entityType, 'fields', field, 'entityList']) || [];

            this.wait(true);

            this.getModelFactory().create(entityType, function (model) {
                model.set(this.typeName, this.conditionData.valueType);

                if (!foreignEntityTypeList.length) {
                    foreignEntityTypeList = Object.keys(this.getMetadata().get(['scopes'])).filter(function (item) {
                        return !!this.getMetadata().get(['scopes', item, 'object']);
                    }, this);

                    foreignEntityTypeList.push('CampaignTrackingUrl');

                    foreignEntityTypeList = foreignEntityTypeList.sort(function (v1, v2) {
                        return this.translate(v1, 'scopeNames').localeCompare(this.translate(v2, 'scopeNames'));
                    }.bind(this));
                }

                this.createView('field', 'views/fields/enum', {
                    el: this.options.el + ' .field-container',
                    mode: 'edit',
                    model: model,
                    readOnly: this.options.readOnly,
                    readOnlyDisabled: !this.options.readOnly,
                    inlineEditDisabled: this.options.readOnly,
                    defs: {
                        name: this.typeName,
                    },
                    params: {
                        options: foreignEntityTypeList,
                        translation: 'Global.scopeNames',
                    },
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

            this.$el.find('select').addClass('input-sm');
        },

        fetch: function () {
            var view = this.getView('field');
            var data = view.fetch();

            var fieldValueMap = {};

            fieldValueMap[this.typeName] = data[this.typeName];

            return {
                valueId: null,
                valueName: null,
                valueType: data[this.typeName],
                fieldValueMap: fieldValueMap,
            };
        },

    });
});
