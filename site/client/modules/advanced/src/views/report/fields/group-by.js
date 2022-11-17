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

define('advanced:views/report/fields/group-by', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        validations: ['required', 'maxCount'],

        validateMaxCount: function () {
            var items = this.model.get(this.name) || [];
            var maxCount = 2;
            if (items.length > maxCount) {
                var msg = this.translate('validateMaxCount', 'messages', 'Report').replace('{field}', this.translate(this.name, 'fields', this.model.name))
                                                                                  .replace('{maxCount}', maxCount);
                this.showValidationMessage(msg, '.selectize-control');
                return true;
            }
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.setupOptions();
            this.setupTranslatedOptions();

            this.allowCustomOptions = false;

            var version = this.getConfig().get('version') || '';
            var arr = version.split('.');
            if (version !== 'dev' && arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) < 506) {
                this.allowCustomOptions = false;
            }

            this.events['click [data-action="edit-groups"]'] = 'editGroups';
        },

        translateValueToEditLabel: function (value) {
            if (!~(this.params.options || []).indexOf(value)) {
                return value.replace(/\t/g, '');
            }

            return Dep.prototype.translateValueToEditLabel.call(this, value);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode == 'edit') {
                var buttonHtml = '<button class="pull-right btn btn-default" data-action="edit-groups">'+
                    '<span class="fas fa-pencil-alt fa-sm"></span></button>';
                var $b = $(buttonHtml);
                this.$el.prepend($b);
                var width = $b.outerWidth() + 8;
                this.$el.find('.selectize-control').css('width', 'calc(100% - '+width+'px)');
            }
        },

        editGroups: function () {
            this.createView('dialog', 'advanced:views/report/modals/edit-group-by', {
                value: Espo.Utils.clone(this.model.get(this.name) || []),
            }, function (view) {
                view.render();

                this.listenToOnce(view, 'apply', function (value) {
                    this.model.set(this.name, value);
                }, this);
            });
        },

        setupOptions: function () {
            var entityType = this.model.get('entityType');

            var fields = this.getMetadata().get('entityDefs.' + entityType + '.fields') || {};

            var itemList = [];

            var fieldList = Object.keys(fields);

            var weekEnabled = true;
            var version = this.getConfig().get('version') || '';
            var arr = version.split('.');
            if (version !== 'dev' && arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) < 408) {
                weekEnabled = false;
            }

            var quarterEnabled = true;
            if (version !== 'dev' && arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) < 505) {
                quarterEnabled = false;
            }

            fieldList.sort(function (v1, v2) {
                return this.translate(v1, 'fields', entityType).localeCompare(this.translate(v2, 'fields', entityType));
            }.bind(this));

            fieldList.forEach(function (field) {
                if (fields[field].disabled) return;
                if (fields[field].reportDisabled) return;
                if (fields[field].reportGroupByDisabled) return;
                if (fields[field].directAccessDisabled) return;

                if (this.getFieldManager().isScopeFieldAvailable && !this.getFieldManager().isScopeFieldAvailable(entityType, field)) {
                    return;
                }

                if (~['date', 'datetime', 'datetimeOptional'].indexOf(fields[field].type)) {
                    itemList.push('MONTH:' + field);
                    itemList.push('YEAR:' + field);
                    itemList.push('DAY:' + field);
                    if (weekEnabled) {
                        itemList.push('WEEK:' + field);
                    }
                    if (quarterEnabled) {
                        itemList.push('QUARTER:' + field);
                    }
                    if (this.getConfig().get('fiscalYearShift')) {
                        itemList.push('YEAR_FISCAL:' + field);
                        itemList.push('QUARTER_FISCAL:' + field);
                    }
                }
            }, this);

            itemList.push('id');

            fieldList.forEach(function (field) {
                if (
                    ~[
                        'linkMultiple',
                        'date',
                        'datetime',
                        'currency',
                        'currencyConverted',
                        'text',
                        'map',
                        'multiEnum',
                        'array',
                        'checklist',
                        'address',
                        'foreign',
                        'linkOne',
                    ].indexOf(fields[field].type)
                ) return;
                if (fields[field].disabled) return;
                if (fields[field].reportDisabled) return;
                if (fields[field].reportGroupByDisabled) return;
                if (fields[field].directAccessDisabled) return;

                if (this.getFieldManager().isScopeFieldAvailable && !this.getFieldManager().isScopeFieldAvailable(entityType, field)) {
                    return;
                }

                itemList.push(field);
            }, this);

            var links = this.getMetadata().get('entityDefs.' + entityType + '.links') || {};

            var linkList = Object.keys(links);

            linkList.sort(function (v1, v2) {
                return this.translate(v1, 'links', entityType).localeCompare(this.translate(v2, 'links', entityType));
            }.bind(this));

            linkList.forEach(function (link) {
                if (links[link].type != 'belongsTo' && links[link].type != 'hasOne') return;
                var scope = links[link].entity;
                if (!scope) return;

                if (links[link].disabled) return;

                var fields = this.getMetadata().get('entityDefs.' + scope + '.fields') || {};
                var fieldList = Object.keys(fields);

                fieldList.sort(function (v1, v2) {
                    return this.translate(v1, 'fields', scope).localeCompare(this.translate(v2, 'fields', scope));
                }.bind(this));

                fieldList.forEach(function (field) {
                    if (fields[field].disabled) return;
                    if (fields[field].reportDisabled) return;
                    if (fields[field].reportGroupByDisabled) return;
                    if (fields[field].directAccessDisabled) return;
                    if (fields[field].foreignAccessDisabled) return;

                    if (~['date', 'datetime'].indexOf(fields[field].type)) {
                        itemList.push('MONTH:' + link + '.' + field);
                        itemList.push('YEAR:' + link + '.' +  field);
                        itemList.push('DAY:' + link + '.' + field);
                        if (weekEnabled) {
                            itemList.push('WEEK:' + link + '.' + field);
                        }
                    }
                    if (
                        ~[
                            'linkMultiple',
                            'linkParent',
                            'phone',
                            'email',
                            'date',
                            'datetime',
                            'currency',
                            'currencyConverted',
                            'text',
                            'personName',
                            'map',
                            'multiEnum',
                            'checklist',
                            'array',
                            'address',
                            'foreign',
                        ].indexOf(fields[field].type)
                    ) return;

                    if (this.getFieldManager().isScopeFieldAvailable && !this.getFieldManager().isScopeFieldAvailable(scope, field)) {
                        return;
                    }
                    itemList.push(link + '.' + field);
                }, this);
            }, this);

            this.params.options = itemList;

        },

        setupTranslatedOptions: function (customEntityType) {
            this.translatedOptions = {};

            var entityType = customEntityType || this.model.get('entityType');

            this.params.options.forEach(function (item) {
                var hasFunction = false;
                var field = item;
                var scope = entityType;
                var isForeign = false;
                var p = item;
                var link = null
                var func = null;

                if (~item.indexOf(':')) {
                    hasFunction = true;
                    func = item.split(':')[0];
                    p = field = item.split(':')[1];
                }

                if (~p.indexOf('.')) {
                    isForeign = true;
                    link = p.split('.')[0];
                    field = p.split('.')[1];
                    scope = this.getMetadata().get('entityDefs.' + entityType + '.links.' + link + '.entity');
                }
                this.translatedOptions[item] = this.translate(field, 'fields', scope);

                var fieldType = this.getMetadata().get(['entityDefs', scope, 'fields', field, 'type']);
                if (fieldType === 'currencyConverted' && field.substr(-9) === 'Converted') {
                    this.translatedOptions[item] = this.translate(field.substr(0, field.length - 9), 'fields', scope);
                }

                if (isForeign) {
                    this.translatedOptions[item] = this.translate(link, 'links', entityType) + '.' + this.translatedOptions[item];
                }
                if (hasFunction) {
                    this.translatedOptions[item] = this.translate(func, 'functions', 'Report').toUpperCase() + ': ' + this.translatedOptions[item];
                }
            }, this);
        },

    });
});
