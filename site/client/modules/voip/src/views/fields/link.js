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

Espo.define('voip:views/fields/link', 'views/fields/link', function (Dep) {

    return Dep.extend({

        editTemplate: 'voip:fields/link/edit',

        data: function () {
            return _.extend({
                createDisabled: this.createDisabled
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addActionHandler('createLink', function () {
                this.create();
            });
        },

        getCreateAttributes: function () {
            return this.createPhoneNumberAttributes();
        },

        create: function () {
            this.notify('Loading...');
            this.createView('quickCreate', 'views/modals/edit', {
                scope: this.foreignScope,
                fullFormDisabled: true,
                attributes: this.createPhoneNumberAttributes()
            }, function (view) {
                view.once('after:render', function () {
                    this.notify(false);
                });
                view.render();

                this.listenToOnce(view, 'leave', function () {
                    view.close();
                });

                this.listenToOnce(view, 'after:save', function (model) {
                    view.close();
                    this.select(model);
                }.bind(this));
            });
        },

        createPhoneNumberAttributes: function () {
            return attributes = {
                "phoneNumber": this.model.get('phoneNumber'),
                "voipEventId": this.model.get('id'),
                "voipUniqueid": this.model.get('uniqueid'),
                "voipLine": this.model.get('lineId')
            };
        }

    });

});
