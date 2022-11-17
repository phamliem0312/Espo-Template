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

define('advanced:start-process-action-handler', ['action-handler'], function (Dep) {

    return Dep.extend({

        init: function () {
            if (~(this.view.getHelper().getAppParam('flowchartEntityTypeList') || []).indexOf(this.view.model.entityType)) {
                this.view.showHeaderActionItem('startProcessGlobal');
            }
        },

        actionStartProcessGlobal: function () {
            var viewName = 'views/modals/select-records';
            this.view.createView('startProcessDialog', viewName, {
                scope: 'BpmnFlowchart',
                primaryFilterName: 'isManuallyStartable',
                createButton: false,
                filters: {
                    targetType: {
                        type: 'in',
                        value: [this.view.model.entityType],
                        data: {
                            type: 'anyOf',
                            valueList: [this.view.model.entityType]
                        },
                    },
                },
            }).then(
                function (view) {
                    view.render();

                    this.view.listenToOnce(view, 'select', function (m) {
                        var attributes = {
                            flowchartName: m.get('name'),
                            flowchartId: m.id,
                            targetType: this.view.model.entityType,
                            targetName: this.view.model.get('name'),
                            targetId: this.view.model.id,
                            startElementIdList: m.get('eventStartAllIdList'),
                            flowchartElementsDataHash: m.get('elementsDataHash'),
                        };

                        var router = this.view.getRouter();

                        var returnUrl = router.getCurrentUrl();
                        router.navigate('#BpmnProcess/create', {trigger: false});
                        router.dispatch('BpmnProcess', 'create', {
                            attributes: attributes,
                            returnUrl: returnUrl,
                        });

                    }.bind(this));

                }.bind(this)
            );
        },

    });
});
