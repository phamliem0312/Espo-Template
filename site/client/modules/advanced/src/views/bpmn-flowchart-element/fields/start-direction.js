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

Espo.define('advanced:views/bpmn-flowchart-element/fields/start-direction', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.isNotEmpty = true;
            return data;
        },

        getValueForDisplay: function () {
            var value = Dep.prototype.getValueForDisplay.call(this);
            if (!value) {
                value = '';
            }
            return value;
        },

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);
            if (data[this.name] === '') {
                data[this.name] = null;
            }
            return data;
        }
    });

});