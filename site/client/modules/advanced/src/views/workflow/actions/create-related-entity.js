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

Espo.define('advanced:views/workflow/actions/create-related-entity', 'advanced:views/workflow/actions/base', function (Dep) {

    return Dep.extend({

        type: 'createRelatedEntity',

        defaultActionData: {
            link: false,
            fieldList: [],
            fields: {},
        },

        data: function () {
            var data = Dep.prototype.data.call(this);

            if (this.actionData.link) {
                data.linkTranslated = this.translate(this.actionData.link, 'links', this.entityType);
            }
            data.numberId = this.numberId;
            data.aliasId = this.aliasId;
            return data;
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.numberId = null;
            if (this.options.flowcharElementId && this.options.flowchartCreatedEntitiesData) {
                var aliasId = this.options.flowcharElementId + '_' + this.actionData.id;
                if (aliasId in this.options.flowchartCreatedEntitiesData) {
                    this.numberId = this.options.flowchartCreatedEntitiesData[aliasId].numberId;
                }
                this.aliasId = aliasId;
            }
        },

        additionalSetup: function() {
            Dep.prototype.additionalSetup.call(this);

            if (this.actionData.link) {
                this.linkedEntityName = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + this.actionData.link + '.entity');
            }
        }

    });
});

