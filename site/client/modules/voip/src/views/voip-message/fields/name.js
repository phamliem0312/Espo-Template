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

Espo.define('voip:views/voip-message/fields/name', 'views/fields/varchar', function (Dep) {

    return Dep.extend({
        listLinkTemplate: 'voip:voip-message/fields/name/list-link',

        data: function () {
            var data = Dep.prototype.data.call(this);

            data.isRead = (this.model.get('sentById') === this.getUser().id) || this.model.get('isRead');
            data.isImportant = this.model.has('isImportant') && this.model.get('isImportant');

            if (!data.isRead && !this.model.has('isRead')) {
                data.isRead = true;
            }

            return data;
        },

        getValueForDisplay: function () {
            return this.model.get('name');
        },

        getAttributeList: function () {
            return ['name', 'isRead', 'isImportant'];
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'change', function () {
                if (this.mode == 'list' || this.mode == 'listLink') {
                    if (this.model.hasChanged('isRead') || this.model.hasChanged('isImportant')) {
                        this.reRender();
                    }
                }
            }, this);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
        },

    });

});
