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

Espo.define('advanced:views/workflow/action-modals/trigger-workflow', ['advanced:views/workflow/action-modals/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/trigger-workflow',

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

            this.createView('executionTime', 'advanced:views/workflow/action-fields/execution-time', {
                el: this.options.el + ' .execution-time-container',
                executionData: this.actionData.execution || {},
                entityType: this.entityType
            });

            var model = this.model2 = new Model();

            model.name = 'Workflow';

            model.set({
                workflowId: this.actionData.workflowId,
                workflowName: this.actionData.workflowName,
                target: this.actionData.target,
            });

            this.createView('target', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="target"]',
                defs: {
                    name: 'target',
                    params: {
                        options: this.targetOptionList,
                        translatedOptions: this.targetTranslatedOptions
                    }
                },
                readOnly: this.readOnly,
            });

            this.createView('workflow', 'advanced:views/workflow/fields/workflow', {
                el: this.options.el + ' .field-workflow',
                model: model,
                mode: 'edit',
                foreignScope: 'Workflow',
                entityType: this.getTargetEntityType(),
                defs: {
                    name: 'workflow',
                    params: {
                        required: true
                    }
                }
            });

            this.listenTo(this.model2, 'change:target', function (m, v, o) {
                if (!o.ui) return;

                model.set('workflowId', null);
                model.set('workflowName', null);

                var view = this.getView('workflow');
                if (view) {
                    view.options.entityType = this.getTargetEntityType();
                }
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
            var workflowView = this.getView('workflow');
            workflowView.fetchToModel();
            if (workflowView.validate()) {
                return;
            }
            var o = workflowView.fetch();
            this.actionData.workflowId = o.workflowId;
            this.actionData.workflowName = o.workflowName;

            this.actionData.target = (this.getView('target').fetch()).target || null;

            this.actionData.execution = this.actionData.execution || {};
            this.actionData.execution.type = this.$el.find('[name="executionType"]').val();

            if (this.actionData.execution.type != 'immediately') {
                this.actionData.execution.field = this.$el.find('[name="executionField"]').val();
                this.actionData.execution.shiftDays = this.$el.find('[name="shiftDays"]').val();
                this.actionData.execution.shiftUnit = this.$el.find('[name="shiftUnit"]').val();

                if (this.$el.find('[name="shiftDaysOperator"]').val() == 'minus') {
                    this.actionData.execution.shiftDays = (-1) * this.actionData.execution.shiftDays;
                }
            }

            return true;
        },

    });
});
