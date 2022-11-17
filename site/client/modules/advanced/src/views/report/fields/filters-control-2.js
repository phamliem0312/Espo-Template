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

Espo.define('advanced:views/report/fields/filters-control-2', 'views/fields/base', function (Dep) {

    return Dep.extend({

        editTemplate: 'advanced:report/fields/filters-control-2/edit',

        detailTemplate: 'advanced:report/fields/filters-control-2/detail',

        setup: function () {
            var entityType = this.model.get('entityType');

            var dataList = this.model.get('filtersDataList') || [];

            // for backward compatibility
            if (!dataList.length) {
                var filterList = this.model.get('filters') || [];
                var filtersData = this.model.get('filtersData') || {};

                filterList.forEach(function (item) {
                    if (!(item in filtersData)) return;

                    var data = {};
                    data.id = Math.random().toString(16).slice(2);
                    data.name = item;

                    data.params = filtersData[item];
                    dataList.push(data);
                }, this);
            }

            this.createView('node', 'advanced:views/report/filters/node', {
                el: this.getSelector() + ' > .node-row > .node',
                scope: entityType,
                dataList: dataList
            });
        },


        afterRender: function () {

        },

        fetch: function () {
            var data = {
                filtersDataList: this.getView('node').fetch(),
                filtersData: null,
                filters: null
            };

            return data;
        },

    });

});

