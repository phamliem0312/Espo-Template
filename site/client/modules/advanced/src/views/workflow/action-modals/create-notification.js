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

Espo.define('advanced:views/workflow/action-modals/create-notification', ['advanced:views/workflow/action-modals/base', 'Model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/create-notification',

        data: function () {
            return _.extend({
                recipientOptions: this.getRecipientOptions(),
                messageTemplateHelpText: this.translate('messageTemplateHelpText', 'messages', 'Workflow').replace(/(?:\r\n|\r|\n)/g, '<br />')
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

        setup: function () {
            Dep.prototype.setup.call(this);


            var model = new Model();
            model.name = 'Workflow';

            model.set({
                recipient: this.actionData.recipient,
                messageTemplate: this.actionData.messageTemplate,
                usersIds: this.actionData.userIdList,
                usersNames: this.actionData.userNames,
                specifiedTeamsIds: this.actionData.specifiedTeamsIds,
                specifiedTeamsNames: this.actionData.specifiedTeamsNames
            });

            this.createView('messageTemplate', 'views/fields/text', {
                el: this.options.el + ' .field-messageTemplate',
                model: model,
                mode: 'edit',
                defs: {
                    name: 'messageTemplate',
                    params: {
                        required: false
                    }
                }
            });

            this.createView('users', 'views/fields/link-multiple', {
                mode: 'edit',
                model: model,
                el: this.options.el + ' .field-users',
                foreignScope: 'User',
                defs: {
                    name: 'users'
                },
                readOnly: this.readOnly
            });

            this.createView('specifiedTeams', 'views/fields/link-multiple', {
                el: this.options.el + ' .field-specifiedTeams',
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
                this.$el.find('.cell-users').removeClass('hidden');
            } else {
                this.$el.find('.cell-users').addClass('hidden');
            }

            if (this.actionData.recipient == 'specifiedTeams') {
                this.$el.find('.cell-specifiedTeams').removeClass('hidden');
            } else {
                this.$el.find('.cell-specifiedTeams').addClass('hidden');
            }
        },

        getRecipientOptions: function () {
            var html = '';

            var value = this.actionData.recipient;

            var arr = ['specifiedUsers', 'teamUsers', 'specifiedTeams', 'followers', 'followersExcludingAssignedUser'];

            if (!this.options.flowchartCreatedEntitiesData) {
                arr.push('currentUser');
            }

            arr.forEach(function (item) {
                var label = this.translate(item, 'emailAddressOptions' , 'Workflow');
                html += '<option value="' + item + '" ' + (item === value ? 'selected' : '') + '>' + label + '</option>';
            }, this);

            html += this.getLinkOptions(value);

            return html;
        },

        getLinkOptions: function (value) {
            var html = '';
            var linkDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.links') || {};
            Object.keys(linkDefs).forEach(function (link) {
                var isSelected = 'link:' + link === value;
                // TODO remove in future
                if (!isSelected) {
                    isSelected = link === value;
                }
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
                    if ('emailAddress' in fieldDefs && fieldDefs.emailAddress.type === 'email') {
                        var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType);
                        html += '<option value="link:' + link + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                    }
                }
            }, this);

            Object.keys(linkDefs).forEach(function (link) {
                if (linkDefs[link].type != 'belongsTo') return;

                var foreignEntityType = this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'entity']);
                if (!foreignEntityType) return;

                if (foreignEntityType === 'User') return;

                if (this.getMetadata().get(['scopes', foreignEntityType, 'stream'])) {
                    var isSelected = 'link:' + link + '.followers' === value;
                    var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType) + '.' + this.translate('Followers');
                    html += '<option value="link:' + link + '.followers" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                }


                var subLinkDefs = this.getMetadata().get('entityDefs.' + foreignEntityType + '.links') || {};
                Object.keys(subLinkDefs).forEach(function (subLink) {
                    var isSelected = 'link:' + link + '.' + subLink === value;

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
                    if ('emailAddress' in fieldDefs && fieldDefs.emailAddress.type === 'email') {
                        var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType) + '.' + this.translate(subLink, 'links' , foreignEntityType);
                        html += '<option value="link:' + link + '.' + subLink + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                    }
                }, this);
            }, this);

            Object.keys(this.getMetadata().get(['entityDefs', this.entityType, 'links']) || {}).forEach(function (link) {
                if (this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'type']) === 'belongsToParent') {
                    var subLink = 'assignedUser';
                    var isSelected = 'link:' + link + '.' + subLink === value;
                    var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType) + '.' + this.translate(subLink, 'links');
                    html += '<option value="link:' + link + '.' + subLink + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';

                    subLink = 'followers';
                    isSelected = 'link:' + link + '.' + subLink === value;
                    label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType) + '.' + this.translate('Followers');
                    html += '<option value="link:' + link + '.' + subLink + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                }
            }, this);

            return html;
        },

        fetch: function () {
            this.actionData.messageTemplate = (this.getView('messageTemplate').fetch() || {}).messageTemplate;

            this.actionData.recipient = this.$el.find('[name="recipient"]').val();
            if (this.actionData.recipient === 'specifiedUsers') {
                var usersData = this.getView('users').fetch() || {};
                this.actionData.userIdList = usersData.usersIds;
                this.actionData.userNames = usersData.usersNames;
            } else {
                this.actionData.userIdList = [];
                this.actionData.userNames = {};
            }

            this.actionData.specifiedTeamsIds = [];
            this.actionData.specifiedTeamsNames = {};
            if (this.actionData.recipient === 'specifiedTeams') {
                var specifiedTeamsData = this.getView('specifiedTeams').fetch() || {};
                this.actionData.specifiedTeamsIds = specifiedTeamsData.specifiedTeamsIds;
                this.actionData.specifiedTeamsNames = specifiedTeamsData.specifiedTeamsNames;
            }

            return true;
        },


    });
});
