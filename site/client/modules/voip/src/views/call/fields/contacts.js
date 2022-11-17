/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2018 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

Espo.define('voip:views/call/fields/contacts', 'crm:views/call/fields/contacts', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.events['click [data-action="dial"]'] = function (e) {
                return this.actionDial(e);
            };
        },

        getDetailLinkHtml: function (id, name) {
            var html = Dep.prototype.getDetailLinkHtml.call(this, id, name);

            var key = this.foreignScope + '_' + id;
            var number = null;
            var phoneNumbersMap = this.model.get('phoneNumbersMap') || {};
            if (key in phoneNumbersMap) {
                number = phoneNumbersMap[key];
                var $html = $(html);

                var isModified = false;

                var $a = $html.find('a').filter(function() {
                    return this.href.match(/^tel\:/);
                }).each(function() {
                    if (!this.hasAttribute("data-phone-number")) {
                        $(this).attr('data-phone-number', number);
                        isModified = true;
                    }
                    if (!this.hasAttribute("data-action")) {
                        $(this).attr('data-action', 'dial');
                        isModified = true;
                    }
                });

                if (isModified) {
                    html = '<div>' + $html.html() + '</div>';
                }
            }

            return html;
        },

        /* copied code from voip:views/fields/phone */
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

        /* copied code from voip:views/fields/phone */
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
