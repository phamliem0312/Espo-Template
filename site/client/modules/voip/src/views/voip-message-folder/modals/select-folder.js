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

Espo.define('voip:views/voip-message-folder/modals/select-folder', 'views/modal', function (Dep) {

    return Dep.extend({

        cssName: 'select-folder',

        template: 'voip:voip-message-folder/modals/select-folder',

        fitHeight: true,

        data: function () {
            return {
                dashletList: this.dashletList,
            };
        },

        events: {
            'click a[data-action="selectFolder"]': function (e) {
                var id = $(e.currentTarget).data('id');
                var model = this.collection.get(id);
                var name = this.translate('inbox', 'presetFilters', 'Email');
                if (model) {
                    name = model.get('name');
                }
                this.trigger('select', id, name);
                this.close();
            },
        },

        buttonList: [
            {
                name: 'cancel',
                label: 'Cancel'
            }
        ],

        setup: function () {
            this.headerHtml = '';
            this.wait(true);

            this.getCollectionFactory().create('VoipMessage', function (collection) {
                this.collection = collection;
                collection.maxSize = this.getConfig().get('voipMessageFolderMaxCount') || 100;
                collection.data.boolFilterList = ['onlyMy'];
                collection.fetch().then(function () {
                    this.wait(false);
                }.bind(this));

            }, this);
        },
    });
});
