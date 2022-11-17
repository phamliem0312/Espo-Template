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

define(
    'advanced:views/workflow/action-modals/apply-assignment-rule',
    ['advanced:views/workflow/action-modals/base', 'model'],
    function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/apply-assignment-rule',

        data: function () {
            return _.extend({

            }, Dep.prototype.data.call(this));
        },

        events: {
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.controlVisibility();
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var model = new Model();
            model.name = 'Workflow';

            this.actionModel = model;

            this.actionModel.targetEntityType = this.options.entityType;

            model.set({
                assignmentRule: this.actionData.assignmentRule,
                targetTeamId: this.actionData.targetTeamId,
                targetTeamName: this.actionData.targetTeamName,
                targetUserPosition: this.actionData.targetUserPosition,
                listReportId: this.actionData.listReportId,
                listReportName: this.actionData.listReportName,
                target: this.actionData.target || ''
            });

            this.createView('assignmentRule', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="assignmentRule"]',
                defs: {
                    name: 'assignmentRule',
                    params: {
                        options: this.getMetadata().get('entityDefs.Workflow.assignmentRuleList') || []
                    }
                },
                readOnly: this.readOnly,
            });

            this.createView('targetTeam', 'views/fields/link', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="targetTeam"]',
                foreignScope: 'Team',
                defs: {
                    name: 'targetTeam',
                    params: {
                        required: true
                    }
                },
                readOnly: this.readOnly,
            });

            this.createView('targetUserPosition', 'advanced:views/workflow/fields/target-user-position', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="targetUserPosition"]',
                defs: {
                    name: 'targetUserPosition'
                },
                readOnly: this.readOnly,
            });

            this.createView('listReport', 'advanced:views/workflow/fields/list-report', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="listReport"]',
                entityType: this.options.entityType,
                foreignScope: 'Report',
                defs: {
                    name: 'listReport'
                },
                readOnly: this.readOnly,
            });

            if (this.options.flowchartCreatedEntitiesData) {
                this.controlTargetEntity();

                var targetList = ['', 'process'];

                var translatedOptions = {
                    '': this.translate('Target Entity', 'labels', 'Workflow') + ' (' +
                        this.translate(this.entityType, 'scopeName') + ')',
                    'process': this.translate('Process', 'labels', 'Workflow')
                };

                Object.keys(this.options.flowchartCreatedEntitiesData).forEach(function (aliasId) {
                    targetList.push('created:' + aliasId);

                    var link = this.options.flowchartCreatedEntitiesData[aliasId].link;
                    var entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
                    var numberId = this.options.flowchartCreatedEntitiesData[aliasId].numberId;
                    var text = this.options.flowchartCreatedEntitiesData[aliasId].text;

                    var label = this.translate('Created', 'labels', 'Workflow') + ': ';

                    if (link) {
                        label += this.translate(link, 'links', this.entityType) + ' - ';
                    }

                    label += this.translate(entityType, 'scopeNames');

                    if (text) {
                        label += ' \'' + text + '\'';
                    }
                    else {
                        if (numberId) {
                            label += ' #' + numberId.toString();
                        }
                    }

                    translatedOptions['created:' + aliasId] = label;
                }, this);

                this.createView('target', 'views/fields/enum', {
                    mode: 'edit',
                    model: model,
                    el: this.options.el + ' .field[data-name="target"]',
                    defs: {
                        name: 'target',
                        params: {
                            options: targetList
                        }
                    },
                    readOnly: this.readOnly,
                    translatedOptions: translatedOptions
                });

                this.listenTo(model, 'change:target', function () {
                    this.actionData.target = model.get('target') || null;

                    model.set({
                        listReportId: null,
                        listReportName: null
                    });

                    this.controlTargetEntity();
                    this.controlVisibility();
                }, this);
            }

            this.listenTo(model, 'change:assignmentRule', function () {
                this.controlVisibility();
            }, this);
        },

        controlTargetEntity: function () {
            this.actionModel.targetEntityType = this.options.entityType;

            if (!this.actionData.target) return;

            if (this.actionData.target.indexOf('created:') === 0) {
                this.actionModel.targetEntityType = null;

                var aliasId = this.actionData.target.substr(8);

                if (this.options.flowchartCreatedEntitiesData[aliasId]) {
                    this.actionModel.targetEntityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
                }
            }
            else if (this.actionData.target === 'process') {
                this.actionModel.targetEntityType = null;
            }
        },

        controlVisibility: function () {
            if (!this.isRendered()) {
                return;
            }

            var $listReportCell = this.getView('listReport').$el.closest('.cell');

            if (this.actionData.target === 'process' || this.actionModel.get('assignmentRule') !== 'Least-Busy') {
                $listReportCell.addClass('hidden');
            }
            else {
                $listReportCell.removeClass('hidden');
            }
        },

        fetch: function () {
            var actionModel = this.actionModel;

            this.getView('assignmentRule').fetchToModel();
            this.getView('targetTeam').fetchToModel();
            this.getView('targetUserPosition').fetchToModel();
            this.getView('listReport').fetchToModel();

            if (this.options.flowchartCreatedEntitiesData) {
                this.getView('target').fetchToModel();
            }

            var isNotValid = false;

            isNotValid = this.getView('assignmentRule').validate() || isNotValid;
            isNotValid = this.getView('targetTeam').validate() || isNotValid;
            isNotValid = this.getView('targetUserPosition').validate() || isNotValid;
            isNotValid = this.getView('listReport').validate() || isNotValid;

            if (isNotValid) {
                return;
            }

            this.actionData.assignmentRule = actionModel.get('assignmentRule');
            this.actionData.targetTeamId = actionModel.get('targetTeamId');
            this.actionData.targetTeamName = actionModel.get('targetTeamName');
            this.actionData.targetUserPosition = actionModel.get('targetUserPosition');
            this.actionData.listReportId = actionModel.get('listReportId');
            this.actionData.listReportName = actionModel.get('listReportName');

            if (this.actionData.assignmentRule !== 'Least-Busy') {
                this.actionData.listReportId = null;
                this.actionData.listReportName = null;
            }

            if (this.options.flowchartCreatedEntitiesData) {
                this.actionData.target = actionModel.get('target') || null;
            }

            return true;
        },

    });
});
