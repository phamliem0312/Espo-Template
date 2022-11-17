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

Espo.define('advanced:views/workflow/action-modals/make-followed', ['advanced:views/workflow/action-modals/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/make-followed',

        data: function () {
            return _.extend({

            }, Dep.prototype.data.call(this));
        },

        events: {
            'change [name="recipient"]': function (e) {
            this.actionData.recipient = e.currentTarget.value;
                this.handleRecipient();
            },
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.handleRecipient();
        },

        setModel: function () {
            this.model.set({
                usersToMakeToFollowIds: this.actionData.userIdList,
                usersToMakeToFollowNames: this.actionData.userNames,
                whatToFollow: this.actionData.whatToFollow,
                recipient: this.actionData.recipient || 'specifiedUsers',
                specifiedTeamsIds: this.actionData.specifiedTeamsIds,
                specifiedTeamsNames: this.actionData.specifiedTeamsNames
            });
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.actionData.recipient) {
                this.actionData.recipient = 'specifiedUsers';
            }

            if (this.actionData.whatToFollow && this.actionData.whatToFollow !== 'targetEntity' && this.actionData.whatToFollow.indexOf('link:') !== 0) {
                this.actionData.whatToFollow = 'link:' + this.actionData.whatToFollow;
            }

            var model = this.model = new Model();
            model.name = 'Workflow';
            this.setModel();
            this.on('apply-change', function () {
                this.setModel();
            }, this);

            this.setupRecipientOptions();
            this.setupWhatToFollowOptions();

            this.createView('recipient', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="recipient"]',
                defs: {
                    name: 'recipient',
                    params: {
                        options: this.recipientOptionList,
                        required: true,
                        translatedOptions: this.recipientTranslatedOptions
                    }
                },
                readOnly: this.readOnly
            });

            this.createView('whatToFollow', 'views/fields/enum', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="whatToFollow"]',
                defs: {
                    name: 'whatToFollow',
                    params: {
                        options: this.targetOptionList,
                        required: true,
                        translatedOptions: this.targetTranslatedOptions
                    }
                },
                readOnly: this.readOnly
            });

            this.createView('usersToMakeToFollow', 'views/fields/link-multiple', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field[data-name="usersToMakeToFollow"]',
                foreignScope: 'User',
                defs: {
                    name: 'usersToMakeToFollow'
                },
                readOnly: this.readOnly
            });

            this.createView('specifiedTeams', 'views/fields/link-multiple', {
                el: this.options.el + ' .field[data-name="specifiedTeams"]',
                model: model,
                mode: 'edit',
                foreignScope: 'Team',
                defs: {
                    name: 'specifiedTeams'
                },
                readOnly: this.readOnly
            });
        },

        handleRecipient: function () {
            if (this.actionData.recipient == 'specifiedUsers') {
                this.$el.find('.cell[data-name="usersToMakeToFollow"]').removeClass('hidden');
            } else {
                this.$el.find('.cell[data-name="usersToMakeToFollow"]').addClass('hidden');
            }

            if (this.actionData.recipient == 'specifiedTeams') {
                this.$el.find('.cell[data-name="specifiedTeams"]').removeClass('hidden');
            } else {
                this.$el.find('.cell[data-name="specifiedTeams"]').addClass('hidden');
            }
        },

        fetch: function () {
            this.getView('whatToFollow').fetchToModel();
            if (this.getView('whatToFollow').validate()) {
                return;
            }

            this.actionData.userIdList = (this.getView('usersToMakeToFollow').fetch() || {}).usersToMakeToFollowIds;
            this.actionData.userNames = (this.getView('usersToMakeToFollow').fetch() || {}).usersToMakeToFollowNames;

            this.actionData.whatToFollow = (this.getView('whatToFollow').fetch()).whatToFollow;

            this.actionData.recipient = (this.getView('recipient').fetch() || {}).recipient;

            this.actionData.specifiedTeamsIds = [];
            this.actionData.specifiedTeamsNames = {};
            if (this.actionData.recipient === 'specifiedTeams') {
                var specifiedTeamsData = this.getView('specifiedTeams').fetch() || {};
                this.actionData.specifiedTeamsIds = specifiedTeamsData.specifiedTeamsIds;
                this.actionData.specifiedTeamsNames = specifiedTeamsData.specifiedTeamsNames;
            }

            return true;
        },

        translateCreatedEntityAlias: function (target, optionItem) {
            var aliasId = target;
            if (target.indexOf('created:') === 0) {
                aliasId = target.substr(8);
            }
            if (!this.options.flowchartCreatedEntitiesData[aliasId]) {
                return target;
            }
            var link = this.options.flowchartCreatedEntitiesData[aliasId].link;
            var entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
            var numberId = this.options.flowchartCreatedEntitiesData[aliasId].numberId;

            var label = this.translate('Created', 'labels', 'Workflow') + ': ';

            var raquo = '&raquo;';
            if (optionItem) {
                raquo = '-';
            }
            if (link) {
                label += this.translate(link, 'links', this.entityType) + ' ' + raquo + ' ';
            }
            label += this.translate(entityType, 'scopeNames');
            if (numberId) {
                label += ' #' + numberId.toString();
            }
            return label;
        },

        setupWhatToFollowOptions: function () {
            var targetOptionList = [''];

            var translatedOptions = {
                targetEntity: this.translate('Target Entity', 'labels', 'Workflow') + ' (' + this.translate(this.entityType, 'scopeNames') + ')'
            };

            if (this.getMetadata().get('scopes.' + this.entityType + '.stream')) {
                targetOptionList.push('targetEntity');
            }

            var linkDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.links') || {};
            Object.keys(linkDefs).forEach(function (link) {
                var type = linkDefs[link].type;
                if (type !== 'belongsTo' && type !== 'belongsToParent') return;

                if (type === 'belongsTo') {
                    if (!this.getMetadata().get('scopes.' + linkDefs[link].entity + '.stream')) return;
                }
                targetOptionList.push('link:' + link);
                var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.getLanguage().translate(link, 'links', this.entityType);
                translatedOptions['link:' + link] = label;
            }, this);

            translatedOptions[''] = '--' + this.translate('Select') + '--';

            if (this.options.flowchartCreatedEntitiesData) {
                Object.keys(this.options.flowchartCreatedEntitiesData).forEach(function (aliasId) {
                    var entityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
                    if (!this.getMetadata().get(['scopes', entityType, 'stream'])) return;
                    targetOptionList.push('created:' + aliasId);
                    translatedOptions['created:' + aliasId] = this.translateCreatedEntityAlias(aliasId, true);
                }, this);
            }

            this.targetOptionList = targetOptionList;
            this.targetTranslatedOptions = translatedOptions;
        },

        setupRecipientOptions: function () {
            this.recipientOptionList = ['specifiedUsers', 'teamUsers', 'specifiedTeams', 'followers'];

            if (!this.options.flowchartCreatedEntitiesData) {
                this.recipientOptionList.push('currentUser');
            }

            var linkDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.links') || {};
            Object.keys(linkDefs).forEach(function (link) {
                if (linkDefs[link].type == 'belongsTo' || linkDefs[link].type == 'hasMany') {
                    var foreignEntityType = linkDefs[link].entity;
                    if (!foreignEntityType) {
                        return;
                    }
                    if (linkDefs[link].type == 'hasMany') {
                        if (this.getMetadata().get(['entityDefs', this.entityType, 'fields', link, 'type']) !== 'linkMultiple') {
                            return;
                        }
                    }
                    var fieldDefs = this.getMetadata().get('entityDefs.' + foreignEntityType + '.fields');
                    if (foreignEntityType !== 'User') return;
                    this.recipientOptionList.push('link:' + link);
                }
            }, this);

            Object.keys(linkDefs).forEach(function (link) {
                if (linkDefs[link].type != 'belongsTo') return;

                var foreignEntityType = this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'entity']);
                if (!foreignEntityType) return;

                if (foreignEntityType === 'User') return;

                if (this.getMetadata().get(['scopes', foreignEntityType, 'stream'])) {
                    this.recipientOptionList.push('link:' + link + '.followers');
                }

                var subLinkDefs = this.getMetadata().get('entityDefs.' + foreignEntityType + '.links') || {};
                Object.keys(subLinkDefs).forEach(function (subLink) {
                    if (subLinkDefs[subLink].type == 'belongsTo' || subLinkDefs[subLink].type == 'hasMany') {
                        var subForeignEntityType = subLinkDefs[subLink].entity;
                        if (!subForeignEntityType) {
                            return;
                        }
                    }
                    if (subLinkDefs[subLink].type == 'hasMany') {
                        if (this.getMetadata().get(['entityDefs', subForeignEntityType, 'fields', subLink, 'type']) !== 'linkMultiple') {
                            return;
                        }
                    }
                    var fieldDefs = this.getMetadata().get(['entityDefs', subForeignEntityType, 'fields']) || {};
                    if (subForeignEntityType !== 'User') return;
                    this.recipientOptionList.push('link:' + link + '.' + subLink);
                }, this);
            }, this);

            this.recipientTranslatedOptions = {};
            this.recipientOptionList.forEach(function (item) {
                this.recipientTranslatedOptions[item] = this.translateRecipientOption(item);
            }, this);
        },

        translateRecipientOption: function (value) {
            if (value && value.indexOf('link:') === 0) {
                var link = value.substring(5);

                if (~link.indexOf('.')) {
                    var arr = link.split('.');
                    link = arr[0];
                    var subLink = arr[1];

                    if (subLink === 'followers') {
                        return this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links', this.entityType) +
                            '.' + this.translate('Followers');
                    }

                    var relatedEntityType = this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'entity']);

                    return this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links', this.entityType) +
                        '.' + this.translate(subLink, 'links', relatedEntityType);
                } else {
                    return this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links', this.entityType);
                }

                return link;
            }

            var label = this.translate(value, 'emailAddressOptions', 'Workflow');
            if (value == 'targetEntity') {
                label += ' (' + this.entityType + ')';
            }

            return label;
        }

    });
});
