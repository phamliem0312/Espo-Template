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

Espo.define('advanced:views/bpmn-user-task/modals/resolve', 'views/modal', function (Dep) {

    return Dep.extend({

        template: 'advanced:bpmn-user-task/modals/resolve',

        backdrop: true,

        setup: function () {
            this.header = this.translate('BpmnUserTask', 'scopeNames') + ' &raquo; ' + Handlebars.Utils.escapeExpression(this.model.get('name'));

            this.originalModel = this.model;
            this.model = this.model.clone();

            this.createView('record', 'advanced:views/bpmn-user-task/record/resolve', {
                model: this.model,
                el: this.getSelector() + ' .record'
            });

            this.buttonList = [
                {
                    name: 'resolve',
                    text: this.translate('Resolve', 'labels', 'BpmnUserTask'),
                    style: 'danger',
                    disabled: true
                },
                {
                    name: 'cancel',
                    label: 'Cancel'
                }
            ];

            this.listenTo(this.model, 'change:resolution', function (model, value) {
                if (value) {
                    this.enableButton('resolve');
                } else {
                    this.disableButton('resolve');
                }
            }, this);
        },

        actionResolve: function () {
            this.disableButton('resolve');
            this.model.save().then(function () {
                this.originalModel.set('resolution', this.model.get('resolution'));
                this.originalModel.set('resolutionNote', this.model.get('resolutionNote'));
                this.originalModel.set('isResolved', true);
                this.originalModel.trigger('sync');
                Espo.Ui.success(this.translate('Done'));
                this.close();
            }.bind(this));
        }

    });
});