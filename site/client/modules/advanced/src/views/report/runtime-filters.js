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

Espo.define('advanced:views/report/runtime-filters', 'view', function (Dep) {

    return Dep.extend({

        template: 'advanced:report/runtime-filters',

        data: function () {
            return {
                filterDataList: this.getFilterDataList()
            };
        },

        setup: function () {
            this.wait(true);

            this.filterList = this.options.filterList;

            this.filtersData = this.options.filtersData || {};

            this.ignoreFilterList = [];

            this.getModelFactory().create(this.options.entityType, function (model) {
                this.model = model;
                this.getCollectionFactory().create(this.options.entityType, function (collection) {

                    Espo.require('search-manager', function (SearchManager) {
                        this.searchManager = new SearchManager(collection, 'report', null, this.getDateTime());

                        this.createFilters();
                        this.wait(false);
                    }.bind(this));

                }, this);

            }, this);
        },

        createFilters: function () {
            this.options.filterList.forEach(function (name) {
                var params = this.filtersData[name] || this.getFilterDefaultData(name) || null;
                this.createFilter(name, params);
            }, this);
        },

        getFilterDefaultData: function (name) {
            var fieldType = this.getMetadata().get(['entityDefs', this.model.name, 'fields', name, 'type']);

            if (~['date', 'datetime', 'datetimeOptional'].indexOf(fieldType)) {
                return {
                    type: 'currentYear',
                    field: name,
                };
            }
        },

        getFilterDataList: function () {
            var list = [];
            this.options.filterList.forEach(function (name) {
                if (~this.ignoreFilterList.indexOf(name)) return;
                list.push({
                    key: 'filter-' + name,
                    name: name
                });
            }, this);
            return list;
        },

        createFilter: function (name, params, callback) {
            params = params || {};

            var scope = this.model.name;
            var field = name;

            if (~name.indexOf('.')) {
                var link = name.split('.')[0];
                field = name.split('.')[1];
                scope = this.getMetadata().get('entityDefs.' + this.model.name + '.links.' + link + '.entity');
            }
            if (!scope || !field) {
                return;
            }

            if (!this.getMetadata().get(['entityDefs', scope, 'fields', field])) {
                this.ignoreFilterList.push(name);
                return;
            }

            this.getModelFactory().create(scope, function (model) {
                this.createView('filter-' + name, 'views/search/filter', {
                    name: field,
                    model: model,
                    params: params,
                    el: this.options.el + ' .filter[data-name="'+name+'"]',
                    notRemovable: true,
                }, function (view) {
                    view.once('after:render', function () {
                        if (~name.indexOf('.')) {
                            var label = this.translate(link, 'links', this.options.entityType) + '.' + this.translate(field, 'fields', scope);
                                view.$el.find('.control-label').html(label);
                        }
                        if (typeof callback === 'function') {
                            callback();
                        }
                    }, this);
                });
            }, this);
        },

        fetchRaw: function () {
            var data = {};
            this.filterList.forEach(function (name) {
                if (!this.hasView('filter-' + name)) return;

                var view = this.getView('filter-' + name).getView('field');
                if (!view) return;
                var filterData = view.fetchSearch();

                var prepareItem = function (data, name) {
                    var type = data.type;
                    if (type === 'or' || type === 'and') {
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

                prepareItem(filterData, name);

                data[name] = filterData;
            }, this);
            return data;
        },

        fetch: function () {
            var data = this.fetchRaw();
            this.searchManager.setAdvanced(data);
            return this.searchManager.getWhere();
        },

    });
});
