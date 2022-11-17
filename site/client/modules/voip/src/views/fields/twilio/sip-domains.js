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

Espo.define('voip:views/fields/twilio/sip-domains', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:enabled', function (model) {
                if (this.model.get('enabled')) {
                    this.loadList(true);
                }
            }.bind(this));

            this.listenTo(this.model, 'change:twilioAccountSid', function (model) {
                this.loadList(true);
            }.bind(this));

            this.listenTo(this.model, 'change:twilioAuthToken', function (model) {
                this.loadList(true);
            }.bind(this));

            this.loadList();
        },

        loadList: function (useAccountSid) {
            if (!this.model.get('enabled')) {
                return;
            }

            var postData = {
                connector: this.model.id
            };

            if (!this.model.get('twilioApplicationSid') || useAccountSid) {
                if (!this.model.get('twilioAccountSid') || !this.model.get('twilioAuthToken')) {
                    return;
                }

                postData.twilioAccountSid = this.model.get('twilioAccountSid');
                postData.twilioAuthToken = this.model.get('twilioAuthToken');
            }

            //this.notify(this.translate('Loading SIP Domains', 'labels', 'Integration'));

            this.translatedOptions = {};
            this.params.options = [];

            var jqxhr = $.ajax({
                type : 'POST',
                contentType : 'application/json',
                dataType: 'json',
                url: 'Voip/action/twilioSipDomainList',
                data: JSON.stringify(postData)
            }).done(function (data) {
                var enumData = {};

                if (data) {
                    Object.keys(data).forEach(function(keyName) {
                        var sipDomainId = data[keyName].sid;
                        var sipDomainName = data[keyName].domainName;
                        enumData[sipDomainId] = sipDomainName;
                    }.bind(this));

                    this.translatedOptions = enumData;
                    this.params.options = Object.keys(enumData);
                }

                this.reRender();
                this.notify(false);
            }.bind(this));

            jqxhr.fail(function(xhr) {
                xhr.errorIsHandled = true;
                this.reRender();

                //this.notify(this.translate('errorLoadingSipDomains', 'labels', 'Integration'), 'error', 3000);
            }.bind(this));
        },

    });

});
