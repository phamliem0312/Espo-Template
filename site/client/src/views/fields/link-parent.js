/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

define('views/fields/link-parent', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'linkParent',

        listTemplate: 'fields/link-parent/list',

        detailTemplate: 'fields/link-parent/detail',

        editTemplate: 'fields/link-parent/edit',

        searchTemplate: 'fields/link-parent/search',

        listLinkTemplate: 'fields/link-parent/list-link',

        nameName: null,

        idName: null,

        foreignScopeList: null,

        autocompleteDisabled: false,

        selectRecordsView: 'views/modals/select-records',

        createDisabled: false,

        searchTypeList: ['is', 'isEmpty', 'isNotEmpty'],

        initialSearchIsNotIdle: true,

        data: function () {
            var nameValue = this.model.get(this.nameName);

            if (!nameValue && this.model.get(this.idName) && this.model.get(this.typeName)) {
                nameValue = this.translate(this.model.get(this.typeName), 'scopeNames');
            }

            var iconHtml = null;

            if (
                (
                    this.mode === 'detail' ||
                    this.mode === 'list' && this.displayScopeColorInListMode
                ) &&
                this.foreignScope
            ) {
                iconHtml = this.getHelper().getScopeColorIconHtml(this.foreignScope);
            }

            return _.extend({
                idName: this.idName,
                nameName: this.nameName,
                typeName: this.typeName,
                idValue: this.model.get(this.idName),
                nameValue: nameValue,
                typeValue: this.model.get(this.typeName),
                foreignScope: this.foreignScope,
                foreignScopeList: this.foreignScopeList,
                valueIsSet: this.model.has(this.idName) || this.model.has(this.typeName),
                iconHtml: iconHtml,
                displayEntityType: this.displayEntityType && this.model.get(this.typeName),
            }, Dep.prototype.data.call(this));
        },

        getSelectFilters: function () {},

        getSelectBoolFilterList: function () {
            return this.selectBoolFilterList;
        },

        getSelectPrimaryFilterName: function () {
            return this.selectPrimaryFilterName;
        },

        getCreateAttributes: function () {},

        setup: function () {
            this.nameName = this.name + 'Name';
            this.typeName = this.name + 'Type';
            this.idName = this.name + 'Id';

            this.foreignScopeList = this.options.foreignScopeList || this.foreignScopeList;

            this.foreignScopeList = this.foreignScopeList ||
                this.params.entityList ||
                this.model.getLinkParam(this.name, 'entityList') || [];

            this.foreignScopeList = Espo.Utils.clone(this.foreignScopeList).filter(item => {
                if (!this.getMetadata().get(['scopes', item, 'disabled'])) {
                    return true;
                }
            });

            this.foreignScope = this.model.get(this.typeName) || this.foreignScopeList[0];

            if (this.foreignScope && !~this.foreignScopeList.indexOf(this.foreignScope)) {
                this.foreignScopeList.unshift(this.foreignScope);
            }

            this.listenTo(this.model, 'change:' + this.typeName, () => {
                this.foreignScope = this.model.get(this.typeName) || this.foreignScopeList[0];
            });

            if ('createDisabled' in this.options) {
                this.createDisabled = this.options.createDisabled;
            }

            if (this.mode !== 'list') {
                this.addActionHandler('selectLink', () => {
                    this.notify('Loading...');

                    var viewName = this.getMetadata().get('clientDefs.' + this.foreignScope + '.modalViews.select') ||
                        this.selectRecordsView;

                    this.createView('dialog', viewName, {
                        scope: this.foreignScope,
                        createButton: !this.createDisabled && this.mode !== 'search',
                        filters: this.getSelectFilters(),
                        boolFilterList: this.getSelectBoolFilterList(),
                        primaryFilterName: this.getSelectPrimaryFilterName(),
                        createAttributes: (this.mode === 'edit') ? this.getCreateAttributes() : null,
                        mandatorySelectAttributeList: this.getMandatorySelectAttributeList(),
                        forceSelectAllAttributes: this.isForceSelectAllAttributes(),
                    }, (dialog) => {
                        dialog.render();

                        Espo.Ui.notify(false);

                        this.listenToOnce(dialog, 'select', (model) => {
                            this.clearView('dialog');
                            this.select(model);
                        });
                    });
                });

                this.addActionHandler('clearLink', () => {
                    if (this.foreignScopeList.length) {
                        this.foreignScope = this.foreignScopeList[0];
                        this.$elementType.val(this.foreignScope);
                    }

                    this.$elementName.val('');
                    this.$elementId.val('');
                    this.trigger('change');
                });

                this.events['change select[data-name="'+this.typeName+'"]'] = (e) => {
                    this.foreignScope = e.currentTarget.value;
                    this.$elementName.val('');
                    this.$elementId.val('');
                };
            }
        },

        setupSearch: function () {
            var type = this.getSearchParamsData().type;

            if (type === 'is' || !type) {
                this.searchData.idValue = this.getSearchParamsData().idValue || this.searchParams.valueId;
                this.searchData.nameValue = this.getSearchParamsData().nameValue || this.searchParams.valueName;
                this.searchData.typeValue = this.getSearchParamsData().typeValue || this.searchParams.valueType;
            }

            this.events = _.extend({
                'change select.search-type': (e) => {
                    var type = $(e.currentTarget).val();
                    this.handleSearchType(type);
                },
            }, this.events || {});
        },

        handleSearchType: function (type) {
            if (~['is'].indexOf(type)) {
                this.$el.find('div.primary').removeClass('hidden');
            } else {
                this.$el.find('div.primary').addClass('hidden');
            }
        },

        select: function (model) {
            this.$elementName.val(model.get('name') || model.id);
            this.$elementId.val(model.get('id'));
            this.trigger('change');
        },

        getMandatorySelectAttributeList: function () {
            return this.mandatorySelectAttributeList;
        },

        isForceSelectAllAttributes: function () {
            return this.forceSelectAllAttributes;
        },

        getAutocompleteMaxCount: function () {
            if (this.autocompleteMaxCount) {
                return this.autocompleteMaxCount;
            }

            return this.getConfig().get('recordsPerPage');
        },

        getAutocompleteUrl: function () {
            var url = this.foreignScope + '?maxSize=' + this.getAutocompleteMaxCount();

            if (!this.isForceSelectAllAttributes()) {
                var select = ['id', 'name'];

                if (this.getMandatorySelectAttributeList()) {
                    select = select.concat(this.getMandatorySelectAttributeList());
                }

                url += '&select=' + select.join(',');
            }

            var boolList = this.getSelectBoolFilterList();

            if (boolList) {
                url += '&' + $.param({'boolFilterList': boolList});
            }

            var primary = this.getSelectPrimaryFilterName();

            if (primary) {
                url += '&' + $.param({'primaryFilter': primary});
            }

            return url;
        },

        afterRender: function () {
            if (this.mode === 'edit' || this.mode === 'search') {
                this.$elementId = this.$el.find('input[data-name="' + this.idName + '"]');
                this.$elementName = this.$el.find('input[data-name="' + this.nameName + '"]');
                this.$elementType = this.$el.find('select[data-name="' + this.typeName + '"]');

                this.$elementName.on('change', () => {
                    if (this.$elementName.val() === '') {
                        this.$elementName.val('');
                        this.$elementId.val('');
                        this.trigger('change');
                    }
                });

                this.$elementType.on('change', () => {
                    this.$elementName.val('');
                    this.$elementId.val('');
                    this.trigger('change');
                });

                this.$elementName.on('blur', e => {
                    setTimeout(() => {
                        if (this.mode === 'edit') {
                            e.currentTarget.value = this.model.get(this.nameName) || '';
                        }
                    }, 100);

                    if (!this.autocompleteDisabled) {
                        setTimeout(() => this.$elementName.autocomplete('clear'), 300);
                    }
                });

                if (!this.autocompleteDisabled) {
                    this.$elementName.autocomplete({
                        serviceUrl: (q) => {
                            return this.getAutocompleteUrl(q);
                        },
                        minChars: 1,
                        paramName: 'q',
                        noCache: true,
                        triggerSelectOnValidInput: false,
                        autoSelectFirst: true,
                        beforeRender: ($c) => {
                            if (this.$elementName.hasClass('input-sm')) {
                                $c.addClass('small');
                            }
                        },
                        formatResult: (suggestion) => {
                            return this.getHelper().escapeString(suggestion.name);
                        },
                        transformResult: (response) => {
                            var response = JSON.parse(response);
                            var list = [];

                            response.list.forEach(item => {
                                list.push({
                                    id: item.id,
                                    name: item.name || item.id,
                                    data: item.id,
                                    value: item.name || item.id,
                                    attributes: item
                                });
                            });

                            return {
                                suggestions: list
                            };
                        },
                        onSelect: (s) => {
                            this.getModelFactory().create(this.foreignScope, (model) => {
                                model.set(s.attributes);

                                this.select(model);
                            });
                        },
                    });

                    this.$elementName.off('focus.autocomplete');
                    this.$elementName.on('focus', () => this.$elementName.get(0).select());

                    this.$elementName.attr('autocomplete', 'espo-' + this.name);
                }

                var $elementName = this.$elementName;

                this.once('render', () => {
                    $elementName.autocomplete('dispose');
                });

                this.once('remove', () => {
                    $elementName.autocomplete('dispose');
                });
            }

            if (this.mode === 'search') {
                var type = this.$el.find('select.search-type').val();

                this.handleSearchType(type);

                this.$el.find('select.search-type').on('change', () => {
                    this.trigger('change');
                });
            }
        },

        getValueForDisplay: function () {
            return this.model.get(this.nameName);
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (this.model.get(this.idName) === null || !this.model.get(this.typeName)) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.getLabelText());

                    this.showValidationMessage(msg);

                    return true;
                }
            }
        },

        fetch: function () {
            var data = {};

            data[this.typeName] = this.$elementType.val() || null;
            data[this.nameName] = this.$elementName.val() || null;
            data[this.idName] = this.$elementId.val() || null;

            if (data[this.idName] === null) {
                data[this.typeName] = null;
            }

            return data;
        },

        fetchSearch: function () {
            var type = this.$el.find('select.search-type').val();

            if (type === 'isEmpty') {
                var data = {
                    type: 'isNull',
                    field: this.idName,
                    data: {
                        type: type
                    }
                };

                return data;
            }

            if (type === 'isNotEmpty') {
                var data = {
                    type: 'isNotNull',
                    field: this.idName,
                    data: {
                        type: type
                    }
                };

                return data;
            }

            var entityType = this.$elementType.val();
            var entityName = this.$elementName.val()
            var entityId = this.$elementId.val();

            if (!entityType) {
                return false;
            }

            var data;
            if (entityId) {
                data = {
                    type: 'and',
                    attribute: this.idName,
                    value: [
                        {
                            type: 'equals',
                            field: this.idName,
                            value: entityId
                        },
                        {
                            type: 'equals',
                            field: this.typeName,
                            value: entityType
                        }
                    ],
                    data: {
                        type: 'is',
                        idValue: entityId,
                        nameValue: entityName,
                        typeValue: entityType
                    }
                };
            } else {
                data = {
                    type: 'and',
                    attribute: this.idName,
                    value: [
                        {
                            type: 'isNotNull',
                            field: this.idName
                        },
                        {
                            type: 'equals',
                            field: this.typeName,
                            value: entityType
                        }
                    ],
                    data: {
                        type: 'is',
                        typeValue: entityType
                    }
                };
            }
            return data;
        },

        getSearchType: function () {
            return this.getSearchParamsData().type || this.searchParams.typeFront;
        },
    });
});