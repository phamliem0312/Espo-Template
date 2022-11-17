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

Espo.define('advanced:views/bpmn-flowchart-element/fields/actions', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        detailTemplate: 'advanced:bpmn-flowchart-element/fields/actions/detail',

        editTemplate: 'advanced:bpmn-flowchart-element/fields/actions/detail',

        setup: function () {
            Dep.prototype.setup.call(this);

            var model = new Model;
            model.set('entityType', this.model.targetEntityType);
            var actionList = this.model.get('actionList') || [];
            model.set('actions', actionList);

            var actionTypeList = Espo.Utils.clone(
                this.getMetadata().get(['clientDefs', 'BpmnFlowchart', 'elements', 'task', 'fields', 'actions', 'actionTypeList'])
            );

            this.createView('actions', 'advanced:views/workflow/record/actions', {
                entityType: this.model.targetEntityType,
                el: this.getSelector() + ' > .actions-container',
                readOnly: this.mode !== 'edit',
                model: model,
                actionTypeList: actionTypeList,
                flowcharElementId: this.model.id,
                flowchartCreatedEntitiesData: this.model.flowchartCreatedEntitiesData
            });
        },

        events: {

        },

        data: function () {
            var data = {};
            data.isEditMode = this.mode === 'edit';
            return data;
        },

        fetch: function () {
            var actionList = this.getView('actions').fetch();

            return {
                actionList: actionList
            };
        }

    });

});