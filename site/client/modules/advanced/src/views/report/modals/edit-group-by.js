/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

define('advanced:views/report/modals/edit-group-by', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:report/modals/edit-group-by',

        data: function () {
            return {

            };
        },

        setup: function () {
            this.buttonList = [
                {
                    name: 'apply',
                    label: 'Apply',
                    style: 'danger',
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: function (dialog) {
                        dialog.close();
                    }
                }
            ];

            var v1 = this.options.value[0] ?? '';
            var v2 = this.options.value[1] ?? '';

            v1 = v1.replace(/\t/g, '\r\n');
            v2 = v2.replace(/\t/g, '\r\n');

            this.headerHtml = this.translate('groupBy', 'fields', 'Report');

            this.once('close', function () {
                if (this.$entityType) {
                    this.$entityType.popover('destroy');
                }
            }, this);

            var m = new Model();

            m.set({
                v1: v1,
                v2: v2,
            });

            this.createView('v1', 'views/fields/formula', {
                model: m,
                name: 'v1',
                el: this.getSelector() + ' .v1-container',
                mode: 'edit',
                insertDisabled: true,
                height: 50,
            });
            this.createView('v2', 'views/fields/formula', {
                model: m,
                name: 'v2',
                el: this.getSelector() + ' .v2-container',
                mode: 'edit',
                insertDisabled: true,
                height: 50,
            });
        },

        actionApply: function () {
            var value = [];


            var v1 = this.getView('v1').fetch()['v1'] || '';
            var v2 = this.getView('v2').fetch()['v2'] || '';

            v1 = v1.replace(/(?:\r\n|\r|\n)/g, '\t');
            v2 = v2.replace(/(?:\r\n|\r|\n)/g, '\t');

            if (v1) value.push(v1);
            if (v2) value.push(v2);

            this.trigger('apply', value);

            this.remove();
        },

    });
});
