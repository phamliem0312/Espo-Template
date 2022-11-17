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

Espo.define('voip:views/voip-message/record/edit', ['views/record/edit', 'voip:views/voip-message/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        afterRender: function() {
            Dep.prototype.afterRender.call(this);
            if (this.model.get('status') == 'draft') {
                this.toFillEnum();
            }
            this.listenTo(this.model, 'change:parentId', function () {
                this.toFillEnum();
            }.bind(this));

            this.listenTo(this.model, 'change:type', function () {
                var fromView = this.getFieldView('from') || {};
                if (fromView.mode == 'edit') {
                    fromView.initOptions();
                    fromView.reRender();
                }
            }, this);
        },

        send: function () {
            Detail.prototype.send.call(this);
        },

        prepareView: function () {
            Detail.prototype.prepareView.call(this);
        },

        toFillEnum: function() {
            Detail.prototype.toFillEnum.call(this);
        },

        actionCancel: function () {
            if (this.model.get('returnUrl')) {
                var router = this.getRouter();
                router.navigate(this.model.get('returnUrl'), {trigger: true});
                return;
            }

            Dep.prototype.actionCancel.call(this);
        },

        saveDraft: function () {
            var model = this.model;
            model.set('status', 'draft');
            this.save();
        },

    });
});
