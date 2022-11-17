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

Espo.define('voip:views/admin/integrations/asterisk', 'voip:views/admin/integrations/base', function (Dep) {

    return Dep.extend({

        testConnectionButton: true,

        webhookUrl: '/api/v1/Voip/webhook/{CONNECTOR}/{ACCESS_KEY}?user=USER_EXTENSION&number=PHONE_NUMBER&uniqueid=ASTERISK_UNIQUEID',

        cidLookupUrl: '/api/v1/Voip/webhook/{CONNECTOR}/{ACCESS_KEY}?type=cidlookup&number=[NUMBER]',

        dependencyFields: {
            'playRecordings': [
                'recordingUrl',
                'useOutgoingCallRecording',
            ],
            'useOutgoingCallRecording': [
                'outgoingCallRecordingUrl'
            ]
        },

        renderVoipUri: function () {
            Dep.prototype.renderVoipUri.call(this);
            var url = this.getVoipUri(this.cidLookupUrl);
            this.replaceHelpText('{voipCidlookupUrl}', url);
        },

    });

});
