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

define('advanced:views/bpmn-process/fields/start-element-id', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.has('startElementIdList')) {
                this.conrolElementIdList();
            }

            this.listenTo(this.model, 'change:startElementIdList', function () {
                this.conrolElementIdList();
            }, this);
        },

        conrolElementIdList: function () {
            var flowchartElementsDataHash = this.model.get('flowchartElementsDataHash') || {};

            var startElementIdList = this.model.get('startElementIdList') || [];
            this.translatedOptions = {};
            startElementIdList.forEach(function (id) {
                if (!(id in flowchartElementsDataHash)) return;
                this.translatedOptions[id] = flowchartElementsDataHash[id].text || id;

                var label = flowchartElementsDataHash[id].text || id;

                label = this.translate(flowchartElementsDataHash[id].type, 'elements', 'BpmnFlowchart') + ': ' + label;

                this.translatedOptions[id] = label;
            }, this);
        },

    });
});
