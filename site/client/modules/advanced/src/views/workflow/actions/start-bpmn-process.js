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

define('advanced:views/workflow/actions/start-bpmn-process', ['advanced:views/workflow/actions/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/actions/start-bpmn-process',

        type: 'startBpmnProcess',

        defaultActionData: {
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.targetTranslated = this.getTargetTranslated();
            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var model = this.model2 = new Model();
            model.name = 'BpmnFlowchart';
            model.set({
                flowchartId: this.actionData.flowchartId,
                flowchartName: this.actionData.flowchartName,
                elementId: this.actionData.elementId,
                target: this.actionData.target,
                startElementIdList: this.actionData.startElementIdList,
                startElementNames: this.actionData.startElementNames,
            });

            this.createView('flowchart', 'views/fields/link', {
                el: this.options.el + ' .field[data-name="flowchart"]',
                model: model,
                foreignScope: 'BpmnFlowchart',
                name: 'flowchart',
                mode: 'detail',
                readOnly: true,
            });

            this.createView('elementId', 'advanced:views/workflow/fields/process-start-element-id', {
                el: this.options.el + ' .field[data-name="elementId"]',
                model: model,
                readOnly: true,
                mode: 'detail',
                name: 'elementId',
                options: this.actionData.startElementIdList || [],
                translatedOptions: this.actionData.startElementNames || {},

            });
        },

        afterEdit: function () {
            this.model2.set({
                flowchartId: this.actionData.flowchartId,
                flowchartName: this.actionData.flowchartName,
                elementId: this.actionData.elementId,
                target: this.actionData.target,
                startElementIdList: this.actionData.startElementIdList,
                startElementNames: this.actionData.startElementNames,
            });
        },

        getTargetTranslated: function () {
            return this.translateTargetItem(this.actionData.target);
        },

    });
});
