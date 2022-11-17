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

Espo.define('advanced:views/report/filters/container', 'view', function (Dep) {

    return Dep.extend({

        _template: '<div class="filter">{{{filter}}}</div>',

        events: {
            'click .remove-filter': function () {
                this.trigger('remove-item');
            }
        },

        setup: function () {
            this.scope = this.options.scope;
            this.filterData = this.options.filterData;

            var scope = this.scope;
            var name = this.filterData.name;
            var field = name;

            var link = null;

            if (~name.indexOf('.')) {
                var link = name.split('.')[0];
                field = name.split('.')[1];
                scope = this.getMetadata().get('entityDefs.' + this.scope + '.links.' + link + '.entity');
            }
            if (!scope || !field) {
                return;
            }

            this.wait(true);

            this.getModelFactory().create(scope, function (model) {
                this.createView('filter', 'views/search/filter', {
                    name: field,
                    model: model,
                    params: this.filterData.params,
                    el: this.getSelector() + ' .filter'
                });

                if (scope !== this.scope) {
                    this.on('after:render', function () {
                        var label = this.translate(link, 'links', this.scope) + '.' + this.translate(field, 'fields', scope);
                        this.$el.find('label[data-name="'+field+'"]').html(label);
                    }, this);
                }

                this.wait(false);
            }, this);
        },

        fetch: function () {
            var searchData = this.getView('filter').getView('field').fetchSearch();

            var prepareItem = function (data, name) {
                var type = data.type;
                if (type === 'or' || type === 'and' || type === 'not' || type === 'subQueryIn') {
                    (data.value || []).forEach(function (item) {
                        prepareItem(item, name);
                    }, this);
                    return;
                }

                var attribute = data.attribute || data.field || name;
                if (~name.indexOf('.') && !~attribute.indexOf('.')) {
                    var link = name.split('.')[0];
                    attribute = link + '.' + attribute;
                }
                data.field = attribute;
                data.attribute = attribute;
            };

            prepareItem(searchData, this.filterData.name);

            var data = {
                id: this.filterData.id,
                name: this.filterData.name,
                params: searchData
            };

            return data;
        }

    });
});
