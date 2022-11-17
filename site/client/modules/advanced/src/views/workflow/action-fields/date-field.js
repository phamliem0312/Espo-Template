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

Espo.define('advanced:views/workflow/action-fields/date-field', 'view', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/action-fields/date-field',

        data: function () {
            return {
                value: this.options.value,
                entityType: this.entityType,
                listHtml: this.listHtml,
                readOnly: this.readOnly
            };
        },

        setup: function () {
            this.entityType = this.options.entityType;
            this.readOnly = this.options.readOnly;

            if (this.readOnly) {
                this.buildReadOnlyHtml();
            } else {
                this.buildListHtml();
            }
        },

        buildReadOnlyHtml: function () {

            var value = this.options.value;
            var listHtml = this.translate('today', 'labels', 'Workflow');

            if (value) {
                var entityType = this.entityType;

                if (~value.indexOf('.')) {
                    var splits = value.split('.');
                    var linkDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.links.' + splits[0]);
                    entityType = linkDefs['entity'] || entityType;
                    value = splits[1];
                }

                listHtml = this.translate(entityType, 'scopeNames') + '.' + this.translate(value, 'fields', entityType);
            }

            this.listHtml = listHtml;
        },

        buildListHtml: function () {
            var fieldTypeList = ['date', 'datetime'];

            var list = [];
            var fieldDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.fields') || {};
            Object.keys(fieldDefs).forEach(function (f) {
                if ((~fieldTypeList.indexOf(fieldDefs[f].type))) {
                    list.push(f);
                }
            }, this);

            var listHtml = '';

            listHtml += '<option value="">' + this.translate('today', 'labels', 'Workflow') + '</option>';

            list.forEach(function (f, i) {
                if (i == 0) {
                    listHtml += '<optgroup label="' + this.translate(this.entityType, 'scopeNames') + '">';
                }
                listHtml += '<option value="' + f + '">' + this.translate(f, 'fields', this.entityType) + '</option>';
                if (i == list.length - 1) {
                    listHtml += '</optgroup>';
                }
            }, this);

            var relatedFields = {};

            var linkDefs = this.getMetadata().get('entityDefs.' + this.entityType + '.links');
            Object.keys(linkDefs).forEach(function (link) {
                var list = [];
                if (linkDefs[link].type == 'belongsTo') {
                    var foreignEntityType = linkDefs[link].entity;
                    if (!foreignEntityType) {
                        return;
                    }
                    var fieldDefs = this.getMetadata().get('entityDefs.' + foreignEntityType + '.fields') || {};
                    Object.keys(fieldDefs).forEach(function (f) {
                        if (~fieldTypeList.indexOf(fieldDefs[f].type)) {
                            list.push(f);
                        }
                    }, this);
                    relatedFields[link] = list;
                }
            }, this);

            for (var link in relatedFields) {
                relatedFields[link].forEach(function (f, i) {
                    if (i == 0) {
                        listHtml += '<optgroup label="' + this.translate(link, 'links', this.entityType) + '">';
                    }
                    listHtml += '<option value="' + link + '.' + f + '">' + this.translate(f, 'fields', linkDefs[link].entity) + '</option>';
                    if (i == relatedFields[link].length - 1) {
                        listHtml += '</optgroup>';
                    }
                }, this);
            }

            this.listHtml = listHtml;
        },

        afterRender: function () {
            if (this.options.value) {
                this.$el.find('[name="executionField"]').val(this.options.value);
            }
        },

    });
});

