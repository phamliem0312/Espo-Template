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

Espo.define('advanced:views/workflow/actions/trigger-workflow', ['advanced:views/workflow/actions/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/actions/trigger-workflow',

        type: 'triggerWorkflow',

        defaultActionData: {
            execution: {
                type: 'immediately',
                field: false,
                shiftDays: 0,
                shiftUnit: 'days'
            }
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.targetTranslated = this.getTargetTranslated();
            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('executionTime', 'advanced:views/workflow/action-fields/execution-time', {
                el: this.options.el + ' .execution-time-container',
                executionData: this.actionData.execution || {},
                entityType: this.entityType,
                readOnly: true
            });

            var model = new Model();
            model.name = 'Workflow';
            model.set({
                workflowId: this.actionData.workflowId,
                workflowName: this.actionData.workflowName
            });

            this.createView('workflow', 'views/fields/link', {
                el: this.options.el + ' .field-workflow',
                model: model,
                mode: 'edit',
                foreignScope: 'Workflow',
                defs: {
                    name: 'workflow',
                    params: {
                        required: true
                    }
                },
                readOnly: true
            });
        },

        render: function (callback) {
            this.getView('executionTime').reRender();

            var workflowView = this.getView('workflow');
            workflowView.model.set({
                workflowId: this.actionData.workflowId,
                workflowName: this.actionData.workflowName
            });
            workflowView.reRender();

            Dep.prototype.render.call(this, callback);
        },

        getTargetTranslated: function () {
            return this.translateTargetItem(this.actionData.target);
        },

    });
});
