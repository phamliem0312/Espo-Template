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

define('advanced:views/workflow/record/edit', ['views/record/edit', 'advanced:views/workflow/record/detail'], function (Dep, Detail) {

    return Dep.extend({

        bottomView: 'advanced:views/workflow/record/edit-bottom',

        sideView: 'advanced:views/workflow/record/edit-side',

        stickButtonsContainerAllTheWay: true,

        saveAndContinueEditingAction: true,

        fetch: function () {
            var data = Dep.prototype.fetch.call(this);

            var conditions = {};
            var actions = [];

            var conditionsData = this.fetchConditions();

            for (var k in conditionsData) {
                data[k] = conditionsData[k];
            }

            var actionsData = this.fetchActions();

            for (var k in actionsData) {
                data[k] = actionsData[k];
            }

            return data;
        },

        fetchConditions: function () {
            var data = {};

            var conditionsView = this.getView('bottom').getView('conditions');

            conditions = {};

            if (conditionsView) {
                conditions = conditionsView.fetch();
            }

            data.conditionsAny = conditions.any || [];
            data.conditionsAll = conditions.all || [];
            data.conditionsFormula = conditions.formula || null;

            return data;
        },

        fetchActions: function () {
            var data = {};

            var actionsView = this.getView('bottom').getView('actions');

            if (actionsView) {
                actions = actionsView.fetch();
            }

            data.actions = actions;

            return data;
        },

        onChangeConditions: function () {
            var data = this.fetchConditions();

            this.model.set(data, {
                ui: true,
            });
        },

        onChangeActions: function () {
            var data = this.fetchActions();

            this.model.set(data, {
                ui: true,
            });
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            Detail.prototype.manageFieldsVisibility.call(this);

            this.listenTo(this.model, 'change', function (model, options) {
                if (this.model.hasChanged('portalOnly') || this.model.hasChanged('type')) {
                    Detail.prototype.manageFieldsVisibility.call(this, options.ui);
                }
            }, this);

            this.listenTo(this.model, 'change:entityType', function (model, value, o) {
                if (o.ui) {
                    setTimeout(function () {
                        model.set({
                            'targetReportId': null,
                            'targetReportName': null,
                        });
                    }, 100);
                }
            }, this);

            if (!this.model.isNew()) {
                this.setFieldReadOnly('type');
                this.setFieldReadOnly('entityType');
            }

            this.listenTo(this.model, 'change', function (model, o) {
                if (
                    !this.model.hasChanged('actions') &&
                    !this.model.hasChanged('conditionsAll') &&
                    !this.model.hasChanged('conditionsAny')
                ) {
                    return;
                }

                if (!this.model.isNew()) {
                    return;
                }

                var actions = this.model.get('actions') || [];
                var conditionsAll = this.model.get('conditionsAll') || [];
                var conditionsAny = this.model.get('conditionsAny') || [];

                if (
                    actions.length ||
                    conditionsAll.length ||
                    conditionsAny.length
                ) {
                    this.setFieldReadOnly('entityType');
                    //this.setFieldReadOnly('type');

                    return;
                }

                this.setFieldNotReadOnly('entityType');
                //this.setFieldNotReadOnly('type');

            }, this);
        },

    });
});
