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

Espo.define('advanced:views/report/fields/order-by-list', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            var entityType = this.model.get('entityType');
            var itemList = [];

            itemList.push('');

            var fields = this.getMetadata().get('entityDefs.' + entityType + '.fields') || {};
            Object.keys(fields).forEach(function (field) {
                if (fields[field].disabled) return;
                if (fields[field].reportDisabled) return;
                if (fields[field].reportOrderByDisabled) return;
                if (fields[field].directAccessDisabled) return;

                if (fields[field].type == 'linkMultiple') return;
                if (fields[field].type == 'map') return;

                if (this.getFieldManager().isScopeFieldAvailable && !this.getFieldManager().isScopeFieldAvailable(entityType, field)) {
                    return;
                }

                itemList.push('ASC:' + field);
                itemList.push('DESC:' + field);
            }, this);

            this.params.options = itemList;

            this.setupTranslatedOptions();
        },

        setupTranslatedOptions: function () {
            this.translatedOptions = {};

            this.translatedOptions[''] = this.translate('Default');

            this.params.options.forEach(function (item) {
                if (item === '') return;

                var order = item.substr(0, item.indexOf(':'));
                var p = item.substr(item.indexOf(':') + 1);

                var scope = this.model.get('entityType');
                var entityType = scope;

                var field = p;

                var func = false;
                var link = false;

                if (~p.indexOf(':')) {
                    func = p.split(':')[0];
                    p = field = p.split(':')[1];
                }

                if (~p.indexOf('.')) {
                    link = p.split('.')[0];
                    field = p.split('.')[1];
                    scope = this.getMetadata().get('entityDefs.' + entityType + '.links.' + link + '.entity');
                }
                this.translatedOptions[item] = this.translate(field, 'fields', scope);
                if (link) {
                    this.translatedOptions[item] = this.translate(link, 'links', entityType) + '.' + this.translatedOptions[item];
                }

                if (order != 'LIST') {
                    this.translatedOptions[item] = this.translatedOptions[item] + ' (' + this.translate(order, 'orders', 'Report').toUpperCase() + ')';
                }
            }, this);
        },

    });

});

