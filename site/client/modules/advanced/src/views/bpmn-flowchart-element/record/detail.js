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

Espo.define('advanced:views/bpmn-flowchart-element/record/detail', 'views/record/detail-small', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.dynamicLogicDefs = this.options.dynamicLogicDefs;

            Dep.prototype.setup.call(this);

            if (!this.model.get('description')) {
                this.hideField('description');
                this.hidePanel('description');
            }
            if (!this.model.get('text')) {
                this.hideField('text');
                this.hidePanel('text');
            }

            var flowchartData = {
                list: this.model.get('dataList'),
                createdEntitiesData: this.model.flowchartCreatedEntitiesData,
            };
            this.model.set('flowchartData', flowchartData);
        },

    });
});