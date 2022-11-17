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

Espo.define('voip:views/user/fields/test-connection', 'views/fields/base', function (Dep) {

    return Dep.extend({

        readOnly: true,

        _template: '<button class="btn btn-default" data-action="voipTestConnection">{{translate \'testConnection\' scope=\'Integration\'}}</button>',

        events: {
            'click [data-action="voipTestConnection"]': function () {
                this.testConnection();
            },
        },

        fetch: function () {
            return {};
        },

        testConnection: function () {
            var data = {
                "id": this.model.get('id'),
                "connector": this.model.get('voipConnector'),
                "user": this.model.get('voipUser'),
                "password": this.model.get('voipPassword')
            }

            this.$el.find('button').addClass('disabled');

            this.notify(this.translate('connecting', 'labels', 'Integration'));

            $.ajax({
                url: 'Voip/action/testConnection',
                type: 'POST',
                data: JSON.stringify(data),
                error: function (xhr, status) {
                    var statusReason = xhr.getResponseHeader('X-Status-Reason') || '';
                    statusReason = statusReason.replace(/ $/, '');
                    statusReason = statusReason.replace(/,$/, '');

                    var msg = this.translate('failedConnection', 'labels', 'Integration');
                    if (statusReason) {
                        msg = this.translate('Error') + ': ' + statusReason;
                    }

                    Espo.Ui.error(msg);
                    xhr.errorIsHandled = true;
                    this.$el.find('button').removeClass('disabled');
                }.bind(this)
            }).done(function (data) {
                if (data) {
                    Espo.Ui.success(this.translate('successConnection', 'labels', 'Integration'));
                } else {
                    Espo.Ui.error(this.translate('failedConnection', 'labels', 'Integration'));
                }

                this.$el.find('button').removeClass('disabled');
            }.bind(this));
        },

    });

});
