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

Espo.define('advanced:views/workflow/action-fields/shift-days', 'view', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/action-fields/shift-days',

        data: function () {
            return {
                shiftDaysOperator: this.shiftDaysOperator,
                value: this.value,
                unitValue: this.options.unitValue,
                readOnly: this.readOnly
            };
        },

        setup: function () {
            this.value = this.options.value;
            this.readOnly = this.options.readOnly;

            if (this.value < 0) {
                this.shiftDaysOperator = 'minus';
                this.value = (-1) * this.value;
            } else {
                this.shiftDaysOperator = 'plus';
            }
        },

    });
});

