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

define('advanced:views/workflow/action-modals/send-email', ['advanced:views/workflow/action-modals/base', 'Model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/send-email',

        data: function () {
            return _.extend({
                fromOptions: this.getFromOptions(),
                toOptions: this.getToOptions(),
                replyToOptions: this.getReplyToOptions(),
                fromEmailValue: this.actionData.fromEmail,
                toEmailValue: this.actionData.toEmail,
                replyToEmailValue: this.actionData.replyToEmail,
                optOutLink: this.actionData.optOutLink,
            }, Dep.prototype.data.call(this));
        },

        events: {
            'change [name="from"]': function (e) {
                this.actionData.from = e.currentTarget.value;
                this.handleFrom();
            },
            'change [name="to"]': function (e) {
            this.actionData.to = e.currentTarget.value;
                this.handleTo();
            },
            'change [name="replyTo"]': function (e) {
                this.actionData.replyTo = e.currentTarget.value;
                this.handleReplyTo();
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.handleFrom();
            this.handleTo();
            this.handleReplyTo();
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('executionTime', 'advanced:views/workflow/action-fields/execution-time', {
                el: this.options.el + ' .execution-time-container',
                executionData: this.actionData.execution || {},
                entityType: this.entityType
            });

            var model = new Model();

            model.name = 'Workflow';

            model.set({
                emailTemplateId: this.actionData.emailTemplateId,
                emailTemplateName: this.actionData.emailTemplateName,
                doNotStore: this.actionData.doNotStore,
                optOutLink: this.actionData.optOutLink,
            });

            if (this.actionData.toSpecifiedEntityIds) {
                var viewName = 'to' + this.actionData.to.charAt(0).toUpperCase() + this.actionData.to.slice(1);
                model.set(viewName + 'Ids', this.actionData.toSpecifiedEntityIds);
                model.set(viewName + 'Names', this.actionData.toSpecifiedEntityNames);
            }

            this.createView('emailTemplate', 'views/fields/link', {
                el: this.options.el + ' .field-emailTemplate',
                model: model,
                mode: 'edit',
                foreignScope: 'EmailTemplate',
                defs: {
                    name: 'emailTemplate',
                    params: {
                        required: true
                    }
                }
            });

            this.createView('toSpecifiedTeams', 'views/fields/link-multiple', {
                el: this.options.el + ' .toSpecifiedTeams-container .field-toSpecifiedTeams',
                model: model,
                mode: 'edit',
                foreignScope: 'Team',
                defs: {
                    name: 'toSpecifiedTeams'
                }
            });

            this.createView('toSpecifiedUsers', 'views/fields/link-multiple', {
                el: this.options.el + ' .toSpecifiedUsers-container .field-toSpecifiedUsers',
                model: model,
                mode: 'edit',
                foreignScope: 'User',
                defs: {
                    name: 'toSpecifiedUsers'
                }
            });

            this.createView('toSpecifiedContacts', 'views/fields/link-multiple', {
                el: this.options.el + ' .toSpecifiedContacts-container .field-toSpecifiedContacts',
                model: model,
                mode: 'edit',
                foreignScope: 'Contact',
                defs: {
                    name: 'toSpecifiedContacts'
                }
            });

            this.createView('doNotStore', 'views/fields/bool', {
                el: this.options.el + ' .doNotStore-container .field-doNotStore',
                model: model,
                mode: 'edit',
                defs: {
                    name: 'doNotStore'
                }
            });

            this.createView('optOutLink', 'views/fields/bool', {
                el: this.options.el + ' .field[data-name="optOutLink"]',
                model: model,
                mode: 'edit',
                defs: {
                    name: 'optOutLink'
                },
            });
        },

        handleFrom: function () {
            var value = this.actionData.from;

            if (value == 'specifiedEmailAddress') {
                this.$el.find('.from-email-container').removeClass('hidden');
            } else {
                this.$el.find('.from-email-container').addClass('hidden');
            }
        },

        handleReplyTo: function () {
            var value = this.actionData.replyTo;

            if (value == 'specifiedEmailAddress') {
                this.$el.find('.reply-to-email-container').removeClass('hidden');
            } else {
                this.$el.find('.reply-to-email-container').addClass('hidden');
            }
        },

        handleTo: function () {
            var value = this.actionData.to;

            if (value == 'specifiedEmailAddress') {
                this.$el.find('.to-email-container').removeClass('hidden');
            } else {
                this.$el.find('.to-email-container').addClass('hidden');
            }

            var fieldList = ['specifiedTeams', 'specifiedUsers', 'specifiedContacts'];

            fieldList.forEach(function(field) {
                var $elem = this.$el.find('.to' + this.ucfirst(field) + '-container');
                if (!$elem.hasClass('hidden')) {
                    $elem.addClass('hidden');
                }
            }.bind(this));

            if (~fieldList.indexOf(value)) {
                this.$el.find('.to' + this.ucfirst(value) + '-container').removeClass('hidden');
            }
        },

        getFromOptions: function () {
            var html = '';

            var value = this.actionData.from;

            var arr = ['system', 'specifiedEmailAddress'];

            if (!this.options.flowchartCreatedEntitiesData) {
                arr.push('currentUser');
            }

            arr.forEach(function (item) {
                var label = this.translate(item, 'emailAddressOptions' , 'Workflow');
                html += '<option value="' + item + '" ' + (item === value ? 'selected' : '') + '>' + label + '</option>';
            }, this);

            html += this.getLinkOptions(value, true, true);

            return html;
        },

        getReplyToOptions: function () {
            var html = '';

            var value = this.actionData.replyTo;

            var arr = ['', 'system', 'currentUser', 'specifiedEmailAddress'];

            arr.forEach(function (item) {
                var label = this.translate(item, 'emailAddressOptions' , 'Workflow');
                html += '<option value="' + item + '" ' + (item === value ? 'selected' : '') + '>' + label + '</option>';
            }, this);

            html += this.getLinkOptions(value, false, true);

            return html;
        },

        getToOptions: function () {
            var html = '';

            var value = this.actionData.to;

            var arr = ['currentUser', 'teamUsers', 'specifiedTeams', 'specifiedUsers', 'specifiedContacts', 'specifiedEmailAddress', 'followers', 'followersExcludingAssignedUser'];

            if (this.entityType === 'Email') {
                arr.push('fromOrReplyTo');
            }

            var fieldDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.fields') || {};

            if ('emailAddress' in fieldDefs && this.entityType !== 'Email') {
                var item = 'targetEntity';
                var label = this.translate(item, 'emailAddressOptions' , 'Workflow') + ': ' + this.entityType + '';
                html += '<option value="' + item + '" ' + (item === value ? 'selected' : '') + '>' + label + '</option>';
            }

            arr.forEach(function (item) {
                var label = this.translate(item, 'emailAddressOptions' , 'Workflow');
                html += '<option value="' + item + '" ' + (item === value ? 'selected' : '') + '>' + label + '</option>';
            }, this);

            html += this.getLinkOptions(value);

            return html;
        },

        getLinkOptions: function (value, onlyUser, noMultiple) {
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
                        if (noMultiple) return;
                        if (this.getMetadata().get(['entityDefs', this.entityType, 'fields', link, 'type']) !== 'linkMultiple') {
                            return;
                        }
                    }
                    var fieldDefs = this.getMetadata().get('entityDefs.' + foreignEntityType + '.fields') || {};
                    if (onlyUser && foreignEntityType !== 'User') return;
                    if ('emailAddress' in fieldDefs && fieldDefs.emailAddress.type === 'email') {
                        var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType);
                        html += '<option value="link:' + link + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                    }
                } else if (linkDefs[link].type == 'belongsToParent') {
                    if (onlyUser) return;
                    var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType);
                    html += '<option value="link:' + link + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                }
            }, this);

            Object.keys(linkDefs).forEach(function (link) {
                if (linkDefs[link].type != 'belongsTo') return;

                var foreignEntityType = this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'entity']);
                if (!foreignEntityType) return;

                if (foreignEntityType === 'User') return;

                if (!noMultiple) {
                    if (this.getMetadata().get(['scopes', foreignEntityType, 'stream'])) {
                        var isSelected = 'link:' + link + '.followers' === value;
                        var label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType) + '.' + this.translate('Followers');
                        html += '<option value="link:' + link + '.followers" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                    }
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
                    if (onlyUser && subForeignEntityType !== 'User') return;
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

                    if (noMultiple) return;
                    subLink = 'followers';
                    isSelected = 'link:' + link + '.' + subLink === value;
                    label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType) + '.' + this.translate('Followers');
                    html += '<option value="link:' + link + '.' + subLink + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';

                    subLink = 'contacts';
                    isSelected = 'link:' + link + '.' + subLink === value;
                    label = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links' , this.entityType) + '.' + this.translate('Contact', 'scopeNamesPlural');
                    html += '<option value="link:' + link + '.' + subLink + '" ' + (isSelected ? 'selected' : '') + '>' + label + '</option>';
                }
            }, this);

            return html;
        },

        fetch: function () {
            var emailTemplateView = this.getView('emailTemplate');

            emailTemplateView.fetchToModel();

            if (emailTemplateView.validate()) {
                return;
            }

            var o = emailTemplateView.fetch();

            this.actionData.emailTemplateId = o.emailTemplateId;
            this.actionData.emailTemplateName = o.emailTemplateName;

            this.actionData.from = this.$el.find('[name="from"]').val();
            this.actionData.to = this.$el.find('[name="to"]').val();
            this.actionData.replyTo = this.$el.find('[name="replyTo"]').val();

            if (~['specifiedTeams', 'specifiedUsers', 'specifiedContacts'].indexOf(this.actionData.to)) {
                this.actionData = _.extend(this.actionData, this.getSpecifiedEntityData(this.actionData.to, 'to'));
            }

            this.actionData.fromEmail = this.$el.find('[name="fromEmail"]').val();
            this.actionData.toEmail = this.$el.find('[name="toEmail"]').val();
            this.actionData.replyToEmail = this.$el.find('[name="replyToEmail"]').val();
            this.actionData.doNotStore = this.getViewData('doNotStore').doNotStore || false;
            this.actionData.optOutLink = this.getViewData('optOutLink').optOutLink || false;

            this.actionData.execution = this.actionData.execution || {};

            this.actionData.execution.type = this.$el.find('[name="executionType"]').val();

            if (this.actionData.execution.type != 'immediately') {
                this.actionData.execution.field = this.$el.find('[name="executionField"]').val();
                this.actionData.execution.shiftDays = this.$el.find('[name="shiftDays"]').val();
                this.actionData.execution.shiftUnit = this.$el.find('[name="shiftUnit"]').val();

                if (this.$el.find('[name="shiftDaysOperator"]').val() == 'minus') {
                    this.actionData.execution.shiftDays = (-1) * this.actionData.execution.shiftDays;
                }
            }

            return true;
        },

        getViewData: function (viewName) {
            var view = this.getView(viewName);

            if (view) {
                view.fetchToModel();
                return view.fetch();
            }

            return {};
        },

        getSpecifiedEntityData: function (field, type) {
            var viewName = type + field.charAt(0).toUpperCase() + field.slice(1);
            var view = this.getView(viewName);

            var data = {};

            if (view) {
                view.fetchToModel();
                var viewData = view.fetch();

                data[type + 'SpecifiedEntityName'] = view.foreignScope;
                data[type + 'SpecifiedEntityIds'] = viewData[view.idsName];
                data[type + 'SpecifiedEntityNames'] = viewData[view.nameHashName];
            }

            return data;
        },

        ucfirst: function (string) {
            return string.charAt(0).toUpperCase() + string.slice(1);
        },


    });
});
