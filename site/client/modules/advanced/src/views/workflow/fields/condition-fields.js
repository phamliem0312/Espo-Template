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

Espo.define('advanced:views/workflow/fields/condition-fields', 'views/fields/multi-enum', function (Dep) {

    return Dep.extend({

        getItemList: function () {
            var entityType = this.model.targetEntityType;

            var conditionFieldTypes = this.getMetadata().get('entityDefs.Workflow.conditionFieldTypes') || {};

            var fields = this.getMetadata().get('entityDefs.' + entityType + '.fields');
            var itemList = Object.keys(fields).filter(function (field) {
                if (!fields[field].type) return;
                if (!(fields[field].type in conditionFieldTypes)) return;
                if (fields[field].disabled) return;
                if (fields[field].workflowConditionDisabled) return;
                if (fields[field].directAccessDisabled) return;
                return true;
            }, this);

            itemList.sort(function (v1, v2) {
                return this.translate(v1, 'fields', entityType).localeCompare(this.translate(v2, 'fields', entityType));
            }.bind(this));

            var links = this.getMetadata().get('entityDefs.' + entityType + '.links') || {};

            var linkList = Object.keys(links).sort(function (v1, v2) {
                return this.translate(v1, 'links', entityType).localeCompare(this.translate(v2, 'links', entityType));
            }.bind(this));

            linkList.forEach(function (link) {
                var type = links[link].type
                if (type != 'belongsTo') return;
                var scope = links[link].entity;
                if (!scope) return;

                var fields = this.getMetadata().get('entityDefs.' + scope + '.fields') || {};
                var foreignItemList = Object.keys(fields).filter(function (field) {
                    if (!fields[field].type) return;
                    if (!(fields[field].type in conditionFieldTypes)) return;
                    if (fields[field].disabled) return;
                    if (fields[field].workflowConditionDisabled) return;
                    if (fields[field].directAccessDisabled) return;
                    return true;
                }, this);
                foreignItemList.sort(function (v1, v2) {
                    return this.translate(v1, 'fields', scope).localeCompare(this.translate(v2, 'fields', scope));
                }.bind(this));

                foreignItemList.forEach(function (item) {
                    itemList.push(link + '.' + item);
                }, this);
            }, this);

            if (this.options.createdEntitiesData) {
                var createdAliasIdList = Object.keys(this.options.createdEntitiesData);
                createdAliasIdList.sort(function (v1, v2) {
                    var entityType1 = this.options.createdEntitiesData[v1].entityType || '';
                    var entityType2 = this.options.createdEntitiesData[v2].entityType || '';

                    return this.translate(entityType1, 'scopeNames').localeCompare(this.translate(entityType2, 'scopeNames'));
                }.bind(this));

                createdAliasIdList.forEach(function (aliasId) {
                    var item = this.options.createdEntitiesData[aliasId];
                    var entityType = item.entityType;
                    var link = item.link;

                    var fields = this.getMetadata().get(['entityDefs', entityType, 'fields']) || {};
                    var foreignItemList = Object.keys(fields).filter(function (field) {
                        var defs = fields[field];
                        if (!defs.type) return;
                        if (!(defs.type in conditionFieldTypes)) return;
                        if (defs.disabled) return;
                        if (defs.workflowConditionDisabled) return;
                        return true;
                    }, this);
                    foreignItemList.sort(function (v1, v2) {
                        return this.translate(v1, 'fields', entityType).localeCompare(this.translate(v2, 'fields', entityType));
                    }.bind(this));

                    foreignItemList.forEach(function (item) {
                        itemList.push('created:' + aliasId + '.' + item);
                    }, this);
                }, this);
            }

            return itemList;
        },

        setupTranslatedOptions: function () {
            this.translatedOptions = {};

            var entityType = this.model.targetEntityType;
            this.params.options.forEach(function (item) {
                var field = item;
                var scope = entityType;
                var isForeign = false;
                var isCreated = false;
                if (~item.indexOf('.')) {
                    if (item.indexOf('created:') === 0) {
                        isCreated = true;
                        field = item.split('.')[1];
                        var aliasId = item.split('.')[0].substr(8);
                        scope = this.options.createdEntitiesData[aliasId].entityType;
                        var numberId = this.options.createdEntitiesData[aliasId].numberId;
                        var link = this.options.createdEntitiesData[aliasId].link;
                        var text = this.options.createdEntitiesData[aliasId].text;
                    } else {
                        isForeign = true;
                        field = item.split('.')[1];
                        var link = item.split('.')[0];
                        scope = this.getMetadata().get('entityDefs.' + entityType + '.links.' + link + '.entity');
                    }
                }
                this.translatedOptions[item] = this.translate(field, 'fields', scope);
                if (isForeign) {
                    this.translatedOptions[item] =  this.translate(link, 'links', entityType) + '.' + this.translatedOptions[item];
                } else if (isCreated) {
                    var labelLeftPart = this.translate('Created', 'labels', 'Workflow') + ': ';
                    if (link) {
                        labelLeftPart += this.translate(link, 'links', entityType) + ' - ';
                    }
                    labelLeftPart += this.translate(scope, 'scopeNames');
                    if (text) {
                        labelLeftPart += ' \'' + text + '\'';
                    } else {
                        if (numberId) {
                            labelLeftPart += ' #' + numberId.toString();
                        }
                    }
                    this.translatedOptions[item] = labelLeftPart + '.' + this.translatedOptions[item];
                }
            }, this);
        },

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            this.params.options = this.getItemList();
            this.setupTranslatedOptions();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (this.$element && this.$element[0] && this.$element[0].selectize) {
                this.$element[0].selectize.focus();
            }
        }

    });

});
