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

define('advanced:views/bpmn-flowchart-element/fields/message-related-to', ['views/fields/enum'], function (Dep, From) {

    return Dep.extend({

        fetchEmptyValueAsNull: true,

        setupOptions: function () {
            var list = [''];
            var translatedOptions = {};

            if (this.getMetadata().get(['scopes', this.model.targetEntityType, 'object'])) {
                list.push('targetEntity');
            }

            var ignoreEntityTypeList = ['User', 'Email'];
            this.model.elementHelper.getTargetCreatedList().forEach(function (item) {
                var entityType = this.model.elementHelper.getEntityTypeFromTarget(item);
                if (~ignoreEntityTypeList.indexOf(entityType)) return;
                if (!this.getMetadata().get(['scopes', entityType, 'object'])) return;

                list.push(item);
                translatedOptions[item] = this.model.elementHelper.translateTargetItem(item);
            }, this);

            this.model.elementHelper.getTargetLinkList(2, false, false).forEach(function (item) {
                var entityType = this.model.elementHelper.getEntityTypeFromTarget(item);
                if (~ignoreEntityTypeList.indexOf(entityType)) return;
                if (!this.getMetadata().get(['scopes', entityType, 'object'])) return;

                list.push(item);
                translatedOptions[item] = this.model.elementHelper.translateTargetItem(item);
            }, this);

            this.params.options = list;

            translatedOptions[''] = this.translate('None');
            translatedOptions['targetEntity'] = this.getLanguage().translateOption('targetEntity', 'emailAddress', 'BpmnFlowchartElement') +
            ' (' + this.translate(this.model.targetEntityType, 'scopeNames') + ')';

            this.translatedOptions = translatedOptions;
        },
    });
});