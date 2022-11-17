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

Espo.define('voip:views/voip-message/record/list', 'views/record/list', function (Dep) {

    return Dep.extend({

        rowActionsView: 'voip:views/voip-message/record/row-actions/default',

        massActionList: ['remove', 'massUpdate'],

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addMassAction('markAsNotRead', false, true);
            this.addMassAction('markAsRead', false, true);

            this.listenToOnce(this, 'after-send', function () {
                this.collection.trigger('draft-sent');
                this.collection.fetch();
            }, this);
        },

        massActionMarkAsRead: function () {
            var ids = [];
            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }
            $.ajax({
                url: 'VoipMessage/action/markAsRead',
                type: 'POST',
                data: JSON.stringify({
                    ids: ids
                })
            });

            ids.forEach(function (id) {
                var model = this.collection.get(id);
                if (model) {
                    model.set('isRead', true);
                }
            }, this);
        },

        massActionMarkAsNotRead: function () {
            var ids = [];
            for (var i in this.checkedList) {
                ids.push(this.checkedList[i]);
            }
            $.ajax({
                url: 'VoipMessage/action/markAsNotRead',
                type: 'POST',
                data: JSON.stringify({
                    ids: ids
                })
            });

            ids.forEach(function (id) {
                var model = this.collection.get(id);
                if (model) {
                    model.set('isRead', false);
                }
            }, this);
        },

    });
});
