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

Espo.define('voip:views/modals/user-settings', ['views/modal', 'views/record/base', 'view-record-helper'], function (Dep, Record, ViewRecordHelper) {

    return Dep.extend({

        cssName: 'voip-user-settings',

        template: 'voip:modals/user-settings',
        mode: 'edit',
        dependencyDefs: {},

        userFieldList: [
            "voipConnector",
            "voipUser",
            "voipPassword",
            "voipNotifications",
            "voipMute",
            "voipInternalCall",
            "voipContext",
            "voipTestConnection"
        ],

        userFieldsDefs: {},

        data: function () {
            return {
                "userFieldsDefs": this.userFieldsDefs
            };
        },

        setup: function () {
            this.buttons = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                    onClick: function (dialog) {
                        this.changeSettings();
                    }.bind(this)
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: function (dialog) {
                        dialog.close();
                    }
                }
            ];

            this.recordHelper = new ViewRecordHelper();
            this.dependencyDefs = _.extend(this.getMetadata().get('clientDefs.' + this.model.name + '.formDependency') || {}, this.dependencyDefs);

            this.header = this.translate('Voip Settings', 'labels', 'User');

            this.normalizeFieldDefs();

            this.wait(true);

            this.createFieldViews();
            this.wait(false);
            this.initDependancy();
        },

        normalizeFieldDefs: function () {
            this.userFieldList.forEach(function(fieldName) {
                this.userFieldsDefs[fieldName] = this.getMetadata().get('entityDefs.User.fields.' + fieldName) || {};
            }.bind(this));
        },

        createFieldViews: function () {
            Object.keys(this.userFieldsDefs).forEach(function(fieldName) {
                var type = this.userFieldsDefs[fieldName].type;
                var viewName = this.userFieldsDefs[fieldName].view || 'views/fields/' + type;

                this.createView(fieldName, viewName, {
                    model: this.options.model,
                    mode: 'edit',
                    el: this.options.el + ' .field-' + fieldName,
                    defs: {
                        name: fieldName
                    }
                });
            }.bind(this));
        },

        changeSettings: function () {

            this.$el.find('button[data-name="save"]').addClass('disabled');

            var data = {
                userId: this.model.get('id')
            };

            this.userFieldList.forEach(function(fieldName) {
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
                this.trigger('changed');

                if (data.id) {
                    this.getStorage().set('user', 'user', data);
                }

                this.close();
            }.bind(this));
        },

        initDependancy: function () {
            Record.prototype.initDependancy.call(this);
        },

        _handleDependencyAttributes: function () {
            Record.prototype._handleDependencyAttributes.call(this);
        },

        _handleDependencyAttribute: function (attr) {

            var currentValue = this.model.get(attr);

            switch(attr) {
                case 'voipConnector':
                    if (currentValue) {
                        this.model.attributes[attr] = currentValue.replace(/[0-9]+$/i, '');
                    }
                    break;
            }

            Record.prototype._handleDependencyAttribute.call(this, attr);

            switch(attr) {
                case 'voipConnector':
                    this.model.attributes[attr] = currentValue;
                    break;
            }
        },

        _doDependencyAction: function (data) {
            Record.prototype._doDependencyAction.call(this, data);
        },
        getFieldView: function (name) {
            return this.getView(name) || null;
        },

        showField: function (name) {
            Record.prototype.showField.call(this, name);
        },

        setFieldReadOnly: function (name) {
            Record.prototype.setFieldReadOnly.call(this, name);
        },

        setFieldNotReadOnly: function (name) {
            Record.prototype.setFieldNotReadOnly.call(this, name);
        },

        setFieldRequired: function (name) {
            Record.prototype.setFieldRequired.call(this, name);
        },

        setFieldNotRequired: function (name) {
            Record.prototype.setFieldNotRequired.call(this, name);
        },

        hideField: function (name) {
            Record.prototype.hideField.call(this, name);
        },

    });
});
