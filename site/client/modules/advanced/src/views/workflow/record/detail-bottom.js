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

define(
    'advanced:views/workflow/record/detail-bottom',
    ['views/record/edit-bottom', 'advanced:views/workflow/record/edit-bottom'],
    function (Dep, Edit) {

    return Dep.extend({

        editMode: false,

        template: 'advanced:workflow/record/edit-bottom',

        setup: function () {
            Dep.prototype.setup.call(this);

            if (this.model.get('type') === 'scheduled') {
                this.hideConditions();
            }

            this.createView('workflowLogRecords', 'views/record/panels/relationship', {
                model: this.model,
                el: this.options.el + ' .panel[data-name="workflowLogRecords"] .panel-body',
                panelName: 'workflowLogRecords',
                defs: {
                    create: false,
                    rowActionsView: "views/record/row-actions/remove-only"
                },
                recordHelper: this.recordHelper,
            });
        },

        afterRender: function () {
            if (!this.model.isNew()) {
                this.showConditions();
                this.showActions();
            } else {
                if (this.model.get('entityType')) {
                    this.showConditions();
                    this.showActions();
                }
            }
        },

        showConditions: function () {
            Edit.prototype.showConditions.call(this);
        },

        showActions: function () {
            this.$el.find('.panel-actions').removeClass('hidden');

            this.createView('actions', 'advanced:views/workflow/record/actions', {
                model: this.model,
                el: this.options.el + ' .actions-container',
                readOnly: !this.editMode,
            }, function (view) {
                view.render();
            });
        },

        hideConditions: function () {
            if (!this.isRendered()) {
                this.once('after:render', function () {
                    this.hideConditions();
                }, this);

                return;
            }
            this.$el.find('.panel-conditions').addClass('hidden');

            var view = this.getView('conditions');

            if (view) {
                view.remove();
            }
        },

        hideActions: function () {
            this.$el.find('.panel-actions').addClass('hidden');

            var view = this.getView('actions');

            if (view) {
                view.remove();
            }
        },
    });
});
