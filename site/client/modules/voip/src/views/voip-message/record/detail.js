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

Espo.define('voip:views/voip-message/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        duplicateAction: false,

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.has('isRead') && !this.model.get('isRead')) {
                this.model.set('isRead', true);
            }
            this.listenTo(this.model, 'sync', function () {
                if (!this.model.get('isRead')) {
                    this.model.set('isRead', true);
                }
            }, this);
        },

        afterRender: function() {
            Dep.prototype.afterRender.call(this);

            if (this.model.get('status') == 'draft') {
                this.toFillEnum();
            }

            if (this.model.get('status') == 'queued') {
                this.setFieldReadOnly('body');
                this.setFieldReadOnly('parent');
                this.setFieldReadOnly('from');
                this.setFieldReadOnly('to');
                this.setFieldReadOnly('attachments');
                this.setFieldReadOnly('type');
            }
            this.listenTo(this.model, 'change:parentId', function () {
                this.toFillEnum();
            }.bind(this));

            this.listenTo(this.model, 'change:type', function () {
                var fromView = this.getFieldView('from');
                if (fromView.mode == 'edit') {
                    fromView.initOptions();
                    fromView.reRender();
                }
            }, this);
        },

        send: function () {
            //var model = this.model;
            this.model.set('status', 'queued');

            var afterSend = function () {
                Espo.Ui.success(this.translate('messageSent', 'messages', 'VoipMessage'));
                this.trigger('after:send');
            };

            this.once('after:save', afterSend, this);
            this.once('cancel:save', function () {
                this.model.set('status', 'draft');
                this.off('after:save', afterSend);
            }, this);

            this.once('before:save', function () {
                Espo.Ui.notify(this.translate('Sending...', 'labels', 'VoipMessage'));
            }, this);

            this.save();
        },

        toFillEnum: function() {
            var toView = this.getFieldView('to');
            if (toView) {
                toView.loadPhoneNumbers();
                toView.reRender();
            }
        },

    });
});
