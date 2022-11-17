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

Espo.define('voip:views/voip-message/fields/from', 'views/fields/enum', function (Dep) {

    return Dep.extend({
        listTemplate: 'fields/base/detail',

        detailTemplate: 'fields/base/detail',

        setup: function () {
           this.initOptions();
        },

        getFromNumberList: function () {
            let additionalPhoneNumbers = this.getUser().get('voipAdditionalNumbers') || null;
            let type = this.model.get('type') || 'sms';

            if (!additionalPhoneNumbers) {
                let text = this.getLanguage().translate('noSmsPhone', 'messages', 'VoipMessage');

                setTimeout(function () {
                    Espo.Ui.warning(text);
                }, 500);

                return [];
            }

            if (typeof additionalPhoneNumbers[type][1] !== 'undefined') {
                return Object.values(additionalPhoneNumbers[type]);
            }

            return additionalPhoneNumbers[type] || [];
        },

        initOptions: function () {
            if (this.model.get('status') == 'draft' && this.model.get('direction') == 'outgoing') {
                let fromNumbers = this.getFromNumberList();
                let value = this.model.get(this.name);

                if (value && !~fromNumbers.indexOf(value)) {
                    this.model.set(this.name, null);
                }

                if (fromNumbers) {
                    let fromNumbersObj = {};
                    for (i in fromNumbers) {
                        fromNumbersObj[fromNumbers[i]] = fromNumbers[i];
                    }

                    this.translatedOptions = fromNumbersObj;
                    this.params.isSorted = true;
                    this.params.options = fromNumbers;

                    this.params.options = this.params.options.sort(function (v1, v2) {
                        return (this.translatedOptions[v1] || v1).localeCompare(this.translatedOptions[v2] || v2);
                    }.bind(this));
                }
            }
        },

        fetch: function () {
            let value = this.$el.find('[data-name="' + this.name + '"]').val();
            let data = {};

            data[this.name] = value;
            return data;
        },

        getValueForDisplay: function () {
            return this.model.get(this.name);
        },

    });

});
