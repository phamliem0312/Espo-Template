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

Espo.define('advanced:views/workflow/actions/update-related-entity', 'advanced:views/workflow/actions/base', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/actions/update-related-entity',

        type: 'updateRelatedEntity',

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
            if (this.actionData.parentEntityType) {
                data.parentEntityTypeTranslated = this.translate(this.actionData.parentEntityType, 'scopeNames');
            }

            return data;
        },

        additionalSetup: function() {
            Dep.prototype.additionalSetup.call(this);

            if (this.actionData.link) {
                var linkData = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + this.actionData.link);

                this.linkedEntityName = linkData.entity || this.entityType;
                this.displayedLinkedEntityName = null;
                if (linkData.type == 'belongsToParent') {
                    this.linkedEntityName = this.actionData.parentEntityType || this.linkedEntityName;
                    this.displayedLinkedEntityName = this.translate(this.actionData.link, 'links' , this.entityType) + ' &raquo; ' + this.translate(this.actionData.parentEntityType, 'scopeNames');
                }
            }
        }

    });
});

