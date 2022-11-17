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
    'advanced:views/workflow/action-modals/create-related-entity',
    ['advanced:views/workflow/action-modals/create-entity', 'model'],
    function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/create-related-entity',

        permittedLinkTypes: ['belongsTo', 'hasMany', 'hasChildren'],

        getLinkOptionsHtml: function () {
            var value = this.actionData.link;

            var list = Object.keys(this.getMetadata().get('entityDefs.' + this.entityType + '.links') || []).sort(function (v1, v2) {
                 return this.translate(v1, 'links', this.scope).localeCompare(this.translate(v2, 'links', this.scope));
            }.bind(this));

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

        setupScope: function (callback) {
            if (this.actionData.link) {
                var scope = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + this.actionData.link + '.entity');
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

    });
});
