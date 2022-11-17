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

define('advanced:views/bpmn-flow-node/record/row-actions/default', 'views/record/row-actions/default', function (Dep) {

    return Dep.extend({

        getActionList: function () {
            var list = [];


            if (~['In Process'].indexOf(this.model.get('status'))) {
                list.push({
                    action: 'interruptFlowNode',
                    html: this.translate('Interrupt', 'labels', 'BpmnProcess'),
                    data: {
                        id: this.model.id
                    },
                });
            }

            if (!~['Processed', 'Interrupted', 'Rejected', 'Failed'].indexOf(this.model.get('status'))) {
                list.push({
                    action: 'rejectFlowNode',
                    html: this.translate('Reject', 'labels', 'BpmnProcess'),
                    data: {
                        id: this.model.id
                    },
                });
            }

            return list;
        },

    });
});
