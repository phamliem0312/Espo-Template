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

Espo.define('advanced:views/workflow/action-modals/run-service', ['advanced:views/workflow/action-modals/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/run-service',

        data: function () {
            return _.extend({

            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var model = new Model();
            model.name = 'Workflow';
            this.actionModel = model;

            if (this.actionData.methodName || false) {
                model.set({
                    methodName: this.actionData.methodName,
                    additionalParameters: this.actionData.additionalParameters,
                    helpText: this.getHelperText(this.actionData.methodName),
                    target: this.actionData.target || 'targetEntity'
                });
            }

            this.controlTargetEntity();
            this.listenTo(model, 'change:target', function () {
                this.actionData.target = model.get('target') || null;
                model.set({
                    methodName: null
                });
                this.controlTargetEntity();

                var methodNameView = this.getView('methodName');
                if (methodNameView) {
                    methodNameView.translatedOptions = this.methodTranslatedOptions;
                    methodNameView.setOptionList(this.methodOptionList);
                }
            }, this);

            this.setupTargetOptions();

            this.createView('target', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="target"]',
                defs: {
                    name: 'target',
                    params: {
                        options: this.targetOptionList
                    }
                },
                readOnly: this.readOnly,
                translatedOptions: this.targetTranslatedOptions
            });

            this.createView('methodName', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="methodName"]',
                defs: {
                    name: 'methodName',
                    params: {
                        options: this.methodOptionList,
                        required: true,
                        translatedOptions: this.methodTranslatedOptions
                    }
                },
                readOnly: this.readOnly
            });

            this.createView('additionalParameters', 'views/fields/formula', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="additionalParameters"]',
                defs: {
                    name: 'additionalParameters'
                },
                readOnly: this.readOnly,
                height: 60,
                insertDisabled: true,
            }, function (view) {
                view.validations = ['json'];

                view.validateJson = function () {
                    var value = this.model.get(this.name);
                    value = value.trim();
                    if (!value) return;

                    try {
                        JSON.parse(value);
                        return false;
                    } catch (e) {}

                    var msg = this.translate('jsonInvalid', 'messages', 'Workflow');
                    this.showValidationMessage(msg, '.ace_editor');
                    return true;
                };
            });

            var helpView = this.createView('helpText', 'advanced:views/workflow/fields/help-text', {
                mode: 'detail',
                model: model,
                el: this.options.el + ' .field[data-name="helpText"]',
                defs: {
                    name: 'helpText'
                },
                readOnly: true
            });

            this.listenTo(model, 'change:methodName', function () {
                var text = this.getHelperText(model.get('methodName')).toString();

                model.set('helpText', text);
            }, this);
        },

        controlTargetEntity: function () {
            this.setupMethodOptionList();
        },

        setupMethodOptionList: function () {
            var methodOptionList = [''];
            var translatedOptions = {};

            var entityType = this.entityType;

            var target = this.actionData.target || 'targetEntity';

            if (target.indexOf('link:') === 0) {
                var link = target.substr(5);
                entityType = this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'entity']);
            } else if (target.indexOf('created:') === 0) {
                var aliasId = target.substr(8);
                entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
            }

            if (entityType) {
                var actionsData = this.getMetadata().get(['entityDefs', 'Workflow', 'serviceActions', entityType]);
                if (actionsData && Array.isArray(actionsData)) {
                    actionsData.forEach(function(methodName) {
                        methodOptionList.push(methodName);
                        translatedOptions[methodName] = this.getLabel(methodName, 'serviceActions');
                    }.bind(this));
                } else if (actionsData) {
                    for (var methodName in actionsData) {
                        methodOptionList.push(methodName);
                        translatedOptions[methodName] = this.getLabel(methodName, 'serviceActions');
                    }
                }
            }

            this.targetEntityType = entityType;

            this.methodOptionList = methodOptionList;
            this.methodTranslatedOptions = translatedOptions;
        },

        setupTargetOptions: function () {
            var targetOptionList = [];

            var translatedOptions = {
                targetEntity: this.translateTargetItem('targetEntity', true)
            };

            targetOptionList.push('targetEntity');

            var linkDefs = this.getMetadata().get(['entityDefs', this.entityType, 'links']) || {};
            Object.keys(linkDefs).forEach(function (link) {
                var type = linkDefs[link].type;
                if (type !== 'belongsTo') return;

                var value = 'link:' + link;
                targetOptionList.push(value);

                translatedOptions[value] = this.translateTargetItem(value, true);
            }, this);

            if (this.options.flowchartCreatedEntitiesData) {
                Object.keys(this.options.flowchartCreatedEntitiesData).forEach(function (aliasId) {
                    var entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;

                    var value = 'created:' + aliasId;
                    targetOptionList.push(value);

                    translatedOptions[value] = this.translateTargetItem(value, true);
                }, this);
            }

            this.targetOptionList = targetOptionList;
            this.targetTranslatedOptions = translatedOptions;
        },

        fetch: function () {
            var actionModel = this.actionModel;

            this.getView('methodName').fetchToModel();
            this.getView('additionalParameters').fetchToModel();

            var isInvalid = false;
            isInvalid = isInvalid || this.getView('methodName').validate();
            isInvalid = isInvalid || this.getView('additionalParameters').validate();

            if (isInvalid) return;

            this.actionData.target = actionModel.get('target') || null;

            this.actionData.targetEntityType = this.targetEntityType;

            this.actionData.methodName = (this.getView('methodName').fetch()).methodName;
            this.actionData.additionalParameters = (this.getView('additionalParameters').fetch()).additionalParameters;

            return true;
        },

        getLabel: function (methodName, category, defaultValue) {
            if (!methodName) {
                return defaultValue;
            }
            var labelName = this.targetEntityType + methodName.charAt(0).toUpperCase() + methodName.slice(1);
            if (this.getLanguage().has(labelName, category, 'Workflow')) {
                return this.translate(labelName, category, 'Workflow');
            }

            if (defaultValue != null && !this.getLanguage().has(methodName, category, 'Workflow')) {
                return defaultValue;
            }

            return this.translate(methodName, category, 'Workflow');
        },

        getHelperText: function (methodName) {
            var label = this.getLabel(methodName, 'serviceActionsHelp', '');

            if (this.getHelper().transfromMarkdownText) {
                label = label.replace(/&quot;/g, '"')
                label = this.getHelper().transfromMarkdownText(label, {});
            }
            return label;
        }

    });
});
