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

Espo.define('advanced:views/bpmn-flowchart-element/fields/flows-conditions', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        detailTemplate: 'advanced:bpmn-flowchart-element/fields/flows-conditions/detail',

        editTemplate: 'advanced:bpmn-flowchart-element/fields/flows-conditions/detail',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setupConditionsList();

            this.listenTo(this.model, 'change:defaultFlowId', function () {
                this.setupConditionsList(function () {
                    this.reRender();
                }.bind(this));
            }, this);
        },

        events: {
            'click [data-action="moveUp"]': function (e) {
                var id = $(e.currentTarget).data('id');
                this.moveUp(id);
            },
            'click [data-action="moveDown"]': function (e) {
                var id = $(e.currentTarget).data('id');
                this.moveDown(id);
            }
        },

        data: function () {
            var data = {};

            var flowDataList = [];
            var flowList = this.getFlowList();
            flowList.forEach(function (item, i) {
                flowDataList.push({
                    id: item.id,
                    label: this.getFlowLabel(item.id),
                    isTop: i === 0,
                    isBottom: i === flowList.length - 1
                });
            }, this);
            data.flowDataList = flowDataList;

            data.isEditMode = this.mode === 'edit';

            return data;
        },

        moveUp: function (id) {
            this.fetchToModel();

            var flowList = this.getFlowList();
            var isMet = false;
            flowList.forEach(function (item, i) {
                if (isMet) return;
                if (item.id === id && i > 0) {
                    var temp = flowList[i];
                    flowList[i] = flowList[i - 1];
                    flowList[i - 1] = temp;
                    isMet = true;
                }
            }, this);
            this.model.set('flowList', flowList);
            this.setupConditionsList(function () {
                this.reRender();
            }.bind(this));
        },

        moveDown: function (id) {
            this.fetchToModel();

            var flowList = this.getFlowList();
            var isMet = false;
            flowList.forEach(function (item, i) {
                if (isMet) return;
                if (item.id === id && i < flowList.length - 1) {
                    var temp = flowList[i];
                    flowList[i] = flowList[i + 1];
                    flowList[i + 1] = temp;
                    isMet = true;
                }
            }, this);
            this.model.set('flowList', flowList);
            this.setupConditionsList(function () {
                this.reRender();
            }.bind(this));
        },

        setupConditionsList: function (callback) {
            var flowList = this.getFlowList();
            var countLoaded = 0;

            flowList.forEach(function (item) {
                var key = item.id;

                var conditionsModel = new Model();
                conditionsModel.set({
                    conditionsAll: item.conditionsAll || [],
                    conditionsAny: item.conditionsAny || [],
                    conditionsFormula: item.conditionsFormula || null
                });

                this.createView(key, 'advanced:views/workflow/record/conditions', {
                    entityType: this.model.targetEntityType,
                    el: this.getSelector() + ' .flow[data-id="'+item.id+'"]',
                    readOnly: this.mode !== 'edit',
                    model: conditionsModel,
                    flowchartCreatedEntitiesData: this.model.flowchartCreatedEntitiesData,
                    isChangedDisabled: true
                }, function () {
                    countLoaded++;
                    if (countLoaded === flowList.length) {
                        if (callback) {
                            callback();
                        }
                    }
                });
            }, this);
        },

        getFlowLabel: function (id) {
            var item = this.getElementData(id);
            if (!item) return;
            var endItem = this.getElementData(item.endId);
            if (!endItem) return;
            return this.translate(endItem.type, 'elements', 'BpmnFlowchart') + ': ' +  (endItem.text || endItem.id);
        },

        getFlowList: function () {
            var flowList = this.model.get('flowList') || [];

            var flowIdList = this.getFlowIdList();

            flowIdList.forEach(function (flowId) {
                var isMet = false;
                flowList.forEach(function (item) {
                    if (item.id === flowId) isMet = true;
                });
                if (!isMet) {
                    flowList.push({
                        id: flowId,
                        conditionsAll: [],
                        conditionsAny: [],
                        conditionsFormula: null
                    });
                }
            }, this);

            var flowListCopy = [];
            flowList.forEach(function (item) {
                if (item.id === this.model.get('defaultFlowId')) return;
                flowListCopy.push(item);
            }, this);

            return flowListCopy;
        },

        getFlowIdList: function () {
            var flowIdList = [];

            var dataList = this.model.dataHelper.getAllDataList();

            dataList.forEach(function (item) {
                if (item.type !== 'flow') return;

                if (item.startId === this.model.id && item.endId) {
                    var endItem = this.getElementData(item.endId);
                    if (!endItem) return;
                    flowIdList.push(item.id);
                }
            }, this);
            return flowIdList;
        },

        getElementData: function (id) {
            return this.model.dataHelper.getElementData(id);
        },

        fetch: function () {
            var flowList = this.getFlowList();

            flowList.forEach(function (item) {
                var conditionsData = this.getView(item.id).fetch();
                item.conditionsAll = conditionsData.all;
                item.conditionsAny = conditionsData.any;
                item.conditionsFormula = conditionsData.formula;
            }, this);

            return {
                flowList: flowList
            };
        }

    });

});