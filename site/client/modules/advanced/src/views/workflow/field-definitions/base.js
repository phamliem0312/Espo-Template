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

Espo.define('advanced:views/workflow/field-definitions/base', 'view', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/field-definitions/base',

        defaultFieldData: {
            subjectType: 'value',
            attributes: {},
        },

        subjectTypeList: ['value', 'field'],

        events: {
            'change [name="subjectType"]': function (e) {
                this.fieldData.subjectType = e.currentTarget.value;
                this.handleSubjectType();
            }
        },

        data: function () {
            return {
                subjectTypeList: this.subjectTypeList,
                subjectTypeValue: this.fieldData.subjectType,
                readOnly: this.readOnly
            };
        },

        setup: function () {
            this.scope = this.options.scope;
            this.entityType = this.options.entityType;
            this.field = this.options.field;
            this.readOnly = this.options.readOnly;

            this.fieldData = this.options.fieldData || {};
            if (this.options.isNew) {
                var cloned = {};
                for (var i in this.defaultFieldData) {
                    cloned[i] = Espo.Utils.clone(this.defaultFieldData[i]);
                }
                this.fieldData = _.extend(cloned, this.fieldData);
            }

            this.fieldType = this.model.getFieldType(this.field) || 'base';
        },

        afterRender: function () {
            this.handleSubjectType();
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
            } else if (this.fieldData.subjectType == 'value') {
                var viewName =  this.model.getFieldParam(this.field, 'view') || this.getFieldManager().getViewName(this.fieldType);

                this.createView('subject', viewName, {
                    el: this.options.el + ' .subject',
                    model: this.model,
                    defs: {
                        name: this.field,
                        params: {
                        },
                    },
                    mode: 'edit',
                    readOnly: this.readOnly,
                    readOnlyDisabled: true
                }, function (view) {
                    view.render();
                });
            }
        },

        fetch: function () {
            this.fieldData.attributes = {};
            if (this.fieldData.subjectType == 'value') {

                this.getView('subject').fetchToModel();
                if (this.getView('subject').validate()) {
                    return false;
                }
                this.fieldData.attributes = this.getView('subject').fetch();
            } else if (this.fieldData.subjectType == 'field') {
                this.fieldData.field = this.$el.find('[name="subject"]').val();
            }

            return true;
        },

    });
});
