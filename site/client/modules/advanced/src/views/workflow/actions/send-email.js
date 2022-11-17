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

Espo.define('advanced:views/workflow/actions/send-email', ['advanced:views/workflow/actions/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/actions/send-email',

        type: 'sendEmail',

        defaultActionData: {
            execution: {
                type: 'immediately',
                field: false,
                shiftDays: 0,
                shiftUnit: 'days',
            },
            from: 'system',
            to: '',
            optOutLink: false,
        },

        data: function () {
            var data = Dep.prototype.data.call(this);

            data.fromLabel = this.translateEmailOption(this.actionData.from);
            data.toLabel = this.translateEmailOption(this.actionData.to);
            data.replyToLabel = this.translateEmailOption(this.actionData.replyTo);
            return data;
        },

        setModel: function () {
            this.model.set({
                emailTemplateId: this.actionData.emailTemplateId,
                emailTemplateName: this.actionData.emailTemplateName,
                doNotStore: this.actionData.doNotStore || false,
                optOutLink: this.actionData.optOutLink || false,
            });
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('executionTime', 'advanced:views/workflow/action-fields/execution-time', {
                el: this.options.el + ' .execution-time-container',
                executionData: this.actionData.execution || {},
                entityType: this.entityType,
                readOnly: true,
            });

            var model = this.model = new Model();
            model.name = 'Workflow';

            this.setModel();
            this.on('change', function () {
                this.setModel();
            }, this);

            this.createView('emailTemplate', 'views/fields/link', {
                el: this.options.el + ' .field-emailTemplate',
                model: model,
                mode: 'edit',
                foreignScope: 'EmailTemplate',
                defs: {
                    name: 'emailTemplate',
                    params: {
                        required: true,
                    }
                },
                readOnly: true,
            });

            this.createView('toSpecifiedTeams', 'views/fields/link-multiple', {
                el: this.options.el + ' .toSpecifiedTeams-container .field-toSpecifiedTeams',
                model: model,
                mode: 'edit',
                foreignScope: 'Team',
                defs: {
                    name: 'toSpecifiedTeams'
                },
                readOnly: true,
            });

            this.createView('toSpecifiedUsers', 'views/fields/link-multiple', {
                el: this.options.el + ' .toSpecifiedUsers-container .field-toSpecifiedUsers',
                model: model,
                mode: 'edit',
                foreignScope: 'User',
                defs: {
                    name: 'toSpecifiedUsers'
                },
                readOnly: true,
            });

            this.createView('toSpecifiedContacts', 'views/fields/link-multiple', {
                el: this.options.el + ' .toSpecifiedContacts-container .field-toSpecifiedContacts',
                model: model,
                mode: 'edit',
                foreignScope: 'Contact',
                defs: {
                    name: 'toSpecifiedContacts'
                },
                readOnly: true,
            });

            this.createView('doNotStore', 'views/fields/bool', {
                el: this.options.el + ' .field-doNotStore',
                model: model,
                mode: 'edit',
                defs: {
                    name: 'doNotStore',
                },
                readOnly: true,
            });

            this.createView('optOutLink', 'views/fields/bool', {
                el: this.options.el + ' .field[data-name="optOutLink"]',
                model: model,
                mode: 'edit',
                defs: {
                    name: 'optOutLink',
                },
                readOnly: true,
            });
        },

        render: function (callback) {
            this.getView('executionTime').reRender();

            var emailTemplateView = this.getView('emailTemplate');
            emailTemplateView.model.set({
                emailTemplateId: this.actionData.emailTemplateId,
                emailTemplateName: this.actionData.emailTemplateName
            });
            emailTemplateView.reRender();

            if (this.actionData.toSpecifiedEntityIds) {
                var viewName = 'to' + this.actionData.to.charAt(0).toUpperCase() + this.actionData.to.slice(1);
                var toSpecifiedEntityView = this.getView(viewName);
                if (toSpecifiedEntityView) {
                    var toSpecifiedEntityData = {};
                    toSpecifiedEntityData[viewName + 'Ids'] = this.actionData.toSpecifiedEntityIds;
                    toSpecifiedEntityData[viewName + 'Names'] = this.actionData.toSpecifiedEntityNames;

                    toSpecifiedEntityView.model.set(toSpecifiedEntityData);
                    toSpecifiedEntityView.reRender();
                }
            }

            var doNotStore = this.getView('doNotStore');
            doNotStore.model.set({
                doNotStore: this.actionData.doNotStore
            });
            doNotStore.reRender();

            Dep.prototype.render.call(this, callback);
        },

        renderFields: function () {
        },

        translateEmailOption: function (value) {
            var linkDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + value);
            if (linkDefs) {
                return this.translate(value, 'links' , this.entityType);
            }

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
