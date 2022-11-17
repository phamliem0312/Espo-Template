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

Espo.define('voip:views/voip-message/fields/to', 'views/fields/enum', function (Dep) {

    return Dep.extend({
        listTemplate: 'fields/base/detail',

        detailTemplate: 'fields/base/detail',

        setup: function () {
        },

        normalizePhoneNumber: function(phoneNumber) {
            if (phoneNumber) {
                prefix = '';
                if (phoneNumber.substr(0, 1) == '+') {
                    prefix = '+';
                    phoneNumber = phoneNumber.substr(1);
                }

                return prefix + phoneNumber.replace(/[^\d]/g, "");
            }
        },

        loadPhoneNumbers: function() {
            if (this.model.get('parentId') || false) {
                $.ajax({
                    type: 'GET',
                    async: false,
                    url: this.model.get('parentType') + '/' + this.model.get('parentId'),
                    error: function (xhr) {
                        xhr.errorIsHandled = true;
                    },
                }).done(function (model) {
                    this.setPhoneNumberData(model);
                }.bind(this));
            } else {
                this.setEmpty();
            }
        },

        setPhoneNumberData: function (model) {
            this.params.options = [];
            this.translatedOptions = {};

            var phoneNumberData = model['phoneNumberData'] || {};

            phoneNumberData.forEach(function (phoneData) {
                var phoneNumber = phoneData.phoneNumber || false;
                if (phoneNumber) {
                    var normalizedPhoneNumber = this.normalizePhoneNumber(phoneNumber);
                    this.params.options.push(normalizedPhoneNumber);
                    this.translatedOptions[normalizedPhoneNumber] = phoneNumber;
                }
            }.bind(this));

            var currentValue = model['phoneNumber'] || null;
            if (this.model.get('to') && ~this.params.options.indexOf(this.model.get('to'))) {
                currentValue = this.model.get('to');
            }

            var normalizedValue = this.normalizePhoneNumber(currentValue);

            if (normalizedValue) {
                this.model.set('to', normalizedValue);
            } else {
                this.setEmpty();
            }
        },

        setEmpty: function() {
            this.params.options = [''];
            this.translatedOptions = {
                '': this.translate('None')
            };
            this.model.set('to', '');
        },

    });

});
