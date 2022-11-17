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

Espo.define('advanced:bpmn-element-helper', ['view'], function (Fake) {

    var Helper = function (viewHelper, model) {
        this.viewHelper = viewHelper;
        this.model = model;
    }

    _.extend(Helper.prototype, {

        getTargetCreatedList: function () {
            var flowchartCreatedEntitiesData = this.model.flowchartCreatedEntitiesData;

            var itemList = [];
            if (flowchartCreatedEntitiesData) {
                Object.keys(flowchartCreatedEntitiesData).forEach(function (aliasId) {
                    var entityType = flowchartCreatedEntitiesData[aliasId].entityType;
                    itemList.push('created:' + aliasId);
                }, this);
            }
            return itemList;
        },

        getTargetLinkList: function (level, allowHasMany, skipParent) {
            var entityType = this.model.targetEntityType;

            var itemList = [];

            var linkList = [];

            var linkDefs = this.viewHelper.metadata.get(['entityDefs', entityType, 'links']) || {};
            Object.keys(linkDefs).forEach(function (link) {
                var type = linkDefs[link].type;
                if (linkDefs[link].disabled) return;

                if (skipParent && type === 'belongsToParent') return;

                if (!level || level === 1) {
                    if (!allowHasMany) {
                        if (!~['belongsTo', 'belongsToParent'].indexOf(type)) return;
                    } else {
                        if (!~['belongsTo', 'belongsToParent', 'hasMany'].indexOf(type)) return;
                    }
                } else {
                    if (!~['belongsTo', 'belongsToParent'].indexOf(type)) return;
                }

                var item = 'link:' + link;

                itemList.push(item);

                linkList.push(link);
            }, this);

            if (level === 2) {
                linkList.forEach(function (link) {
                    var entityType = linkDefs[link].entity;
                    if (entityType) {
                        var subLinkDefs = this.viewHelper.metadata.get(['entityDefs', entityType, 'links']) || {};
                        Object.keys(subLinkDefs).forEach(function (subLink) {
                            var type = subLinkDefs[subLink].type;
                            if (subLinkDefs[subLink].disabled) return;
                            if (skipParent && type === 'belongsToParent') return;
                            if (!allowHasMany) {
                                if (!~['belongsTo', 'belongsToParent'].indexOf(type)) return;
                            } else {
                                if (!~['belongsTo', 'belongsToParent', 'hasMany'].indexOf(type)) return;
                            }
                            var item = 'link:' + link + '.' + subLink;
                            itemList.push(item);
                        }, this);
                    }
                }, this);
            }

            return itemList;
        },

        translateTargetItem: function (target) {
            if (target && target.indexOf('created:') === 0) {
                return this.translateCreatedEntityAlias(target)
            }

            var delimiter = '.';

            var entityType = this.model.targetEntityType;

            if (target && target.indexOf('link:') === 0) {
                var linkPath = target.substr(5);
                var linkList = linkPath.split('.');

                var labelList = [];

                linkList.forEach(function (link) {
                    labelList.push(this.viewHelper.language.translate(link, 'links', entityType));
                    if (!entityType) return;
                    entityType = this.viewHelper.metadata.get(['entityDefs', entityType, 'links', link, 'entity']);
                }, this);

                return this.viewHelper.language.translate('Related', 'labels', 'Workflow') + ': ' + labelList.join(delimiter);
            }

            if (target === 'currentUser') {
                return this.viewHelper.language.translate('currentUser', 'emailAddressOptions', 'Workflow');
            }
            if (target === 'targetEntity' || !target) {
                return this.getLanguage().translate('targetEntity', 'emailAddressOptions', 'Workflow') +
                    ' (' + this.viewHelper.language.translate(entityType, 'scopeName') + ')';
            }
            if (target === 'followers') {
                return this.viewHelper.language.translate('followers', 'emailAddressOptions', 'Workflow');
            }
        },

        translateCreatedEntityAlias: function (target) {
            var aliasId = target;
            if (target.indexOf('created:') === 0) {
                aliasId = target.substr(8);
            }
            if (!this.model.flowchartCreatedEntitiesData || !this.model.flowchartCreatedEntitiesData[aliasId]) {
                return target;
            }

            var link = this.model.flowchartCreatedEntitiesData[aliasId].link;
            var entityType = this.model.flowchartCreatedEntitiesData[aliasId].entityType;
            var numberId = this.model.flowchartCreatedEntitiesData[aliasId].numberId;

            var label = this.viewHelper.language.translate('Created', 'labels', 'Workflow') + ': ';

            var delimiter = ' - ';

            if (link) {
                label += this.viewHelper.language.translate(link, 'links', this.entityType) + ' ' + delimiter + ' ';
            }
            label += this.viewHelper.language.translate(entityType, 'scopeNames');
            if (numberId) {
                label += ' #' + numberId.toString();
            }

            return label;
        },

        getEntityTypeFromTarget: function (target) {
            if (target && target.indexOf('created:') === 0) {
                var aliasId;
                aliasId = target.substr(8);
                if (!this.model.flowchartCreatedEntitiesData || !this.model.flowchartCreatedEntitiesData[aliasId]) return null;
                return this.model.flowchartCreatedEntitiesData[aliasId].entityType;
            }

            var targetEntityType = this.model.targetEntityType;

            if (target && target.indexOf('link:') === 0) {
                var linkPath = target.substr(5);
                var linkList = linkPath.split('.');

                var entityType = targetEntityType;

                linkList.forEach(function (link) {
                    if (!entityType) return;
                    entityType = this.viewHelper.metadata.get(['entityDefs', entityType, 'links', link, 'entity']);
                }, this);

                return entityType;
            }

            if (target === 'followers') return 'User';
            if (target === 'currentUser') return 'User';
            if (target === 'targetEntity') return targetEntityType;
            if (!target) return targetEntityType;

            return null;
        },

    });

    return Helper;
});
