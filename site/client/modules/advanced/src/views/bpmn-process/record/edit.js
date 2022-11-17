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

define('advanced:views/bpmn-process/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupFlowchartDependency();
        },

        setupFlowchartDependency: function () {
            this.listenTo(this.model, 'change:flowchartId', function (model, value, o) {
                if (!o.ui) return;
                this.model.set({
                    'targetId': null,
                    'targetName': null
                });
                if (!value) {
                    this.model.set('startElementIdList', []);
                }

                this.model.set('name', this.model.get('flowchartName'));
            }, this);

            if (this.model.has('startElementIdList')) {
                this.showField('startElementId');
                this.setStartElementIdList(this.model.get('startElementIdList'));
            } else {
                this.hideField('startElementId');
            }

            this.listenTo(this.model, 'change:startElementIdList', function (model, value, o) {
                this.setStartElementIdList(value);
            }, this);
        },

        setStartElementIdList: function (value) {
            value = value || [];
            this.setFieldOptionList('startElementId', value);

            if (value.length) {
                this.model.set('startElementId', value[0]);
            } else {
                this.model.set('startElementId', null);
            }
            if (value.length > 0) {
                this.showField('startElementId');
            } else {
                this.hideField('startElementId');
            }
        },

    });
});
