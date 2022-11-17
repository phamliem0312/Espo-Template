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

Espo.define('advanced:views/bpmn-flowchart-element/fields/call-activity-target', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var data = this.getTargetOptionsData();

            this.params.options = data.itemList;
            this.translatedOptions = data.translatedOptions;
        },

        getTargetOptionsData: function () {
            var targetOptionList = [''];

            var translatedOptions = {};
            translatedOptions[''] = this.translate('Current', 'labels', 'Workflow') + ' (' + this.translate(this.model.targetEntityType, 'scopeNames') + ')';

            var list = this.model.elementHelper.getTargetCreatedList();
            list.forEach(function (item) {
                targetOptionList.push(item);
                translatedOptions[item] = this.model.elementHelper.translateTargetItem(item);
            }, this);

            var linkList = this.model.elementHelper.getTargetLinkList(2, false, this.skipParent);
            linkList.forEach(function (item) {
                targetOptionList.push(item);
                translatedOptions[item] = this.model.elementHelper.translateTargetItem(item);
            }, this);


            return {
                itemList: targetOptionList,
                translatedOptions: translatedOptions,
            };
        },

    });
});