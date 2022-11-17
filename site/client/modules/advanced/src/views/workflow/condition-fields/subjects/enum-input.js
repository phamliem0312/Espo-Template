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

Espo.define('advanced:views/workflow/condition-fields/subjects/enum-input', ['view', 'advanced:workflow-helper'], function (Dep, Helper) {

    return Dep.extend({

        template: 'advanced:workflow/condition-fields/subjects/enum-input',

        data: function () {
            return {
                readOnly: this.options.readOnly
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.field = this.options.field;
            this.entityType = this.options.entityType;
            this.conditionData = this.options.conditionData || {};

            this.wait(true);

            var helper = new Helper(this.getMetadata());

            var entityType = helper.getComplexFieldEntityType(this.field, this.entityType);
            var field = helper.getComplexFieldFieldPart(this.field);

            this.realField = field;

            this.getModelFactory().create(entityType, function (model) {
                model.set(this.realField, this.conditionData.value);

                var viewName = this.getMetadata().get('entityDefs.' + entityType + '.fields.' + field + '.view') || 'views/fields/enum';

                this.createView('field', viewName, {
                    el: this.options.el + ' .field-container',
                    mode: 'edit',
                    model: model,
                    readOnly: this.options.readOnly,
                    defs: {
                        name: this.realField
                    }
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
            return {
                value: data[this.realField]
            };
        }

    });
});

