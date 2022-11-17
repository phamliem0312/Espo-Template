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

Espo.define('advanced:views/bpmn-flowchart-element/record/sub-process-edit', [
    'advanced:views/bpmn-flowchart-element/record/edit',
    'advanced:bpmn-element-helper',
], function (Dep, Helper) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.hideField('targetType');
            this.hideField('flowchartVisualization');
            this.hidePanel('flowchartVisualization');

            this.controlTargetTypeField();

            this.bpmnHelper = new Helper(this.getHelper(), this.model);
        },

        controlTargetTypeField: function () {
            var dataList = this.model.get('dataList') || [];

            if (dataList.length === 0) {
                this.setFieldNotReadOnly('target');
            } else {
                this.setFieldReadOnly('target');
            }
        },

    });
});