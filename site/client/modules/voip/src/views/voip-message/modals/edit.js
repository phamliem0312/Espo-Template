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

define('voip:views/voip-message/modals/edit', 'views/modals/edit', function (Dep) {

    return Dep.extend({

        scope: 'VoipMessage',

        saveDisabled: true,

        fullFormDisabled: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.isNew()) {
                this.actionCreateMessage();
            } else {
                this.actionEditMessage();
            }
        },

        actionCreateMessage: function () {
            this.buttonList.unshift({
                name: 'saveDraft',
                text: this.translate('Save Draft', 'labels', 'VoipMessage')
            });

            this.buttonList.unshift({
                name: 'send',
                text: this.translate('Send', 'labels', 'VoipMessage'),
                style: 'danger'
            });

            this.headerHtml = this.getLanguage().translate('Compose Message', 'labels', 'VoipMessage');

            var attributes = this.attributes || {};
            var parentType = attributes.parentType || null;
            if (parentType && parentType == 'Account') {
                this.once('after:render', function () {
                    if (this.hasParentView() && this.getParentView().model.name == 'Contact') {
                        this.model.set('to', null);
                        this.model.set('parentType', 'Contact');
                        this.model.set('parentId', this.getParentView().model.get('id'));
                        this.model.set('parentName', this.getParentView().model.get('name'));
                    }
                }, this);
            }
        },

        actionEditMessage: function () {
            this.buttonList.unshift({
                name: 'fullForm',
                label: 'Full Form'
            });

            this.buttonList.unshift({
                name: 'save',
                label: 'Save',
                style: 'primary',
            });
        },

        actionSend: function () {
            var dialog = this.dialog;

            var editView = this.getView('edit');

            var model = editView.model;

            var afterSend = function () {
                this.trigger('after:save', model);
                this.trigger('after:send', model);
                dialog.close();
            };

            editView.once('after:send', afterSend, this);

            this.disableButton('send');
            this.disableButton('saveDraft');

            editView.once('cancel:save', function () {
                this.enableButton('send');
                this.enableButton('saveDraft');

                editView.off('after:save', afterSend);
            }, this);

            editView.send();
        },

        actionSaveDraft: function () {
            var dialog = this.dialog;

            var editView = this.getView('edit');

            var model = editView.model;

            this.disableButton('send');
            this.disableButton('saveDraft');

            var afterSave = function () {
                this.enableButton('send');
                this.enableButton('saveDraft');
                Espo.Ui.success(this.translate('savedAsDraft', 'messages', 'VoipMessage'));

                this.trigger('after:save', model);

                this.$el.find('button[data-name="cancel"]').html(this.translate('Close'));
            }.bind(this);

            editView.once('after:save', afterSave , this);

            editView.once('cancel:save', function () {
                this.enableButton('send');
                this.enableButton('saveDraft');

                editView.off('after:save', afterSave);
            }, this);

            editView.saveDraft();
        },

    });
});
