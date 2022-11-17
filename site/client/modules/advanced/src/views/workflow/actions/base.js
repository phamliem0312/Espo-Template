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

Espo.define('advanced:views/workflow/actions/base', ['view', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/actions/base',

        defaultActionData: {
            execution: {
                type: 'immediately',
                field: false,
                shiftDays: 0,
            }
        },

        data: function () {
            return {
                entityType: this.entityType,
                actionType: this.actionType,
                linkedEntityName: this.linkedEntityName || this.entityType,
                displayedLinkedEntityName: this.displayedLinkedEntityName || this.linkedEntityName || this.entityType,
                actionData: this.actionData,
                readOnly: this.readOnly
            };
        },

        events: {
            'click [data-action="editAction"]': function () {
                this.edit();
            }
        },

        setup: function () {
            this.actionType = this.options.actionType;
            this.id = this.options.id;
            this.readOnly = this.options.readOnly;

            this.actionData = this.options.actionData || {};

            this.hasFormulaAvailable = !!this.getMetadata().get('app.formula.functionList');

            if (this.options.isNew) {
                var cloned = {};
                for (var i in this.defaultActionData) {
                    cloned[i] = Espo.Utils.clone(this.defaultActionData[i]);
                }

                if ('execution' in cloned) {
                    for (var i in cloned.execution) {
                        cloned.execution[i] = Espo.Utils.clone(cloned.execution[i]);
                    }
                }

                this.actionData = _.extend(cloned, this.actionData);
            }

            this.entityType = this.options.entityType;

            this.additionalSetup();
        },

        afterRender: function () {
            this.renderFields();

            this.$formulaField = this.$el.find('.field[data-name="formula"]');

            if (this.hasFormulaAvailable) {
                this.renderFormula();
            }
        },

        renderFormula: function () {
            this.clearView('formula');

            if (this.actionData.formula && this.actionData.formula !== '') {
                this.$formulaField.removeClass('hidden');
                var model = new Model;
                model.set('formula', this.actionData.formula);
                this.createView('formula', 'views/fields/formula', {
                    name: 'formula',
                    model: model,
                    mode: 'detail',
                    height: 100,
                    el: this.getSelector() + ' .field[data-name="formula"]',
                    inlineEditDisabled: true,
                    params: {
                        seeMoreDisabled: true
                    }
                }, function (view) {
                    view.render();
                }, this);
            } else {
                this.clearView('formula');
                this.$formulaField.addClass('hidden');
            }
        },

        edit: function (isNew) {
            this.createView('edit', 'advanced:views/workflow/action-modals/' + Espo.Utils.camelCaseToHyphen(this.actionType), {
                actionData: this.actionData,
                actionType: this.actionType,
                entityType: this.entityType,
                flowchartCreatedEntitiesData: this.options.flowchartCreatedEntitiesData
            }, function (view) {
                view.render();
                if (isNew) {
                    this.listenToOnce(view, 'cancel', function () {
                        setTimeout(function () {
                            this.getParentView().removeAction(this.id);
                        }.bind(this), 200);
                    }, this);
                }

                this.listenToOnce(view, 'apply', function (actionData) {
                    this.clearView('edit');
                    this.actionData = actionData;
                    this.trigger('change');
                    this.additionalSetup();
                    this.afterEdit();
                    setTimeout(function(){
                        this.reRender();
                    }.bind(this), 200);
                }, this);

            }.bind(this));
        },

        afterEdit: function () {

        },

        fetch: function () {
            this.actionData.type = this.type;
            this.actionData.id = this.options.actionId;

            return this.actionData;
        },

        renderFields: function () {
            var self = this;
            var $fieldContainer = this.$el.find('.field-list');

            if (this.actionData.fields) {

                var model = new Model();
                model.name = this.linkedEntityName || this.entityType;

                _.each(this.actionData.fields, function(row, fieldName){

                    model.set(row.attributes);
                    model.defs = {
                        "fields": {
                        },
                        "links": {
                        }
                    }
                    model.defs.fields[fieldName] = this.getMetadata().get('entityDefs.' + model.name + '.fields.' + fieldName);
                    model.defs.links[fieldName] = this.getMetadata().get('entityDefs.' + model.name + '.links.' + fieldName);

                    var metaFieldKey = 'entityDefs.' + model.name + '.fields.' + fieldName;

                    switch (row.subjectType) {
                        case "value":
                            var viewName =  this.getMetadata().get(metaFieldKey + '.view') || this.getFieldManager().getViewName(this.getMetadata().get(metaFieldKey + '.type'));

                            this.createView('subject', viewName, {
                                el: this.options.el + ' .field-container[data-field="' + fieldName + '"]',
                                model: model,
                                defs: {
                                    name: fieldName,
                                    params: {
                                    },
                                },
                                inlineEditDisabled: true,
                                readOnly: true
                            }, function (view) {
                                setTimeout(function(){
                                    view.render();
                                }, 200);
                            });
                            break;

                        case "field":
                        case "today":
                            var fieldType = this.getMetadata().get(metaFieldKey + '.type') || 'base';
                            var type = this.getMetadata().get('entityDefs.Workflow.fieldDefinitions.' + fieldType) || 'base';

                            this.createView('field-' + fieldName, 'advanced:views/workflow/field-definitions/' + Espo.Utils.camelCaseToHyphen(type), {
                                el: this.options.el + ' .field-container[data-field="' + fieldName + '"]',
                                fieldData: row,
                                model: this.model,
                                field: fieldName,
                                entityType: this.entityType,
                                scope: model.name,
                                type: type,
                                fieldType: fieldType,
                                isNew: false,
                                readOnly: true
                            }, function (view) {
                                view.render();
                            });
                            break;
                    }

                }.bind(this))
            }
        },

        additionalSetup: function() {
            if (this.actionData.link) {
                this.linkedEntityName = this.actionData.link;
            }
        },

        translateCreatedEntityAlias: function (target, optionItem) {
            var aliasId = target;
            if (target.indexOf('created:') === 0) {
                aliasId = target.substr(8);
            }
            if (!this.options.flowchartCreatedEntitiesData[aliasId]) {
                return target;
            }
            var link = this.options.flowchartCreatedEntitiesData[aliasId].link;
            var entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
            var numberId = this.options.flowchartCreatedEntitiesData[aliasId].numberId;

            var label = this.translate('Created', 'labels', 'Workflow') + ': ';

            var raquo = '&raquo;';
            if (optionItem) {
                raquo = '-';
            }
            if (link) {
                label += this.translate(link, 'links', this.entityType) + ' ' + raquo + ' ';
            }
            label += this.translate(entityType, 'scopeNames');
            if (numberId) {
                label += ' #' + numberId.toString();
            }
            return label;
        },

        translateTargetItem: function (target, optionItem, targetEntityType) {
            if (target && target.indexOf('created:') === 0) {
                return this.translateCreatedEntityAlias(target, optionItem)
            }

            var raquo = ' &raquo; ';
            if (optionItem) {
                raquo = '.';
            }

            var entityType = targetEntityType || this.entityType;

            if (target && target.indexOf('link:') === 0) {
                var linkPath = target.substr(5);
                var linkList = linkPath.split('.');

                var labelList = [];

                linkList.forEach(function (link) {
                    labelList.push(this.translate(link, 'links', entityType));
                    if (!entityType) return;
                    entityType = this.getMetadata().get(['entityDefs', entityType, 'links', link, 'entity']);
                }, this);

                return this.translate('Related', 'labels', 'Workflow') + ': ' + labelList.join(raquo);
            }

            if (target === 'currentUser') {
                return this.getLanguage().translate('currentUser', 'emailAddressOptions', 'Workflow');
            }
            if (target === 'targetEntity' || !target) {
                return this.getLanguage().translate('targetEntity', 'emailAddressOptions', 'Workflow') +
                    ' (' + this.translate(entityType, 'scopeNames') + ')';
            }
            if (target === 'followers') {
                return this.getLanguage().translate('followers', 'emailAddressOptions', 'Workflow');
            }
        },

    });
});
