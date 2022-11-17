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

define('advanced:views/bpmn-flowchart-element/fields/signal', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            this.params.options = [
                'create.ENTITY_TYPE',
                'update.ENTITY_TYPE.ID',
                'delete.ENTITY_TYPE.ID',
                'relate.ENTITY_TYPE.ID.LINK_NAME',
                'relate.ENTITY_TYPE.ID.LINK_NAME.FOREIGN_ID',
                'unrelate.ENTITY_TYPE.ID.LINK_NAME',
                'unrelate.ENTITY_TYPE.ID.LINK_NAME.FOREIGN_ID',
            ];
        },

    });
});