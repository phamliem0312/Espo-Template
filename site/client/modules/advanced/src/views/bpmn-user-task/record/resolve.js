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

Espo.define('advanced:views/bpmn-user-task/record/resolve', 'views/record/base', function (Dep) {

    return Dep.extend({

        template: 'advanced:bpmn-user-task/record/resolve',

        setup: function () {
            Dep.prototype.setup.call(this);
        },

        setupBeforeFinal: function () {
            this.dynamicLogicDefs = this.getMetadata().get('clientDefs.' + this.model.name + '.dynamicLogic') || {};
            Dep.prototype.setupBeforeFinal.call(this);
            this.createField('resolution', 'views/fields/enum', {}, 'edit');
            this.createField('resolutionNote', 'views/fields/text', {}, 'edit');

            this.once('after:render', function () {
                if (this.recordHelper.hasFieldOptionList('resolution')) {
                    this.getFieldView('resolution').setOptionList(this.recordHelper.getFieldOptionList('resolution'));
                }
            }, this);
        }
    });
});