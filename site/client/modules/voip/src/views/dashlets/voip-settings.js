/*********************************************************************************
 * The contents of this file are subject to the EspoCRM VoIP Integration
 * Extension Agreement ("License") which can be viewed at
 * https://www.espocrm.com/voip-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2021 Letrium Ltd.
 * 
 * License ID: e36042ded1ed7ba87a149ac5079bd238
 ***********************************************************************************/

Espo.define('voip:views/dashlets/voip-settings', ['views/dashlets/abstract/base', 'model'], function (Dep, Model) {

    return Dep.extend({
        name: 'VoipSettings',

        fieldList: [],

        template: 'voip:dashlets/voip-settings',

        actionRefresh: function () {
           this.listenToOnce(this.model, 'sync', function () {
               this.getView('voipDoNotDisturb').reRender();
               this.getView('voipDoNotDisturbUntil').reRender();
               this.defaultView();
            }, this);
            this.model.fetch({silent: true});
        },

        setup: function () {
            this.model = new Model();
            this.model.scope = 'User';
            this.model.name = 'User';
            this.model.urlRoot = 'User';
            this.model.id = this.getUser().id;
            this.fieldList = [];

            this.model.defs.fields = {
                voipDoNotDisturb: {
                    type: 'bool',
                    view: 'voip:views/fields/switch'
                },
                voipDoNotDisturbUntil: {
                    type: 'datetime'
                },
            };
            this.wait(true);
            this.listenToOnce(this.model, 'sync', function () {
                this.wait(false);
            }, this);
            for (i in this.model.defs.fields) {
                var field = this.model.defs.fields[i];
                var view = field.view || this.getFieldManager().getViewName(field.type) || null;
                this.createFieldView(i, view, []);
            }
            this.model.fetch();
        },

        defaultView: function () {
            if (this.model.get('voipDoNotDisturb')) {
                this.show('voipDoNotDisturbUntil');
            } else {
                this.hide('voipDoNotDisturbUntil');
            }
        },

        afterRender: function () {
            this.defaultView();
            this.listenTo(this.model, 'change:voipDoNotDisturb', function () {
                this.defaultView();
                this.save();
            }, this);

            this.listenTo(this.model, 'change:voipDoNotDisturbUntil', function () {
                this.save();
            }, this);
        },

        createFieldView: function (name, view, params) {
            var o = {
                model: this.model,
                mode: 'edit',
                el: this.options.el + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    params: params || {}
                }
            };
            this.createView(name, view, o);

            if (!~this.fieldList.indexOf(name)) {
                this.fieldList.push(name);
            }
        },

        save: function () {
            this.notify('Saving...');

            var data = {
                userId: this.model.id
            };

            this.fieldList.forEach(function(fieldName) {
                data[fieldName] = this.model.get(fieldName);
            }.bind(this));

            $.ajax({
                url: 'Voip/action/changeUserSettings',
                type: 'POST',
                data: JSON.stringify(data),
                error: function () {
                    this.$el.find('button[data-name="save"]').removeClass('disabled');
                }.bind(this)
            }).done(function (data) {
                Espo.Ui.success(this.translate('Saved'));
                this.defaultView();
            }.bind(this));
        },

        hide: function (field) {
            if (!this.$el.find('.cell[data-name="' + field+'"]').hasClass('hidden')) {
                this.$el.find('.cell[data-name="' + field+'"]').addClass('hidden');
            }
        },

        show: function (field) {
            if (this.$el.find('.cell[data-name="' + field+'"]').hasClass('hidden')) {
                this.$el.find('.cell[data-name="' + field+'"]').removeClass('hidden');
            }
        },
    });
});
