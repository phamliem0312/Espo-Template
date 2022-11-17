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

Espo.define('advanced:views/workflow/action-fields/subjects/field', 'view', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/action-fields/subjects/field',

        data: function () {
            return {
                value: this.options.value,
                entityType: this.options.entityType,
                listHtml: this.listHtml,
                readOnly: this.readOnly
            };
        },

        setup: function () {
            var entityType = this.options.entityType;
            var scope = this.options.scope;
            var field = this.options.field;
            this.readOnly = this.options.readOnly;

            var foreignScope;

            var value = this.options.value;

            var fieldType = this.getMetadata().get('entityDefs.' + scope + '.fields.' + field + '.type') || 'base';

            var fieldTypeList = this.getMetadata().get('entityDefs.Workflow.fieldTypeComparison.' + fieldType) || [];

            if (fieldType == 'link' || fieldType == 'linkMultiple') {
                foreignScope = this.getMetadata().get('entityDefs.' + scope + '.links.' + field + '.entity');
            }

            if (this.readOnly) {
                if (~value.indexOf('.')) {
                    var values = value.split(".");
                    this.listHtml = this.translate('Field', 'labels', 'Workflow') + ': ' + this.translate(values[0], 'links', entityType) + '.' + this.translate(values[1], 'fields', foreignScope);
                } else {
                    this.listHtml = this.translate('Field', 'labels', 'Workflow') + ': ' + this.translate(value, 'fields', entityType);
                }
                return;
            }

            var list = [];
            var fieldDefs = this.getMetadata().get('entityDefs.' + entityType + '.fields');
            Object.keys(fieldDefs).forEach(function (f) {
                if ((fieldDefs[f].type == fieldType || ~fieldTypeList.indexOf(fieldDefs[f].type))) {
                    if (fieldDefs[f].directAccessDisabled) return;
                    if (fieldDefs[f].disabled) return;
                    if (fieldType == 'link' || fieldType == 'linkMultiple') {
                        var fScope = this.getMetadata().get('entityDefs.' + entityType + '.links.' + f + '.entity');
                        if (fScope != foreignScope) {
                            return;
                        }
                    }
                    list.push(f);
                }
            }, this);

            var listHtml = '';

            list.forEach(function (f, i) {
                if (i == 0) {
                    var label = this.translate('Target Entity', 'labels', 'Workflow') + ' (' + this.translate(entityType, 'scopeNames') + ')';
                    listHtml += '<optgroup label="' + label + '">';
                }

                var selectedHtml = '';
                if (value == f) {
                    selectedHtml = 'selected';
                }

                listHtml += '<option ' + selectedHtml + ' value="' + f + '">' + this.translate(f, 'fields', entityType) + '</option>';
                if (i == list.length - 1) {
                    listHtml += '</optgroup>';
                }
            }, this);

            var relatedFields = {};

            var linkDefs = this.getMetadata().get('entityDefs.' + entityType + '.links');
            Object.keys(linkDefs).forEach(function (link) {
                var list = [];
                if (linkDefs[link].type == 'belongsTo') {
                    if (linkDefs[link].disabled) return;
                    var foreignEntityType = linkDefs[link].entity;
                    if (!foreignEntityType) {
                        return;
                    }
                    var fieldDefs = this.getMetadata().get('entityDefs.' + foreignEntityType + '.fields');
                    Object.keys(fieldDefs).forEach(function (f) {
                        if (fieldDefs[f].type == fieldType || ~fieldTypeList.indexOf(fieldDefs[f].type)) {
                            if (fieldDefs[f].directAccessDisabled) return;
                            if (fieldDefs[f].disabled) return;

                            if (fieldType == 'link' || fieldType == 'linkMultiple') {
                                var fScope = this.getMetadata().get('entityDefs.' + foreignEntityType + '.links.' + f + '.entity');
                                if (fScope != foreignScope) {
                                    return;
                                }
                            }
                            list.push(f);
                        }
                    }, this);
                    relatedFields[link] = list;
                }
            }, this);

            for (var link in relatedFields) {
                relatedFields[link].forEach(function (f, i) {
                    if (i == 0) {
                        listHtml += '<optgroup label="' + this.translate(link, 'links', entityType) + '">';
                    }

                    var selectedHtml = false;
                    if (value == link + '.' + f) {
                        selectedHtml = 'selected';
                    }

                    listHtml += '<option ' + selectedHtml + ' value="' + link + '.' + f + '">' + this.translate(link, 'links', entityType) + '.' + this.translate(f, 'fields', linkDefs[link].entity) + '</option>';
                    if (i == relatedFields[link].length - 1) {
                        listHtml += '</optgroup>';
                    }
                }, this);
            }

            this.listHtml = listHtml;
        },

    });
});
