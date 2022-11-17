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

Espo.define('advanced:views/bpmn-flowchart-element/record/gateway-exclusive-detail', 'advanced:views/bpmn-flowchart-element/record/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            if (!this.isDivergent()) {
                this.hideField('flowsConditions');
                this.hidePanel('flowsConditions');
                this.hideField('defaultFlowId');
                this.hidePanel('divergent');
            } else {
                this.showPanel('divergent');
            }
        },

        isCovergent: function () {
            var flowchartDataList = this.model.dataHelper.getAllDataList();
            var id = this.model.id;

            var count = 0;
            flowchartDataList.forEach(function (item) {
                if (item.type === 'flow' && item.endId === id) {
                    count++;
                }
            }, this);
            return count > 1;
        },

        isDivergent: function () {
            var flowchartDataList = this.model.dataHelper.getAllDataList();
            var id = this.model.id;

            var count = 0;
            flowchartDataList.forEach(function (item) {
                if (item.type === 'flow' && item.startId === id) {
                    count++;
                }
            }, this);
            return count > 1;
        }

    });

});