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

Espo.define('voip:views/user/record/detail', 'views/user/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.buttonList = _.clone(this.buttonList);

            if ( !this.getUser().isPortal() && (this.model.id == this.getUser().id || this.getUser().isAdmin()) ) {
                this.buttonList.push({
                    name: 'voipSettings',
                    label: 'Voip Settings',
                    style: 'default'
                });
            }
        },

        actionVoipSettings: function () {
            this.notify('Loading...');

            this.createView('voipSettings', 'voip:views/modals/user-settings', {
                model: this.model
            }, function (view) {
                view.render();
                this.notify(false);

                this.listenToOnce(view, 'changed', function () {
                    setTimeout(function () {
                        this.render();
                    }.bind(this), 100);
                }, this);

            }.bind(this));
        }

    });

});
