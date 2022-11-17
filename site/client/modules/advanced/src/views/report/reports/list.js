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

define('advanced:views/report/reports/list', 'advanced:views/report/reports/base', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.initReport();
        },

        getListLayout: function (forExport) {
            var scope = this.model.get('entityType')
            var layout = [];

            var columnsData = Espo.Utils.cloneDeep(this.model.get('columnsData') || {});
            (this.model.get('columns') || []).forEach(function (item) {
                var o = columnsData[item] || {};
                o.name = item;

                if (!forExport && o.exportOnly) return;

                if (~item.indexOf('.')) {
                    var a = item.split('.');
                    o.name = item.replace('.', '_');
                    o.notSortable = true;

                    var link = a[0];
                    var field = a[1];

                    var foreignScope = this.getMetadata().get('entityDefs.' + scope + '.links.' + link + '.entity');
                    var label = this.translate(link, 'links', scope) + '.' + this.translate(field, 'fields', foreignScope);

                    o.customLabel = label;

                    var type = this.getMetadata().get('entityDefs.' + foreignScope + '.fields.' + field + '.type');

                    if (type === 'enum') {
                        o.view = 'advanced:views/fields/foreign-enum';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'image') {
                        o.view = 'views/fields/image';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'file') {
                        o.view = 'views/fields/file';
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'date') {
                        o.view = 'views/fields/date';
                        o.notSortable = false;
                        o.options = {
                            foreignScope: foreignScope
                        };
                    } else if (type === 'datetime') {
                        o.view = 'views/fields/datetime';
                        o.options = {
                            foreignScope: foreignScope
                        };
                        o.notSortable = false;
                    } else if (type === 'link') {
                        o.view = 'advanced:views/fields/foreign-link';
                    } else if (type === 'email') {
                        o.view = 'views/fields/email';
                        o.notSortable = false;
                    } else if (type === 'phone') {
                        o.view = 'views/fields/phone';
                        o.notSortable = false;
                    } else if (type === 'array' || type === 'checklist' || type === 'multiEnum') {
                        o.view = 'views/fields/array';
                        o.notSortable = true;
                    } else if (type === 'varchar') {
                        o.view = 'views/fields/varchar';
                        o.notSortable = false;
                    } else if (type === 'bool') {
                        o.view = 'views/fields/bool';
                        o.notSortable = false;
                    } else if (type === 'currencyConverted') {
                        o.view = 'views/fields/currency-converted';
                        o.notSortable = false;
                    }
                } else {
                    var type = this.getMetadata().get(['entityDefs', scope, 'fields', item, 'type']);
                    if (type === 'linkMultiple') {
                        o.notSortable = true;
                    } else if (type === 'attachmentMultiple') {
                        o.notSortable = true;
                    }
                }
                layout.push(o);
            }, this);
            return layout;
        },

        export: function () {
            var where = this.getRuntimeFilters();

            var url = 'Report/action/exportList';

            var data = {
                id: this.model.id
            };

            var fieldList = [];

            var listLayout = this.getListLayout(true);
            listLayout.forEach(function (item) {
                fieldList.push(item.name);
            });

            var o = {
                fieldList: fieldList,
                scope: this.model.get('entityType')
            };

            this.createView('dialogExport', 'views/export/modals/export', o, function (view) {
                view.render();
                this.listenToOnce(view, 'proceed', function (dialogData) {
                    if (!dialogData.exportAllFields) {
                        data.attributeList = dialogData.attributeList;
                        data.fieldList = dialogData.fieldList;
                    }
                    data.where = where;
                    data.format = dialogData.format;

                    Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                    this.ajaxPostRequest(url, data, {timeout: 0}).then(function (data) {
                        Espo.Ui.notify(false);
                        if ('id' in data) {
                            window.location = this.getBasePath() + '?entryPoint=download&id=' + data.id;
                        }
                    }.bind(this));
                }, this);
            }, this);
        },

        run: function () {
            this.notify('Please wait...');

            $container = this.$el.find('.report-results-container');
            $container.empty();

            $listContainer = $('<div>').addClass('report-list');

            $container.append($listContainer);

            this.getCollectionFactory().create(this.model.get('entityType'), function (collection) {
                collection.url = 'Report/action/runList?id=' + this.model.id;
                collection.where = this.getRuntimeFilters();

                var orderByList = this.model.get('orderByList');
                if (orderByList && orderByList !== '') {
                    var arr = orderByList.split(':');
                    collection.sortBy = arr[1];
                    collection.asc = arr[0] === 'ASC';

                    collection.orderBy = arr[1];
                    collection.order = arr[0] === 'ASC' ? 'asc' : 'desc';
                }

                collection.maxSize = this.getConfig().get('recordsPerPage') || 20;

                this.listenToOnce(collection, 'sync', function () {
                    this.storeRuntimeFilters();

                    this.createView('list', 'advanced:views/record/list-for-report', {
                        el: this.options.el + ' .report-list',
                        collection: collection,
                        listLayout: this.getListLayout(),
                        displayTotalCount: true,
                        reportId: this.model.id,
                        runtimeWhere: collection.where
                    }, function (view) {
                        this.notify(false);

                        this.listenTo(view, 'after:render', function () {
                            view.$el.find('> .list').addClass('no-side-margin').addClass('no-bottom-margin');
                        }, this);

                        view.render();
                    }, this);
                }, this);

                collection.fetch();

            }, this);
        },

    });
});
