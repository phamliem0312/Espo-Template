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

Espo.define('advanced:views/workflow/action-modals/relate-with-entity', ['advanced:views/workflow/action-modals/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/relate-with-entity',

        data: function () {
            var data = {};

            data.linkOptionsHtml = this.getLinkOptionsHtml();

            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupScope();

            this.model = new Model();
            this.model.set('entityId', this.actionData.entityId || null);
            this.model.set('entityName', this.actionData.entityName || null);
        },

        getLinkOptionsHtml: function () {
            var value = this.actionData.link;

            var list = Object.keys(this.getMetadata().get('entityDefs.' + this.entityType + '.links') || []).sort(function (v1, v2) {
                 return this.translate(v1, 'links', this.scope).localeCompare(this.translate(v2, 'links', this.scope));
            }.bind(this));

            var html = '<option value="">--' + this.translate('Select') + '--</option>';

            list.forEach(function (item) {
                var defs = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + item) || {};
                if (defs.disabled) return;
                if ((defs.type != 'hasMany' && defs.type != 'hasChildren')) {
                    return;
                }
                var label = this.translate(item, 'links' , this.entityType);
                html += '<option value="' + item + '" ' + (item === value ? 'selected' : '') + '>' + label + '</option>';
            }, this);

            return html;
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$link = this.$el.find('select[data-name="link"]');

            this.$link.on('change', function () {
                this.actionData.link = this.$link.val();

                if (!this.actionData.link) {
                    this.actionData.link = null;
                }

                this.setupScope();

                if (this.actionData.link) {
                    this.createLinkField();
                }
                this.clearView('link');

                this.model.set({
                    'entityId': null,
                    'entityName': null
                });
            }.bind(this));

            if (this.actionData.link) {
                this.createLinkField();
            }
        },

        createLinkField: function () {
            this.createView('entity', 'views/fields/link', {
                el: this.getSelector() + ' .field[data-name="entity"]',
                foreignScope: this.scope,
                name: 'entity',
                model: this.model,
                mode: 'edit'
            }, function (view) {
                view.render();
            }, this);
        },

        setupScope: function () {
            if (this.actionData.link) {
                var scope = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + this.actionData.link + '.entity');
                this.scope = scope;
                if (!this.scope) {
                    throw new Error;
                }
            } else {
                this.scope = null;
            }
        },

        fetch: function () {
            if (!this.actionData.link) return false;

            this.actionData.entityId = this.model.get('entityId');
            this.actionData.entityName = this.model.get('entityName');

            if (!this.actionData.entityId) return false;

            return true;
        }

    });
});
