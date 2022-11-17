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

Espo.define('advanced:views/bpmn-flowchart/record/panels/flowchart', 'views/record/panels/bottom', function (Dep) {

    return Dep.extend({

        template: 'advanced:bpmn-flowchart/record/panels/flowchart',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('flowchart', 'advanced:views/bpmn-flowchart/fields/flowchart', {
                model: this.model,
                el: this.options.el + ' .field[data-name="flowchart"]',
                defs: {
                    name: 'flowchart'
                },
                mode: this.mode,
                inlineEditDisabled: true,
                disabled: this.recordHelper.getFieldStateParam('flowchart', 'hidden')
            });
        },

        getFieldViews: function () {
            var fields = {};
            fields.flowchart = this.getView('flowchart');
            return fields;
        }
    });
});