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

define('advanced:views/report/fields/columns-control', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        editTemplate: 'advanced:report/fields/filters-control/edit',

        detailTemplate: 'advanced:report/fields/filters-control/detail',

        fieldDataList: [
            {
                name: 'width',
                view: 'views/fields/float'
            },
            {
                name: 'align',
                view: 'views/fields/enum',
                params: {
                    options: ["left", "right"],
                    translation: 'Report.options.layoutAlign',
                },
            },
            {
                name: 'link',
                view: 'views/fields/bool'
            },
            {
                name: 'exportOnly',
                view: 'views/fields/bool',
            },
            {
                name: 'notSortable',
                view: 'views/fields/bool'
            },
        ],

        setup: function () {
            var entityType = this.entityType = this.model.get('entityType');


            this.seed = new Model();
            this.seed.name = 'LayoutManager';

            this.setupColumnsData();

            this.listenTo(this.model, 'change:columns', function () {
                var previousColumnList = Espo.Utils.clone(this.columnList);
                var toAdd = [];
                var toRemove = [];
                this.setupColumnsData();
                var columnList = this.columnList;
                columnList.forEach(function (name) {
                    if (!~previousColumnList.indexOf(name)) {
                        toAdd.push(name);
                    }
                });
                previousColumnList.forEach(function (name) {
                    if (!~columnList.indexOf(name)) {
                        toRemove.push(name);
                    }
                });

                if (this.isRendered()) {
                    toAdd.forEach(function (name) {
                        this.createColumn(name);
                    }, this);
                    toRemove.forEach(function (name) {
                        this.removeColumn(name);
                    }, this);
                }

            }, this);
        },

        setupColumnsData: function () {
            this.columnList = Espo.Utils.clone(this.model.get('columns')) || [];
        },

        afterRender: function () {
            this.columnList.forEach(function (name) {
                var params = (this.model.get('columnsData') || {})[name];
                this.createColumn(name, params);
            }, this);
        },

        removeColumn: function (name) {
            this.clearView('name-' + name);
            this.$el.find('.filters-row .column-' + Espo.Utils.toDom(name)).remove();

            this.fieldDataList.forEach(function (item) {
                this.clearView('field-'+name+'-'+item.name);
            }, this);
        },

        createColumn: function (name, params) {
            params = params || {};

            var label;

            if (~name.indexOf('.')) {
                var link = name.split('.')[0];
                field = name.split('.')[1];
                var scope = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + link + '.entity');
                label = this.translate(link, 'links', this.entityType) + '.' + this.translate(field, 'fields', scope);
            } else {
                label = this.translate(name, 'fields', this.entityType);
            }


            var columnHtml =
            '<div class="column column-' + Espo.Utils.toDom(name) + ' col-sm-4 col-md-3" data-name="'+name+'">' +
            '<label class="cotrol-label">'+label+'</label>' +
            '<div class="column-content" style="margin-bottom: 10px;">' + '</div>' +
            '</div>';

            var $column = $(columnHtml);
            this.$el.find('.filters-row').append($column);

            var $column = this.$el.find('.filters-row .column-' + Espo.Utils.toDom(name));
            var $columnContent = $column.find('.column-content');

            var model = this.seed.clone();
            model.name = 'LayoutManager';

            var defaultAttributes = {};
            if (name === 'name') {
                defaultAttributes.link = true;
            }

            var attr = (this.model.get('columnsData') || {})[name] || defaultAttributes;
            model.set(attr);

            this.fieldDataList.forEach(function (item) {
                var fieldHtml =
                '<div class="column-field" data-name="'+item.name+'">' +
                    '<label class="cotrol-label small">'+this.translate(item.name, 'layoutFields', 'Report')+'</label>' +
                    '<div class="field-content"></div>' +
                '</div>';

                var $field = $(fieldHtml);
                $columnContent.append($field);

                this.createView('field-'+name+'-'+item.name, item.view, {
                    model: model,
                    name: item.name,
                    defs: {
                        name: item.name,
                        params: item.params || {},
                    },
                    el: this.options.el + ' .column[data-name="'+name+'"] .column-content .column-field[data-name="'+item.name+'"] .field-content',
                    mode: 'edit'
                }, function (view) {
                    view.render();
                }, this);
            }, this);
        },

        fetch: function () {
            var data = {};
            this.columnList.forEach(function (name) {
                var columnData = {};
                this.fieldDataList.forEach(function (item) {
                    var view = this.getView('field-'+name+'-'+item.name);
                    if (!view) return;
                    columnData = _.extend(columnData, view.fetch());
                }, this);
                data[name] = columnData;
            }, this);

            return {
                'columnsData': data
            };
        },

    });
});
