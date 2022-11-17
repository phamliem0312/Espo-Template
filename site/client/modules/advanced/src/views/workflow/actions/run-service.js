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

Espo.define('advanced:views/workflow/actions/run-service', ['advanced:views/workflow/actions/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        type: 'runService',

        template: 'advanced:workflow/actions/run-service',

        data: function () {
            return _.extend({
                methodName: this.translatedOption || this.getLabel(this.actionData.methodName, 'serviceActions'),
                additionalParameters: this.actionData.additionalParameters,
                targetTranslated: this.getTargetTranslated()
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            var methodName = this.actionData.methodName || null;

            var model = new Model();
            model.name = 'Workflow';
            model.set({
                methodName: methodName,
                additionalParameters: this.actionData.additionalParameters
            });

            this.translatedOption = this.getLabel(methodName, 'serviceActions');
        },

        getTargetTranslated: function () {
            var target = this.actionData.target;
            if (target === 'targetEntity' || !target) {
                return this.translate('Target Entity', 'labels', 'Workflow');
            }
            if (target.indexOf('link:') === 0) {
                var link = target.substr(5);
                return this.translate('Related', 'labels', 'Workflow') + ': ' + this.getLanguage().translate(link, 'links', this.entityType);

            } else if (target.indexOf('created:') === 0) {
                return this.translateCreatedEntityAlias(target);
            }
        },

        getLabel: function(methodName, category, returns) {
            if (methodName) {
                var labelName = this.actionData.targetEntityType + methodName.charAt(0).toUpperCase() + methodName.slice(1);
                if (this.getLanguage().has(labelName, category, 'Workflow')) {
                    return this.translate(labelName, category, 'Workflow');
                }

                if (returns != null && !this.getLanguage().has(methodName, category, 'Workflow')) {
                    return returns;
                }

                return this.translate(methodName, category, 'Workflow');
            }
            return '';
        }

    });
});
