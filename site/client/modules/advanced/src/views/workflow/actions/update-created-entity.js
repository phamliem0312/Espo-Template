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

Espo.define('advanced:views/workflow/actions/update-created-entity', 'advanced:views/workflow/actions/base', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/actions/update-created-entity',

        type: 'updateCreatedEntity',

        defaultActionData: {
            target: null,
            fieldList: [],
            fields: {}
        },

        data: function () {
            var data = Dep.prototype.data.call(this);

            if (this.actionData.target) {
                var aliasId = this.actionData.target.substr(8);
                if (this.options.flowchartCreatedEntitiesData[aliasId]) {
                    var link = this.options.flowchartCreatedEntitiesData[aliasId].link;
                    var entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
                    var numberId = this.options.flowchartCreatedEntitiesData[aliasId].numberId;
                    var text = this.options.flowchartCreatedEntitiesData[aliasId].text;

                    if (link) {
                        data.linkTranslated = this.translate(link, 'links', this.entityType);
                    }
                    data.entityTypeTranslated = this.translate(entityType, 'scopeNames');

                    data.numberId = numberId;

                    data.text = text;
                }
            }

            return data;
        },

        additionalSetup: function() {
            Dep.prototype.additionalSetup.call(this);

            if (this.actionData.target) {
                var id = this.actionData.target;
                if (id.indexOf('created:') === 0) {
                    id = id.substr(8);
                }

                if (this.options.flowchartCreatedEntitiesData[id]) {
                    this.linkedEntityName = this.options.flowchartCreatedEntitiesData[id].entityType;
                }

            }
        },
    });
});
