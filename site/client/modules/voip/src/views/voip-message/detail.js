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

Espo.define('voip:views/voip-message/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var status = this.model.get('status');
            if (status == 'draft' || status == 'failed' || this.model.isNew()) {
                this.backedMenu = this.menu;
                this.menu = {
                    'buttons': [
                        {
                           "label": "Send",
                           "action": "send",
                           "style": "danger",
                           "acl": "edit"
                        }
                    ],
                    'dropdown': [],
                    'actions': []
                };
            }
        },

        actionSend: function () {
            var recordView = this.getView('record');
            var $send = this.$el.find('.header-buttons [data-action="send"]');
            $send.addClass('disabled');

            this.listenToOnce(recordView, 'after:send', function () {
                $send.remove();
                this.menu = this.backedMenu;
                if (recordView.mode !== 'detail') {
                    recordView.setDetailMode();
                    recordView.setFieldReadOnly('body');
                    recordView.setFieldReadOnly('parent');
                    recordView.setFieldReadOnly('from');
                    recordView.setFieldReadOnly('to');
                    recordView.setFieldReadOnly('attachments');
                    recordView.setFieldReadOnly('type');
                }
                this.model.trigger('send');
            }, this);

            this.listenToOnce(recordView, 'cancel:save', function () {
                $send.removeClass('disabled');
            }, this);
            recordView.send();
        },

        actionReply: function (data, e) {
            var attributes = {
                //"returnUrl": '#' + this.scope + '/view/' + this.model.get('id'),
                "type": this.model.get('type'),
                "direction": "outgoing",
                "parentType": this.model.get('parentType'),
                "parentName": this.model.get('parentName'),
                "parentId": this.model.get('parentId'),
                "teamsIds": this.model.get('teamsIds'),
                "teamsNames": this.model.get('teamsNames'),
                "assignedUserId": this.getUser().get('id'),
                "assignedUserName": this.getUser().get('name'),
                "repliedId": this.model.get('id'),
                "repliedName": this.model.get('name'),
            };

            switch (this.model.get('direction')) {
                case "incoming":
                    attributes.from = this.model.get('to');
                    attributes.to = this.model.get('from');
                    break;

                default:
                    attributes.from = this.model.get('from');
                    attributes.to = this.model.get('to');
            }

            //cannot use "this.createModalView(attributes);" when Reply from modal detail
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.modalViews.edit') || 'views/modals/edit';
            this.notify('Loading...');
            this.createView('quickCreate', viewName, {
                scope: this.scope,
                attributes: attributes
            }, function (view) {
                view.render();
                view.notify(false);

                this.listenToOnce(view, 'after:save', function () {
                    this.model.trigger('reply');
                }, this);
            }.bind(this));
        },

        actionForward: function (data, e) {
            var attributes = {
                "returnUrl": '#' + this.scope + '/view/' + this.model.get('id'),
                "type": this.model.get('type'),
                "direction": "outgoing",
                "assignedUserId": this.getUser().get('id'),
                "assignedUserName": this.getUser().get('name'),
                "body": this.model.get('body'),
            };

            this.createModalView(attributes);
        },

        createModalView: function (attributes) {
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.modalViews.edit') || 'views/modals/edit';

            this.notify('Loading...');
            this.createView('quickCreate', viewName, {
                scope: this.scope,
                attributes: attributes
            }, function (view) {
                view.render();
                view.notify(false);
            }.bind(this));
        },

    });
});
