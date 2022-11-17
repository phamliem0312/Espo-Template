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

Espo.define('advanced:views/workflow/field-definitions/date', 'advanced:views/workflow/field-definitions/base', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/field-definitions/date',

        defaultFieldData: {
            subjectType: 'today',
            shiftDays: 0,
            attributes: {},
        },

        subjectTypeList: ['today', 'field'],

        events: {
            'change [name="subjectType"]': function (e) {
                this.fieldData.subjectType = e.currentTarget.value;
                this.handleSubjectType();
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('shiftDays', 'advanced:views/workflow/action-fields/shift-days', {
                el: this.options.el + ' .shift-days',
                value: this.fieldData.shiftDays,
                unitValue: this.fieldData.shiftUnit,
                readOnly: this.readOnly
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        handleSubjectType: function () {
            if (this.fieldData.subjectType == 'field') {
                this.createView('subject', 'advanced:views/workflow/action-fields/subjects/field', {
                    el: this.options.el + ' .subject',
                    model: this.model,
                    entityType: this.entityType,
                    scope: this.scope,
                    field: this.field,
                    value: this.fieldData.field,
                    readOnly: this.readOnly
                }, function (view) {
                    view.render();
                });
            } else if (this.fieldData.subjectType == 'today') {
                this.clearView('subject');
            }
        },

        fetch: function () {
            this.fieldData.shiftDays = this.$el.find('[name="shiftDays"]').val();
            this.fieldData.shiftUnit = this.$el.find('[name="shiftUnit"]').val();

            if (this.$el.find('[name="shiftDaysOperator"]').val() == 'minus') {
                this.fieldData.shiftDays = this.fieldData.shiftDays * (-1);
            }

            if (this.fieldData.subjectType == 'field') {
                this.fieldData.field = this.$el.find('[name="subject"]').val();
            };

            return true;
        },

    });
});
