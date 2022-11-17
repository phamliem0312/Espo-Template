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

Espo.define('advanced:views/workflow/action-modals/update-related-entity',
    ['advanced:views/workflow/action-modals/create-entity', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/update-related-entity',

        permittedLinkTypes: ['belongsTo', 'hasMany', 'hasChildren', 'belongsToParent', 'hasOne'],

        getLinkOptionsHtml: function () {
            var value = this.actionData.link;

            var list = Object.keys(this.getMetadata().get('entityDefs.' + this.entityType + '.links'));

            var html = '<option value="">--' + this.translate('Select') + '--</option>';

            list.forEach(function (item) {
                var defs = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + item);

                if (defs.disabled) return;
                if (~this.permittedLinkTypes.indexOf(defs.type)) {
                    var label = this.translate(item, 'links' , this.entityType);
                    html += '<option value="' + item + '" ' + (item === value ? 'selected' : '') + '>' + label + '</option>';
                }

            }, this);

            return html;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var model = new Model();
            model.name = 'Workflow';

            this.modelForParentEntityType = model;

            this.actionModel = model;

            model.set({
                assignmentRule: this.actionData.assignmentRule,
                targetTeamId: this.actionData.targetTeamId,
                targetTeamName: this.actionData.targetTeamName,
                targetUserPosition: this.actionData.targetUserPosition,
                listReportId: this.actionData.listReportId,
                listReportName: this.actionData.listReportName
            });

            var parentEntityTypeList = [];

            if (this.isParentLink()) {
                parentEntityTypeList = this.getParentEntityTypeList(this.actionData.link);
            }

            if (this.actionData.parentEntityType) {
                model.set('parentEntityType', this.actionData.parentEntityType);
            }

            this.createView('parentEntityType', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.getSelector() + ' .field[data-name="parentEntityType"]',
                defs: {
                    name: 'parentEntityType',
                    params: {
                        options: parentEntityTypeList,
                        translation: 'Global.scopeNames'
                    }
                },
                readOnly: this.readOnly,
            });

            this.listenTo(model, 'change', function (m, o) {
                if (o.ui) {
                    this.onParentEntityTypeChange();
                }
            }, this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$link = this.$el.find('select[name="link"]');

            this.controlParentEntityType();
        },

        controlParentEntityType: function () {
            var $parentEntityTypeCell = this.$el.find('.cell[data-name="parentEntityType"]');
            if (this.isParentLink()) {
                var parentEntityTypeList = this.getParentEntityTypeList(this.actionData.link);
                this.getView('parentEntityType').setOptionList(parentEntityTypeList);
                $parentEntityTypeCell.removeClass('hidden');
            } else {
                $parentEntityTypeCell.addClass('hidden');
            }
        },

        isParentLink: function () {
            if (
                this.actionData.link
                &&
                this.getMetadata().get(['entityDefs', this.entityType, 'links', this.actionData.link, 'type']) === 'belongsToParent'
            ) return true;
            return false;
        },

        setupScope: function (callback) {
            if (this.actionData.link) {

                var scope = null;
                if (this.isParentLink()) {
                    scope = this.actionData.parentEntityType;
                } else {
                    scope = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + this.actionData.link + '.entity');
                }
                this.scope = scope;

                if (scope) {
                    this.wait(true);
                    this.getModelFactory().create(scope, function (model) {
                        this.model = model;

                        (this.actionData.fieldList || []).forEach(function (field) {
                            var attributes = (this.actionData.fields[field] || {}).attributes || {};
                            model.set(attributes, {silent: true});
                        }, this);

                        callback();
                    }, this);
                } else {
                    throw new Error;
                }
            } else {
                this.model = null;
                callback();
            }
        },

        onParentEntityTypeChange: function () {
            this.actionData.fieldList.forEach(function (field) {
                this.$el.find('.field-row[data-field="' + field + '"]').remove();
                this.clearView('field-' + field);
            }, this);
            this.actionData.fieldList = [];
            this.actionData.fields = {};

            this.actionData.parentEntityType = this.modelForParentEntityType.get('parentEntityType');

            this.handleLink();
        },

        changeLinkAction: function (e) {
            var $option = $(e.currentTarget).find('option[value="'+e.currentTarget.value+'"]');

            var value = e.currentTarget.value;

            this.actionData.link = value;

            this.actionData.parentEntityType = null;

            if (this.isParentLink()) {
                if (this.isParentLink()) {
                    var entityList = this.getParentEntityTypeList(this.actionData.link);
                    if (entityList.length) {
                        this.actionData.parentEntityType = entityList[0];
                    }
                }
            }

            this.modelForParentEntityType.set('parentEntityType', this.actionData.parentEntityType);

            this.actionData.fieldList.forEach(function (field) {
                this.$el.find('.field-row[data-field="' + field + '"]').remove();
                this.clearView('field-' + field);
            }, this);
            this.actionData.fieldList = [];
            this.actionData.fields = {};

            this.controlParentEntityType();

            this.handleLink();
        },

        setupFormulaView: function () {
            var model = new Model;
            if (this.hasFormulaAvailable) {
                model.set('formula', this.actionData.formula || null);

                this.createView('formula', 'views/fields/formula', {
                    name: 'formula',
                    model: model,
                    mode: this.readOnly ? 'detail' : 'edit',
                    height: 100,
                    el: this.getSelector() + ' .field[data-name="formula"]',
                    inlineEditDisabled: true,
                    targetEntityType: this.scope
                }, function (view) {
                    view.render();
                }, this);
            }
        },

        getParentEntityTypeList: function(name) {
            return this.getMetadata().get(['entityDefs', this.entityType, 'fields', name, 'entityList']) || this.getAllEntityList() || [];
        },

        getAllEntityList: function() {
            return  Object.keys(this.getMetadata().get('scopes')).filter(function (scope) {
                if (this.getMetadata().get('scopes.' + scope + '.disabled')) return;
                return this.getMetadata().get('scopes.' + scope + '.entity') && this.getMetadata().get('scopes.' + scope + '.object');
            }, this).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));
        }

    });
});
