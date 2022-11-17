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

define('voip:views/voip-message/modals/detail', ['views/modals/detail', 'voip:views/voip-message/detail'], function (Dep, Detail) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.getAcl().check('VoipMessage', 'edit')) {
                if (this.model.get('status') == 'draft') {
                    this.buttonList.unshift({
                        name: 'send',
                        text: this.translate('Send', 'labels', 'VoipMessage'),
                        style:"danger"
                    });
                } else {
                    this.buttonList.unshift({
                        name: 'reply',
                        text: this.translate('Reply', 'labels', 'VoipMessage'),
                        style:"danger"
                    });
                }
            }
        },

        actionSend: function (data, e) {
            Detail.prototype.actionSend.call(this, {}, e);

            this.listenToOnce(this.model, 'send', function () {
                if (this.dialog) {
                    this.dialog.close();
                }

                if (this.getParentView()) {
                    this.getParentView().trigger('after-send');
                }
            }, this);
        },

        actionReply: function (data, e) {
            Detail.prototype.actionReply.call(this, {}, e);
        },

    });
});
