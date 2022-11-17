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

Espo.define('voip:views/notification/items/short-status', 'views/notification/items/message', function (Dep) {

    return Dep.extend({

        setup: function () {
            var data = this.model.get('data') || {};

            this.style = data.style || 'text-muted';

            this.messageTemplate = this.model.get('message') || data.message || '';

            this.userId = data.userId;

            this.messageData['entityType'] = Espo.Utils.upperCaseFirst((this.translate(data.entityType, 'scopeNames') || '').toLowerCase());

            this.messageData['entity'] = '<a href="#'+data.entityType+'/view/' + data.entityId + '">' + data.entityName + '</a>';

            this.createMessage();
        }

    });
});

