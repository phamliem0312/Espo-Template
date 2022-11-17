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


Espo.define('advanced:views/workflow/modals/add-condition', 'views/modal', function (Dep) {

    return Dep.extend({

        _template: '<div class="field" data-name="conditionFields">{{{field}}}</div>',

        backdrop: true,

        events: {
            'click a[data-action="addField"]': function (e) {
                this.trigger('add-field', $(e.currentTarget).data().name);
            }
        },

        setup: function () {
            this.header = this.translate('Add Condition', 'labels', 'Workflow');

            var scope = this.scope = this.options.scope;

            this.wait(true);

            this.getModelFactory().create('Workflow', function (model) {
                model.targetEntityType = scope;

                this.createView('field', 'advanced:views/workflow/fields/condition-fields', {
                    el: this.getSelector() + ' .field',
                    model: model,
                    mode: 'edit',
                    createdEntitiesData: this.options.createdEntitiesData,
                    defs: {
                        name: 'conditionFields',
                        params: {}
                    }
                }, function (view) {
                    this.listenTo(view, 'change', function () {
                        var list = model.get('conditionFields') || [];
                        if (!list.length) return;
                        this.trigger('add-field', list[0]);
                    }, this);
                });

                this.wait(false);
            }, this);
        }

    });
});

