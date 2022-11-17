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

Espo.define('advanced:views/workflow/action-modals/base', ['views/modal', 'advanced:views/workflow/actions/base'], function (Dep, ActionBase) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/base',

        data: function () {
            return {};
        },

        setup: function () {
            this.actionData = this.options.actionData || {};

            this.actionDataInitial = Espo.Utils.cloneDeep(this.actionData);

            this.actionType = this.options.actionType;
            this.entityType = this.options.entityType;

            this.once('close', function () {
                if (!this.isApplied) {
                    if (this.actionDataInitial && this.actionData) {
                        for (var i in this.actionDataInitial) {
                            this.actionData[i] = this.actionDataInitial[i];
                        }
                    }
                }
                this.isApplied = false;
            }, this);

            this.buttonList = [
                {
                    name: 'apply',
                    label: 'Apply',
                    style: 'primary',
                    onClick: function (dialog) {
                        if (this.fetch()) {
                            this.isApplied = true;
                            this.trigger('apply', this.actionData);
                            this.close();
                        }
                    }.bind(this),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: function (dialog) {

                        this.trigger('cancel');
                        dialog.close();
                    }.bind(this)
                }
            ];

            this.header = this.translate(this.actionType, 'actionTypes', 'Workflow');
        },

        translateCreatedEntityAlias: function (target, optionItem) {
            return ActionBase.prototype.translateCreatedEntityAlias.call(this, target, optionItem);
        },

        getEntityTypeFromTarget: function (target, targetEntityType) {
            if (target && target.indexOf('created:') === 0) {
                var aliasId;
                aliasId = target.substr(8);
                if (!this.options.flowchartCreatedEntitiesData[aliasId]) return null;
                return this.options.flowchartCreatedEntitiesData[aliasId].entityType;
            }

            if (target && target.indexOf('link:') === 0) {
                var linkPath = target.substr(5);
                var linkList = linkPath.split('.');

                var entityType = targetEntityType || this.entityType;

                linkList.forEach(function (link) {
                    if (!entityType) return;
                    entityType = this.getMetadata().get(['entityDefs', entityType, 'links', link, 'entity']);
                }, this);

                return entityType;
            }

            var entityType = targetEntityType || this.entityType;

            if (target === 'followers') return 'User';
            if (target === 'currentUser') return 'User';
            if (target === 'targetEntity') return entityType;
            if (!target) return entityType;

            return null;
        },

        translateTargetItem: function (target, optionItem, targetEntityType) {
            return ActionBase.prototype.translateTargetItem.call(this, target, optionItem, targetEntityType);
        },
    });
});
