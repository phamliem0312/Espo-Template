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

Espo.define('advanced:views/bpmn-flowchart/fields/flowchart', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        detailTemplate: 'advanced:bpmn-flowchart/fields/flowchart/detail',

        editTemplate: 'advanced:bpmn-flowchart/fields/flowchart/edit',

        height: 500,

        inlineEditDisabled: true,

        dataAttribute: 'data',

        events: {
            'click .action[data-action="setStateCreateFigure"]': function (e) {
                var type = $(e.currentTarget).data('name');
                this.setStateCreateFigure(type);
            },
            'click .action[data-action="resetState"]': function (e) {
                this.resetState(true);
            },
            'click .action[data-action="setStateCreateFlow"]': function (e) {
                this.setStateCreateFlow();
            },
            'click .action[data-action="setStateRemove"]': function (e) {
                this.setStateRemove();
            },
            'click .action[data-action="apply"]': function (e) {
                this.apply();
            },
            'click .action[data-action="zoomIn"]': function (e) {
                this.zoomIn();
            },
            'click .action[data-action="zoomOut"]': function (e) {
                this.zoomOut();
            },
            'click .action[data-action="switchFullScreenMode"]': function (e) {
                e.preventDefault();
                if (this.isFullScreenMode) {
                    this.unsetFullScreenMode();
                } else {
                    this.setFullScreenMode();
                }
            }
        },

        getAttributeList: function () {
            return [this.dataAttribute];
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.heightString = this.height.toString() + 'px';

            if (this.mode  === 'edit') {
                data.elementEventDataList = this.elementEventDataList;
                data.elementGatewayDataList = this.elementGatewayDataList;
                data.elementTaskDataList = this.elementTaskDataList;
                data.currentElement = this.currentElement;
            }
            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var startColor = '#69a345';
            var intermediateColor = '#5d86b0';
            var endColor = '#b34646';

            this.elementEventList = [
                'eventStart',
                'eventStartConditional',
                'eventStartTimer',
                'eventStartError',
                'eventStartEscalation',
                'eventStartSignal',
                'eventIntermediateConditionalCatch',
                'eventIntermediateTimerCatch',
                'eventIntermediateSignalCatch',
                'eventIntermediateMessageCatch',
                'eventIntermediateEscalationThrow',
                'eventIntermediateSignalThrow',
                'eventEnd',
                'eventEndTerminate',
                'eventEndError',
                'eventEndEscalation',
                'eventEndSignal',
                'eventIntermediateErrorBoundary',
                'eventIntermediateConditionalBoundary',
                'eventIntermediateTimerBoundary',
                'eventIntermediateEscalationBoundary',
                'eventIntermediateSignalBoundary',
                'eventIntermediateMessageBoundary',
            ];

            this.elementGatewayList = [
                'gatewayExclusive',
                'gatewayInclusive',
                'gatewayParallel',
                'gatewayEventBased',
            ];

            this.elementTaskList = [
                'task',
                'taskSendMessage',
                'taskUser',
                'taskScript',
                '_divider',
                'subProcess',
                'eventSubProcess',
                'callActivity',
            ];

            this.elementEventDataList = [
                {name: 'eventStart', color: startColor},
                {name: 'eventStartConditional', color: startColor},
                {name: 'eventStartTimer', color: startColor},
                {name: 'eventStartError', color: startColor},
                {name: 'eventStartEscalation', color: startColor},
                {name: 'eventStartSignal', color: startColor},
                {name: '_divider', color: null},
                {name: 'eventIntermediateConditionalCatch', color: intermediateColor},
                {name: 'eventIntermediateTimerCatch', color: intermediateColor},
                {name: 'eventIntermediateSignalCatch', color: intermediateColor},
                {name: 'eventIntermediateMessageCatch', color: intermediateColor},
                {name: '_divider', color: null},
                {name: 'eventIntermediateEscalationThrow', color: intermediateColor},
                {name: 'eventIntermediateSignalThrow', color: intermediateColor},
                {name: '_divider', color: null},
                {name: 'eventEnd', color: endColor},
                {name: 'eventEndTerminate', color: endColor},
                {name: 'eventEndError', color: endColor},
                {name: 'eventEndEscalation', color: endColor},
                {name: 'eventEndSignal', color: endColor},
                {name: '_divider', color: null},
                {name: 'eventIntermediateErrorBoundary', color: intermediateColor},
                {name: 'eventIntermediateConditionalBoundary', color: intermediateColor},
                {name: 'eventIntermediateTimerBoundary', color: intermediateColor},
                {name: 'eventIntermediateEscalationBoundary', color: intermediateColor},
                {name: 'eventIntermediateSignalBoundary', color: intermediateColor},
                {name: 'eventIntermediateMessageBoundary', color: intermediateColor},
            ];

            this.elementGatewayDataList = [];
            this.elementGatewayList.forEach(function (item) {
                this.elementGatewayDataList.push({name: item});
            }, this);

            this.elementTaskDataList = [];
            this.elementTaskList.forEach(function (item) {
                this.elementTaskDataList.push({name: item});
            }, this);

            this.dataHelper = {
                getAllDataList: function () {
                    return this.getAllDataList();
                }.bind(this),

                getElementData: function (id) {
                    return this.getElementData(id);
                }.bind(this),
            };

            this.wait(true);
            Espo.require('lib!client/modules/advanced/lib/espo-bpmn.js', function () {
                this.wait(false);
            }.bind(this));

            this.on('inline-edit-off', function () {
                this.currentState = null;
                this.currentElement = null;
            }, this);

            var data = Espo.Utils.cloneDeep(this.model.get(this.dataAttribute) || {});
            this.dataList = data.list || [];

            this.createdEntitiesData = data.createdEntitiesData || {};

            this.listenTo(this.model, 'change:' + this.dataAttribute, function (model, value, o) {
                if (o.ui) return;
                var data = Espo.Utils.cloneDeep(this.model.get(this.dataAttribute) || {});
                this.dataList = data.list || [];
            }, this);

            this.on('render', function () {
                if (this.canvas) {
                    this.offsetX = this.canvas.offsetX;
                    this.offsetY = this.canvas.offsetY;
                    this.scaleRatio = this.canvas.scaleRatio;
                }
            }, this);

            this.offsetX = null;
            this.offsetY = null;
            this.scaleRatio = null;
        },

        afterRender: function () {
            this.$groupContainer = this.$el.find('.flowchart-group-container');
            this.$container = this.$el.find('.flowchart-container');

            if (this.isFullScreenMode) {
                this.setFullScreenMode();
            }

            var containerElement = this.$container.get(0);
            var dataList = this.dataList;

            var dataDefaults = {};
            var elements = this.getMetadata().get(['clientDefs', 'BpmnFlowchart', 'elements']) || {};
            for (var type in elements) {
                if ('defaults' in elements[type]) {
                    dataDefaults[type] = Espo.Utils.cloneDeep(elements[type].defaults);
                }
            }

            dataDefaults.subProcess = dataDefaults.subProcess || {};
            dataDefaults.subProcess.targetType = this.model.get('targetType');

            dataDefaults.eventSubProcess = dataDefaults.eventSubProcess || {};
            dataDefaults.eventSubProcess.targetType = this.model.get('targetType');

            var o = {
                canvasWidth: '100%',
                canvasHeight: '100%',
                dataDefaults: dataDefaults,
                events: {
                    change: function () {
                        if (this.mode === 'edit') {
                            this.trigger('change');
                        }
                    }.bind(this),
                    resetState: function () {
                        this.currentElement = null;
                        this.currentState = null;
                        this.resetTool(true);
                    }.bind(this),
                    figureLeftClick: function (e) {
                        var id = e.figureId;
                        if (!id) return;
                        this.openElement(id);
                    }.bind(this),
                    removeFigure: function (e) {
                        this.onRemoveElement(e.figureId);
                        this.trigger('change');
                    }.bind(this),
                    createFigure: function (e) {
                        this.onCreateElement(e.figureId, e.figureData);
                    }.bind(this)
                },
                isReadOnly: this.mode !== 'edit',
                scaleDisabled: true,
                isEventSubProcess: this.isEventSubProcess,
            };

            if (this.offsetX !== null) {
                o.offsetX = this.offsetX;
            }
            if (this.offsetY !== null) {
                o.offsetY = this.offsetY;
            }
            if (this.scaleRatio !== null) {
                o.scaleRatio = this.scaleRatio;
            }

            var canvas = this.canvas = new window.EspoBpmn.Canvas(containerElement, o, dataList);

            if (this.mode === 'edit') {
                if (this.currentState) {
                    var o = null;
                    if (this.currentState === 'createFigure') {
                        this.setStateCreateFigure(this.currentElement);
                    } else {
                        var methodName = 'setState' + Espo.Utils.upperCaseFirst(this.currentState);
                        this[methodName]();
                    }
                } else {
                    this.resetTool(true);
                }
            }
        },

        openElement: function (id) {
            var list;

            var elementData = this.getElementData(id);

            if (!elementData) return;

            var parentSubProcessData = this.getParentSubProcessData(id);

            var targetType = this.model.get('targetType');

            if (parentSubProcessData) {
                targetType = parentSubProcessData.targetType || targetType;
            }

            var isInSubProcess = this.isSubProcess;

            if (!isInSubProcess) {
                isInSubProcess = !!parentSubProcessData;
            }

            if (!isInSubProcess) {
                isInSubProcess = !!this.model.get('parentProcessId');
            }

            if (this.mode === 'detail') {
                this.createView('modalView', 'advanced:views/bpmn-flowchart/modals/element-detail', {
                    elementData: elementData,
                    targetType: targetType,
                    flowchartDataList: this.dataList,
                    flowchartModel: this.model,
                    flowchartCreatedEntitiesData: this.createdEntitiesData,
                    dataHelper: this.dataHelper,
                    isInSubProcess: isInSubProcess,
                    isInSubProcess2: !!parentSubProcessData,
                }, function (view) {
                    view.render();

                    this.listenToOnce(view, 'remove', function () {
                        this.clearView('modalView');
                    }, this);
                }, this);
            } else if (this.mode === 'edit') {
                this.createView('modalEdit', 'advanced:views/bpmn-flowchart/modals/element-edit', {
                    elementData: elementData,
                    targetType: targetType,
                    flowchartDataList: this.dataList,
                    flowchartModel: this.model,
                    flowchartCreatedEntitiesData: this.createdEntitiesData,
                    dataHelper: this.dataHelper,
                    isInSubProcess: isInSubProcess,
                }, function (view) {
                    view.render();
                    this.listenTo(view, 'apply', function (data) {
                        for (var attr in data) {
                            elementData[attr] = data[attr];
                        }
                        if ('defaultFlowId' in data) {
                            this.updateDefaultFlow(id);
                        }
                        if ('actionList' in data) {
                            this.updateCreatedEntitiesData(id, data.actionList, targetType);
                        } else if (elementData.type === 'taskUser') {
                            this.updateCreatedEntitiesDataUserTask(id, data);
                        } else if (elementData.type === 'taskSendMessage') {
                            this.updateCreatedEntitiesDataSendMessageTask(id, data);
                        }
                        if (parentSubProcessData && parentSubProcessData.type === 'eventSubProcess') {
                            if ((elementData.type || '').indexOf('eventStart') === 0) {
                                this.updateEventSubProcessStartData(parentSubProcessData.id, data);
                            }
                        }
                        this.trigger('change');
                        view.remove();
                        this.reRender();
                    }, this);
                    this.listenToOnce(view, 'remove', function () {
                        this.clearView('modalEdit');
                    }, this);
                }, this);
            }
        },

        updateCreatedEntitiesDataUserTask: function (id, data) {
            var numberId = (id in this.createdEntitiesData) ? this.createdEntitiesData[id].numberId : this.getNextCreatedEntityNumberId('BpmnUserTask');
            this.createdEntitiesData[id] = {
                elementId: id,
                actionId: null,
                entityType: 'BpmnUserTask',
                numberId: numberId,
                text: data.text || null,
            };
        },

        updateCreatedEntitiesDataSendMessageTask: function (id, data) {
            if (data.messageType === 'Email' && !data.doNotStore) {
                var numberId = (id in this.createdEntitiesData) ? this.createdEntitiesData[id].numberId : this.getNextCreatedEntityNumberId('Email');
                this.createdEntitiesData[id] = {
                    elementId: id,
                    actionId: null,
                    entityType: 'Email',
                    numberId: numberId,
                    text: data.text || null,
                };
            } else {
                delete this.createdEntitiesData[id];
            }
        },

        removeCreatedEntitiesDataUserTask: function (id) {
            delete this.createdEntitiesData[id];
        },

        updateCreatedEntitiesData: function (id, actionList, targetType) {
            var toRemoveEIdList = [];
            for (var eId in this.createdEntitiesData) {
                var item = this.createdEntitiesData[eId];
                if (item.elementId === id) {
                    var isMet = false;
                    actionList.forEach(function (actionItem) {
                        if (item.actionId === actionItem.id) {
                            isMet = true;
                        }
                    }, this);
                    if (!isMet) {
                        toRemoveEIdList.push(eId);
                    }
                }
            }
            toRemoveEIdList.forEach(function (eId) {
                delete this.createdEntitiesData[eId];
            }, this);

            actionList.forEach(function (item) {
                if (!~['createRelatedEntity', 'createEntity'].indexOf(item.type)) return;
                var eId = id + '_' + item.id;

                var entityType;
                var link = null;
                if (item.type === 'createEntity') {
                    entityType = item.entityType;
                } else if (item.type === 'createRelatedEntity') {
                    link = item.link;
                    targetType = targetType || this.model.get('targetType');
                    entityType = this.getMetadata().get(['entityDefs', targetType, 'links', item.link, 'entity']);
                }
                if (!entityType) return;

                var numberId = (eId in this.createdEntitiesData) ? this.createdEntitiesData[eId].numberId : this.getNextCreatedEntityNumberId(entityType);

                this.createdEntitiesData[eId] = {
                    elementId: id,
                    actionId: item.id,
                    link: link,
                    entityType: entityType,
                    numberId: numberId,
                };
            }, this);
        },

        getNextCreatedEntityNumberId: function (entityType) {
            var numberId = 0;
            for (var eId in this.createdEntitiesData) {
                var item = this.createdEntitiesData[eId];
                if (entityType == item.entityType) {
                    if ('numberId' in item) {
                        numberId = item.numberId + 1;
                    }
                }
            }

            return numberId;
        },

        updateDefaultFlow: function (id) {
            var data = this.getElementData(id);
            var flowIdItemList = this.getElementFlowIdList(id);
            flowIdItemList.forEach(function (flowId) {
                var flowData = this.getElementData(flowId);
                if (!flowData) return;
                flowData.isDefault = false;
                if (data.defaultFlowId === flowId) {
                    flowData.isDefault = true;
                }
            }, this);
        },

        getElementFlowIdList: function (id) {
            var idList = [];
            this.getAllDataList().forEach(function (item) {
                if (item.type !== 'flow') return;

                if (item.startId === id && item.endId) {
                    var endItem = this.getElementData(item.endId);
                    if (!endItem) return;
                    idList.push(item.id);
                }
            }, this);
            return idList;
        },

        getElementData: function (id) {
            var o = {};
            this._findElementData(id, this.dataList, o);
            return o.data || null;
        },

        _findElementData: function (id, dataList, o) {
            for (var i = 0; i < dataList.length; i++) {
                if (dataList[i].id === id) {
                    o.data = dataList[i];
                    return;
                } else if (dataList[i].type === 'subProcess' || dataList[i].type === 'eventSubProcess') {
                    this._findElementData(id, dataList[i].dataList || [], o);
                    if (o.data) return;
                }
            }
        },

        getParentSubProcessId: function (id) {
            var o = {};
            this._findParentSubProcessId(id, this.dataList, o);
            return o.id || null;
        },

        _findParentSubProcessId: function (id, dataList, o, parentId) {
            for (var i = 0; i < dataList.length; i++) {
                if (dataList[i].id === id) {
                    o.id = parentId;
                    return;
                } else if (dataList[i].type === 'subProcess' || dataList[i].type === 'eventSubProcess') {
                    this._findParentSubProcessId(id, dataList[i].dataList || [], o, dataList[i].id);
                    if (o.id) return;
                }
            }
        },

        getParentSubProcessData: function (id) {
            var o = {};
            this._findParentSubProcessData(id, this.dataList, o);
            return o.data || null;
        },

        _findParentSubProcessData: function (id, dataList, o, parentData) {
            for (var i = 0; i < dataList.length; i++) {
                if (dataList[i].id === id) {
                    o.data = parentData;
                    return;
                } else if (dataList[i].type === 'subProcess' || dataList[i].type === 'eventSubProcess') {
                    this._findParentSubProcessData(id, dataList[i].dataList || [], o, dataList[i]);
                    if (o.data) return;
                }
            }
        },

        updateEventSubProcessStartData: function (id, data) {
            var item = this.getElementData(id);
            item.eventStartData = _.extend(item.eventStartData, Espo.Utils.cloneDeep(data));
        },

        resetTool: function (isHandTool) {
            this.$el.find('.action[data-action="setStateCreateFigure"] span').addClass('hidden');
            this.$el.find('.button-container .btn').removeClass('active');
            if (isHandTool) {
                this.$el.find('.button-container .btn[data-action="resetState"]').addClass('active');
            }
        },

        setStateCreateFigure: function (type) {
            this.currentElement = type;

            this.currentState = 'createFigure';
            this.canvas.setState('createFigure', {type: type});
            this.resetTool();
            this.$el.find('.action[data-action="setStateCreateFigure"][data-name="'+type+'"] span').removeClass('hidden');

            if (~this.elementEventList.indexOf(type)) {
                this.$el.find('.button-container .btn.add-event-element').addClass('active');
            } else if (~this.elementGatewayList.indexOf(type)) {
                this.$el.find('.button-container .btn.add-gateway-element').addClass('active');
            } else if (~this.elementTaskList.indexOf(type)) {
                this.$el.find('.button-container .btn.add-task-element').addClass('active');
            }
        },

        setStateCreateFlow: function () {
            this.resetState();
            this.currentState = 'createFlow';
            this.canvas.setState('createFlow');

            this.$el.find('.button-container .btn[data-action="setStateCreateFlow"]').addClass('active');
        },

        setStateRemove: function () {
            this.resetState();
            this.currentState = 'remove';
            this.canvas.setState('remove');

            this.$el.find('.button-container .btn[data-action="setStateRemove"]').addClass('active');
        },

        resetState: function (isHandTool) {
            this.canvas.resetState();
            this.currentState = null;
            this.currentElement = null;
            this.resetTool(isHandTool);
        },

        getAllDataList: function () {
            var list = [];
            this._populateAllList(this.dataList, list);
            return list;
        },

        _populateAllList: function (dataList, list) {
            for (var i = 0; i < dataList.length; i++) {
                list.push(dataList[i]);
                if (dataList[i].type === 'subProcess' || dataList[i].type === 'eventSubProcess') {
                    this._populateAllList(dataList[i].dataList || [], list);
                }
            }
        },

        onCreateElement: function (id, data) {
            var item = this.getElementData(id);

            if (item.type === 'taskUser') {
                this.updateCreatedEntitiesDataUserTask(id, item);
            } else if (item.type === 'taskSendMessage') {
                this.updateCreatedEntitiesDataSendMessageTask(id, item);
            }
        },

        onRemoveElement: function (id) {
            this.getAllDataList().forEach(function (item) {
                if (item.defaultFlowId === id) {
                    item.defaultFlowId = null;
                }
                if (item.flowList) {
                    var flowList = item.flowList;
                    var flowListCopy = [];
                    var isMet = false;
                    flowList.forEach(function (flowItem) {
                        if (flowItem.id === id) {
                            isMet = true;
                            return;
                        }
                        flowListCopy.push(flowItem);
                    }, this);
                    if (isMet) {
                        item.flowList = flowListCopy;
                    }
                }
            }, this);

            this.updateCreatedEntitiesData(id, []);
            this.removeCreatedEntitiesDataUserTask(id);
        },

        setFullScreenMode: function () {
            this.isFullScreenMode = true;

            var color = null;
            var $ref = this.$groupContainer;
            while (1) {
                color = window.getComputedStyle($ref.get(0), null).getPropertyValue('background-color');
                if (color && color !== 'rgba(0, 0, 0, 0)') break;
                $ref = $ref.parent();
                if (!$ref.length) break;
            }

            this.$groupContainer.css({
                width: '100%',
                height: '100%',
                position: 'fixed',
                top: 0,
                zIndex: 1050,
                left: 0,
                backgroundColor: color,
            });

            this.$container.css('height', '100%');

            this.$el.find('button[data-action="apply"]').removeClass('hidden');

            this.canvas.params.scaleDisabled = false;
        },

        unsetFullScreenMode: function () {
            this.isFullScreenMode = false;

            this.$groupContainer.css({
                width: '',
                height: '',
                position: 'static',
                top: '',
                left: '',
                zIndex: '',
                backgroundColor: '',
            });

            this.$container.css('height', this.height.toString() + 'px');

            this.$el.find('button[data-action="apply"]').addClass('hidden');

            this.canvas.params.scaleDisabled = true;
        },

        apply: function () {
            this.model.save().then(function () {
                Espo.Ui.success(this.translate('Saved'));
            }.bind(this));
        },

        zoomIn: function () {
            this.canvas.scaleCentered(2);
        },

        zoomOut: function () {
            this.canvas.scaleCentered(-2);
        },

        fetch: function () {
            var fieldData = {};

            fieldData.list = Espo.Utils.cloneDeep(this.dataList);
            fieldData.createdEntitiesData = Espo.Utils.cloneDeep(this.createdEntitiesData);

            var data = {};
            data[this.dataAttribute] = fieldData;
            return data;
        },

    });
});
