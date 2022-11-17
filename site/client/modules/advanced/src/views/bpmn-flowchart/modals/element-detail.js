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

Espo.define(
    'advanced:views/bpmn-flowchart/modals/element-detail',
    ['views/modal', 'model', 'advanced:bpmn-element-helper'],
    function (Dep, Model, BpmnElementHelper) {

    return Dep.extend({

        template: 'advanced:bpmn-flowchart/modals/element-detail',

        backdrop: true,

        fitHeight: true,

        cssName: 'detail-modal',

        className: 'dialog dialog-record',

        setup: function () {
            this.elementData = Espo.Utils.cloneDeep(this.options.elementData || {});
            this.targetType = this.options.targetType;

            var model = this.model = new Model;
            model.name = 'BpmnFlowchartElement';
            model.set(this.elementData);

            this.elementType = this.model.get('type');

            model.targetEntityType = this.targetType;
            model.flowchartDataList = this.options.flowchartDataList;
            model.flowchartModel = this.options.flowchartModel;
            model.flowchartCreatedEntitiesData = this.options.flowchartCreatedEntitiesData;
            model.elementType = this.elementType;
            model.elementHelper = new BpmnElementHelper(this.getHelper(), model);
            model.dataHelper = this.options.dataHelper;
            model.isInSubProcess = this.options.isInSubProcess;

            var fields = this.getMetadata().get(['clientDefs', 'BpmnFlowchart', 'elements', this.elementType, 'fields']) || {};

            model.defs = {
                fields: fields
            };

            this.header = this.translate(this.elementType, 'elements', 'BpmnFlowchart');

            this.buttonList = [
                {
                    name: 'cancel',
                    label: 'Close'
                }
            ];

            if (
                this.model.flowchartModel &&
                this.model.flowchartModel.entityType === 'BpmnProcess' &&
                this.model.flowchartModel.get('status') === 'Started' &&
                !this.options.isInSubProcess2
            ) {
                this.buttonList.push({
                    name: 'startFromHere',
                    html: this.translate('Start flow from here', 'labels', 'BpmnProcess'),
                    pullLeft: true,
                });
            }

            var viewName = this.getMetadata().get(['clientDefs', 'BpmnFlowchart', 'elements', this.elementType, 'recordDetailView']) ||
                'advanced:views/bpmn-flowchart-element/record/detail';

            var detailLayout = this.getMetadata().get(['clientDefs', 'BpmnFlowchart', 'elements', this.elementType, 'layout']);

            var dynamicLogicDefs = this.getMetadata().get(['clientDefs', 'BpmnFlowchart', 'elements', this.elementType, 'dynamicLogic']);

            var options = {
                model: this.model,
                el: this.containerSelector + ' .record-container',
                type: 'detailSmall',
                detailLayout: detailLayout,
                columnCount: 2,
                buttonsPosition: false,
                buttonsDisabled: true,
                inlineEditDisabled: true,
                sideDisabled: true,
                bottomDisabled: true,
                isWide: true,
                dynamicLogicDefs: dynamicLogicDefs,
            };
            this.createView('record', viewName, options);
        },

        actionStartFromHere: function () {
            this.confirm(this.translate('confirmation', 'messages'), function () {
                Espo.Ajax.postRequest('BpmnProcess/action/startFlowFromElement', {
                    processId: this.model.flowchartModel.id,
                    elementId: this.model.id,
                }).then(function () {
                    Espo.Ui.success(this.translate('Done'));
                    this.model.flowchartModel.fetch();
                    this.close();
                }.bind(this));
            }.bind(this));
        },
    });
});