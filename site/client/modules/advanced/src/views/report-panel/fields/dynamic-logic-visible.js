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

Espo.define('advanced:views/report-panel/fields/dynamic-logic-visible', 'views/admin/field-manager/fields/dynamic-logic-conditions', function (Dep) {

    return Dep.extend({

        data: function () {
            return {
                value: this.getValueForDisplay()
            };
        },

        getValueForDisplay: function () {
            if (!this.model.get(this.name)) {
                return this.translate('None');
            }
        },

        setupEntityType: function () {
            this.options.scope = this.scope = this.model.get('entityType');

            this.listenTo(this.model, 'change:entityType', function () {
                this.options.scope = this.scope = this.model.get('entityType');
                if (this.scope) {
                    this.createStringView();
                }
            }, this);
        },

        setup: function () {
            this.setupEntityType();
            this.conditionGroup = Espo.Utils.cloneDeep((this.model.get(this.name) || {}).conditionGroup || []);
            this.createStringView();
        }

    });

});
