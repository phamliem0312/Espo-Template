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

define('advanced:views/workflow/fields/flowchart', 'views/fields/link', function (Dep) {

    return Dep.extend({

        selectPrimaryFilterName: 'active',

        createDisabled: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.targetEntityType = this.options.targetEntityType;

            this.listenTo(this.model, 'change-target-entity-type', function (targetEntityType) {
                this.targetEntityType = targetEntityType;
            });
        },

        select: function (model) {
            var hash = model.get('elementsDataHash') || {};

            var translation = {};

            (model.get('eventStartAllIdList') || []).forEach(function (id) {
                var item = hash[id];
                if (!item) return;

                var label = item.text || id;
                label = this.translate(item.type, 'elements', 'BpmnFlowchart') + ': ' + label;

                translation[id] = label;
            }, this);

            this.model.set('startElementNames', translation);

            this.model.set('startElementIdList', model.get('eventStartAllIdList'));

            Dep.prototype.select.call(this, model);
        },

        getSelectFilters: function () {
            if (!this.targetEntityType) return;
            return {
                targetType: {
                    type: 'in',
                    value: [this.targetEntityType]
                }
            };
        },
    });
});
