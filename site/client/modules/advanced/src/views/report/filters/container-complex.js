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

define('advanced:views/report/filters/container-complex', ['views/record/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:report/filters/container-complex',

        events: {
            'click > div > a[data-action="removeGroup"]': function () {
                this.trigger('remove-item');
            }
        },

        setup: function () {
            var model = this.model = new Model;
            model.name = 'Report';

            Dep.prototype.setup.call(this);

            this.scope = this.options.scope;

            this.filterData = this.options.filterData || {};

            var params = this.filterData.params || {};


            var customDisabled = false;
            var version = this.getConfig().get('version') || '';
            var arr = version.split('.');
            if (version !== 'dev' && arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) < 506) {
                customDisabled = true;
            }

            var functionList;
            if (!this.options.isHaving) {
                functionList = Espo.Utils.clone(this.getMetadata().get(['entityDefs', 'Report', 'complexExpressionFunctionList']) || []);
                if (!customDisabled) {
                    functionList.unshift('customWithOperator');
                    functionList.unshift('custom');
                }
                functionList.unshift('');
            } else {
                functionList = Espo.Utils.clone(this.getMetadata().get(['entityDefs', 'Report', 'complexExpressionHavingFunctionList']) || []);
                if (!customDisabled) {
                    functionList.unshift('customWithOperator');
                    functionList.unshift('custom');
                }
            }

            var operatorList;
            if (!this.options.isHaving) {
                operatorList = Espo.Utils.clone(this.getMetadata().get(['entityDefs', 'Report', 'complexExpressionOperatorList']) || []);
            } else {
                operatorList = Espo.Utils.clone(this.getMetadata().get(['entityDefs', 'Report', 'complexExpressionHavingOperatorList']) || []);
            }

            model.set({
                'function': params.function,
                attribute: params.attribute,
                operator: params.operator,
                expression: params.expression,
                value: params.value,
            });

            this.createView('function', 'views/fields/enum', {
                el: this.getSelector() + ' .function-container',
                params: {
                    options: functionList
                },
                name: 'function',
                model: model,
                mode: 'edit'
            }, function (view) {
                this.listenTo(view, 'after:render', function () {
                    view.$el.find('.form-control').addClass('input-sm');
                }, this);
            });

            this.createView('operator', 'views/fields/enum', {
                el: this.getSelector() + ' .operator-container',
                params: {
                    options: operatorList
                },
                name: 'operator',
                model: model,
                mode: 'edit'
            }, function (view) {
                this.listenTo(view, 'after:render', function () {
                    view.$el.find('.form-control').addClass('input-sm');
                }, this);
            });

            this.setupAttributes();

            this.createView('attribute', 'views/fields/enum', {
                el: this.getSelector() + ' .attribute-container',
                params: {
                    options: this.attributeList,
                    translatedOptions: this.translatedOptions
                },
                name: 'attribute',
                model: model,
                mode: 'edit'
            }, function (view) {
                this.listenTo(view, 'after:render', function () {
                    view.$el.find('.form-control').addClass('input-sm');
                }, this);
            });

            this.createView('value', 'views/fields/formula', {
                el: this.getSelector() + ' .value-container',
                params: {
                    height: 50
                },
                name: 'value',
                model: model,
                mode: 'edit'
            });

            this.createView('expression', 'views/fields/varchar', {
                el: this.getSelector() + ' .expression-container',
                name: 'expression',
                model: model,
                mode: 'edit',
            }, function (view) {
                this.listenTo(view, 'after:render', function () {
                    view.$el.find('.form-control').addClass('input-sm');
                }, this);
            });

            this.controlVisibility();
            this.listenTo(this.model, 'change:operator', function () {
                this.controlVisibility();
            }, this);
            this.listenTo(this.model, 'change:function', function () {
                this.controlVisibility();
            }, this);
        },

        controlVisibility: function () {
            var func = this.model.get('function');
            if (func === 'custom') {
                this.hideField('attribute');
                this.hideField('operator');
                this.hideField('value');
                this.showField('expression');
            } else if (func === 'customWithOperator') {
                this.hideField('attribute');
                this.showField('operator');
                this.showField('value');
                this.showField('expression');
            } else {
                this.hideField('expression');
                this.showField('attribute');
                this.showField('value');
                this.showField('operator');
            }

            if (func !== 'custom') {
                if (~['isNull', 'isNotNull', 'isTrue', 'isFalse'].indexOf(this.model.get('operator'))) {
                    this.hideField('value');
                } else {
                    this.showField('value');
                    if (this.getField('value') && this.getField('value').isRendered()) {
                        this.getField('value').reRender();
                    }
                }
            }
        },

        getAttributeListForScope: function (entityType, isLink) {
            var fieldList = this.getFieldManager().getScopeFieldList(entityType).filter(function (item) {
                var defs = this.getMetadata().get(['entityDefs', entityType, 'fields', item]) || {};
                if (defs.notStorable) return;
                if (!defs.type) return;

                var type = defs.type;

                if (defs.directAccessDisabled) return;
                if (defs.reportDisabled) return;
                if (defs.disabled) return;

                if (~['linkMultiple', 'email', 'phone'].indexOf(type)) return;


                if (this.options.isHaving) {
                    if (!~['int', 'float', 'currency', 'currencyConverted'].indexOf(type)) return;
                }

                if (this.getFieldManager().isScopeFieldAvailable && !this.getFieldManager().isScopeFieldAvailable(entityType, item)) {
                    return;
                }

                return true;
            }, this);

            var attributeList = [];

            fieldList.forEach(function (item) {
                var defs = this.getMetadata().get(['entityDefs', entityType, 'fields', item]) || {};

                if (this.options.isHaving) {
                    if (defs.type === 'currency') {
                        attributeList.push(item);
                        return;
                    }
                }
                this.getFieldManager().getAttributeList(defs.type, item).forEach(function (attr) {
                    if (~attributeList.indexOf(attr)) return;
                    attributeList.push(attr);
                }, this);
            }, this);

            if (this.options.isHaving) {
                attributeList.push('id');
            }

            attributeList.sort();

            return attributeList;
        },

        setupAttributes: function () {
            var entityType = this.scope;

            var attributeList = this.getAttributeListForScope(entityType);

            var links = this.getMetadata().get(['entityDefs', this.options.scope, 'links']);
            var linkList = [];
            Object.keys(links).forEach(function (link) {
                var type = links[link].type;
                if (!type) return;
                if (links[link].disabled) return;

                if (~['belongsToParent', 'hasOne', 'belongsTo'].indexOf(type)) {
                    linkList.push(link);
                }
                if (this.options.isHaving) {
                    if (type === 'hasMany') {
                        linkList.push(link);
                    }
                }
            }, this);
            linkList.sort();
            linkList.forEach(function (link) {
                var scope = links[link].entity;
                if (!scope) return;
                var linkAttributeList = this.getAttributeListForScope(scope, true);
                linkAttributeList.forEach(function (item) {
                    attributeList.push(link + '.' + item);
                }, this);
            }, this);

            this.attributeList = attributeList;

            this.setupTranslatedOptions();
        },

        setupTranslatedOptions: function () {
            this.translatedOptions = {};

            var entityType = this.scope;
            this.attributeList.forEach(function (item) {
                var field = item;
                var scope = entityType;
                var isForeign = false;
                if (~item.indexOf('.')) {
                    isForeign = true;
                    field = item.split('.')[1];
                    var link = item.split('.')[0];
                    scope = this.getMetadata().get('entityDefs.' + entityType + '.links.' + link + '.entity');
                }

                this.translatedOptions[item] = this.translate(field, 'fields', scope);

                if (field.indexOf('Id') === field.length - 2) {
                    var baseField = field.substr(0, field.length - 2);
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', baseField])) {
                        this.translatedOptions[item] = this.translate(baseField, 'fields', scope) + ' (' + this.translate('id', 'fields') + ')';
                    }
                } else if (field.indexOf('Name') === field.length - 4) {
                    var baseField = field.substr(0, field.length - 4);
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', baseField])) {
                        this.translatedOptions[item] = this.translate(baseField, 'fields', scope) + ' (' + this.translate('name', 'fields') + ')';
                    }
                } else if (field.indexOf('Type') === field.length - 4) {
                    var baseField = field.substr(0, field.length - 4);
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', baseField])) {
                        this.translatedOptions[item] = this.translate(baseField, 'fields', scope) + ' (' + this.translate('type', 'fields') + ')';
                    }
                }

                if (field.indexOf('Ids') === field.length - 3) {
                    var baseField = field.substr(0, field.length - 3);
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', baseField])) {
                        this.translatedOptions[item] = this.translate(baseField, 'fields', scope) + ' (' + this.translate('ids', 'fields') + ')';
                    }
                } else if (field.indexOf('Names') === field.length - 5) {
                    var baseField = field.substr(0, field.length - 5);
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', baseField])) {
                        this.translatedOptions[item] = this.translate(baseField, 'fields', scope) + ' (' + this.translate('names', 'fields') + ')';
                    }
                } else if (field.indexOf('Types') === field.length - 5) {
                    var baseField = field.substr(0, field.length - 5);
                    if (this.getMetadata().get(['entityDefs', scope, 'fields', baseField])) {
                        this.translatedOptions[item] = this.translate(baseField, 'fields', scope) + ' (' + this.translate('types', 'fields') + ')';
                    }
                }

                if (isForeign) {
                    this.translatedOptions[item] =  this.translate(link, 'links', entityType) + '.' + this.translatedOptions[item];
                }
            }, this);
        },

        fetch: function () {
            this.getView('function').fetchToModel();
            this.getView('attribute').fetchToModel();
            this.getView('operator').fetchToModel();
            this.getView('value').fetchToModel();
            this.getView('expression').fetchToModel();

            var expression = this.model.get('expression');
            var func = this.model.get('function') || null;
            var attribute = this.model.get('attribute');
            var operator = this.model.get('operator') || null;
            var value = this.model.get('value');

            if (func === 'custom') {
                attribute = null;
                operator = null;
                value = null;
            } else if (func === 'customWithOperator') {
                attribute = null;
            } else {
                expression = null;
            }

            var data = {
                id: this.filterData.id,
                type: 'complexExpression',
                params: {
                    'function': func,
                    'attribute': attribute,
                    'operator': operator,
                    'value': value,
                    'expression': expression,
                }
            };

            return data;
        }

    });
});
