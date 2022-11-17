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

Espo.define('voip:views/fields/phone', 'views/fields/phone', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.events['click [data-action="dial"]'] = function (e) {
                return this.actionDial(e);
            };
        },

        /* copy the modified code to voip:views/call/fields/contacts and voip:views/call/fields/leads*/
        actionDial: function (e) {

            if (!this.useVoipInternalCall()) {
                return true;
            }

            if (this.model.has('doNotCall') && this.model.get('doNotCall')) {
                this.notify(this.translate('doNotCallIsOn', 'labels', 'VoipEvent'), 'warning');
                return false;
            }

            if (this.isOptedOutFieldName && this.model.get(this.isOptedOutFieldName)) {
                this.notify(this.translate('optedOutIsOn', 'labels', 'VoipEvent'), 'warning');
                return false;
            }

            var elem = e.currentTarget;
            var phoneNumber = $(elem).attr('data-phone-number');

            var actionData = {
                "phoneNumber": phoneNumber,
                "line": this.model.get('voipLine'),
                "entityName": this.model.name,
                "entityId": this.model.get('id')
            };

            $(elem).prop("disabled", true);
            this.notify(this.translate('Calling', 'labels', 'VoipEvent'));

            var jqxhr = $.ajax({
                type : 'POST',
                contentType : 'application/json',
                timeout: 90000,
                dataType: 'json',
                url: 'VoipEvent/action/Dial',
                data: JSON.stringify(actionData)
            }).done(function (data) {
                this.notify(false);
            }.bind(this));

            jqxhr.always(function() {
                $(elem).prop("disabled", false);
            });

            return false;
        },

        /* copy the modified code to voip:views/call/fields/contacts and voip:views/call/fields/leads*/
        useVoipInternalCall: function() {

            var isDisabled = this.getMetadata().get('app.popupNotifications.voipNotification.disabled') || false;
            if (isDisabled) {
                return false;
            }

            var voipInternalCall = (this.getStorage().get('user', 'user') || {}).voipInternalCall;
            if (typeof voipInternalCall !== 'undefined') {
                return (this.getStorage().get('user', 'user') || {}).voipInternalCall;
            };

            if (typeof this.getUser().get('voipInternalCall') !== 'undefined') {
                return this.getUser().get('voipInternalCall');
            }

            if (typeof this.getMetadata().get('entityDefs.User.fields.voipInternalCall.default') !== 'undefined') {
                return this.getMetadata().get('entityDefs.User.fields.voipInternalCall.default');
            }

            return true;
        },

    });

});
