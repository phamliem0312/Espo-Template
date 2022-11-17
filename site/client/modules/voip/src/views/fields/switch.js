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

Espo.define('voip:views/fields/switch', ['views/fields/bool', 'lib!bootstrap-switch'], function (Dep, bootstrapSwitch) {

    return Dep.extend({

        type: 'switch',

        listTemplate: 'voip:fields/switch/detail',

        detailTemplate: 'voip:fields/switch/detail',

        editTemplate: 'voip:fields/switch/edit',

        afterRender: function () {
            this.getLabelElement().addClass('hidden');
            $switchEl = this.$el.find('input[name="'+ this.name +'"]');
            $switchEl.bootstrapSwitch({
                labelText: this.translate(this.name, 'fields', this.model.name),
                handleWidth:50,
                labelWidth:200,
                animate: false,
            });
            $switchEl.bootstrapSwitch('state', this.model.get(this.name), false);
            if (this.mode == 'edit') {
                var self = this;
                this.$el.find('input[name="'+this.name+'"]').on('switchChange.bootstrapSwitch', function (event, state) {
                    self.model.set(self.name, state);
                });
            }
        },

        reRender: function () {
            $switchEl.bootstrapSwitch('state', this.model.get(this.name), false);
        },
    });

});
