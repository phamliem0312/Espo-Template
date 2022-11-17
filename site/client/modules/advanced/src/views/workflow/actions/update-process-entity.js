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

Espo.define('advanced:views/workflow/actions/update-process-entity', 'advanced:views/workflow/actions/base', function (Dep) {

    return Dep.extend({

        type: 'updateProcessEntity',

        defaultActionData: {
            fieldList: [],
            fields: {}
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            data.noEntityName = true;
            return data;
        },

        additionalSetup: function() {
            Dep.prototype.additionalSetup.call(this);

            this.displayedLinkedEntityName = this.translate('BpmnProcess', 'scopeNames');
        }

    });
});

