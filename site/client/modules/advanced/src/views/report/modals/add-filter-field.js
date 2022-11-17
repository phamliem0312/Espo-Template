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


Espo.define('advanced:views/report/modals/add-filter-field', 'views/modal', function (Dep) {

    return Dep.extend({

        _template: '<div class="field" data-name="filters">{{{field}}}</div>',

        backdrop: true,

        events: {
            'click a[data-action="addField"]': function (e) {
                this.trigger('add-field', $(e.currentTarget).data().name);
            }
        },

        data: function () {
            return {
            };
        },

        setup: function () {
            this.header = this.translate('Add Field');

            var scope = this.scope = this.options.scope;

            this.wait(true);

            this.getModelFactory().create('Report', function (model) {
                model.set('entityType', scope);

                this.createView('field', 'advanced:views/report/fields/filters', {
                    el: this.getSelector() + ' .field',
                    model: model,
                    mode: 'edit',
                    defs: {
                        name: 'filters',
                        params: {}
                    }
                }, function (view) {
                    this.listenTo(view, 'change', function () {
                        var list = model.get('filters') || [];
                        if (!list.length) return;
                        this.trigger('add-field', list[0]);
                    }, this);
                });

                this.wait(false);
            }, this);

        }

    });
});
