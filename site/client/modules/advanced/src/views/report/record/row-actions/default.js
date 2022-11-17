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

Espo.define('advanced:views/report/record/row-actions/default', 'views/record/row-actions/edit-and-remove', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            var actionList = Dep.prototype.getActionList.call(this);

            var version = this.getConfig().get('version') || '';
            var arr = version.split('.');
            if (version === 'dev' || arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) >= 506) {
                actionList.unshift({
                    link: '#Report/show/' + this.model.id,
                    label: 'Show',
                    action: 'show',
                    data: {
                        id: this.model.id
                    }
                });
            }

            return actionList;
        },

    });
});
