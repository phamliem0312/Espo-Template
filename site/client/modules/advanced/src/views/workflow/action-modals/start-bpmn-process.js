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

define('advanced:views/workflow/action-modals/start-bpmn-process', ['advanced:views/workflow/action-modals/trigger-workflow', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/start-bpmn-process',

        data: function () {
            return _.extend({
            }, Dep.prototype.data.call(this));
        },


        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setupTargetOptions();

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

            this.createView('target', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="target"]',
                defs: {
                    name: 'target',
                    params: {
                        options: this.targetOptionList,
                        translatedOptions: this.targetTranslatedOptions,
                    }
                },
                readOnly: this.readOnly,
            });

            this.createView('flowchart', 'advanced:views/workflow/fields/flowchart', {
                el: this.options.el + ' .field[data-name="flowchart"]',
                model: model,
                mode: 'edit',
                foreignScope: 'BpmnFlowchart',
                entityType: this.getTargetEntityType(),
                defs: {
                    name: 'flowchart',
                    params: {
                        required: true,
                    }
                },
                targetEntityType: this.getTargetEntityType(),
                labelText: this.translate('BpmnFlowchart', 'scopeNames'),
            });

            this.listenTo(model, 'change:target', function (target) {
                model.trigger('change-target-entity-type', this.getTargetEntityType());
            }, this);

            this.createView('elementId', 'advanced:views/workflow/fields/process-start-element-id', {
                el: this.options.el + ' .field[data-name="elementId"]',
                model: model,
                mode: 'edit',
                defs: {
                    name: 'elementId',
                    params: {
                        required: true,
                        options: this.actionData.startElementIdList || [],
                    }
                },
                translatedOptions: this.actionData.startElementNames || {},
            });

            this.listenTo(model, 'change:target', function (m, v, o) {
                if (!o.ui) return;

                model.set('flowchartId', null);
                model.set('flowchartName', null);
                model.set('elementId', null);

            }, this);
        },

        getTargetEntityType: function () {
            var entityType = this.getEntityTypeFromTarget(this.model2.get('target'));

            return entityType;
        },

        setupTargetOptions: function () {
            var targetOptionList = [''];

            var translatedOptions = {};

            translatedOptions[''] = this.translate('Current', 'labels', 'Workflow') + ' (' + this.translate(this.entityType, 'scopeNames') + ')';

            if (this.options.flowchartCreatedEntitiesData) {
                Object.keys(this.options.flowchartCreatedEntitiesData).forEach(function (aliasId) {
                    var entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
                    targetOptionList.push('created:' + aliasId);
                    translatedOptions['created:' + aliasId] = this.translateCreatedEntityAlias(aliasId, true);
                }, this);
            }

            var linkList = [];

            var linkDefs = this.getMetadata().get(['entityDefs', this.entityType, 'links']) || {};
            Object.keys(linkDefs).forEach(function (link) {
                var type = linkDefs[link].type;
                if (type !== 'belongsTo' && type !== 'belongsToParent') return;

                var foreignEntityType = linkDefs[link].entity;
                if (type !== 'belongsToParent') {
                    if (!foreignEntityType) return;
                    if (!this.getMetadata().get(['scopes', foreignEntityType, 'object'])) return;
                }

                var item = 'link:' + link;

                targetOptionList.push(item);

                translatedOptions[item] = this.translateTargetItem(item, true);

                linkList.push(link);
            }, this);

            linkList.forEach(function (link) {
                var entityType = linkDefs[link].entity;
                if (entityType) {
                    var subLinkDefs = this.getMetadata().get(['entityDefs', entityType, 'links']) || {};
                    Object.keys(subLinkDefs).forEach(function (subLink) {
                        var type = subLinkDefs[subLink].type;
                        if (type !== 'belongsTo' && type !== 'belongsToParent') return;

                        var foreignEntityType = subLinkDefs[subLink].entity;
                        if (type !== 'belongsToParent') {
                            if (!foreignEntityType) return;
                            if (!this.getMetadata().get(['scopes', foreignEntityType, 'object'])) return;
                        }

                        var item = 'link:' + link + '.' + subLink;
                        targetOptionList.push(item);

                        translatedOptions[item] = this.translateTargetItem(item, true);

                    }, this);
                }
            }, this);

            this.targetOptionList = targetOptionList;
            this.targetTranslatedOptions = translatedOptions;
        },

        fetch: function () {
            var flowchartView = this.getView('flowchart');
            flowchartView.fetchToModel();
            if (flowchartView.validate()) {
                return;
            }

            var elementIdView = this.getView('elementId');
            elementIdView.fetchToModel();
            if (elementIdView.validate()) {
                return;
            }

            var o = flowchartView.fetch();
            this.actionData.flowchartName = o.flowchartName;
            this.actionData.flowchartId = o.flowchartId;

            this.actionData.target = (this.getView('target').fetch()).target || null;

            this.actionData.startElementIdList = this.model2.get('startElementIdList') || [];
            this.actionData.startElementNames = this.model2.get('startElementNames') || {};

            this.actionData.elementId = (this.getView('elementId').fetch()).elementId || null;

            return true;
        },

    });
});
