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

Espo.define('advanced:views/bpmn-flowchart-element/fields/task-send-message-from', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            this.params.options = Espo.Utils.clone(this.params.options);

            var linkOptionList = this.getLinkOptionList(true, true);
            linkOptionList.forEach(function (item) {
                this.params.options.push(item);
            }, this);

            this.translateOptions();
        },

        translateOptions: function () {
            this.translatedOptions = {};
            var entityType = this.model.targetEntityType;

            this.params.options.forEach(function (item) {
                if (item.indexOf('link:') === 0) {
                    var link = item.substring(5);
                    if (~link.indexOf('.')) {
                        var arr = link.split('.');
                        link = arr[0];
                        var subLink = arr[1];
                        if (subLink === 'followers') {
                            this.translatedOptions[item] = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links', entityType) +
                                '.' + this.translate('Followers');
                            return;
                        }
                        var relatedEntityType = this.getMetadata().get(['entityDefs', entityType, 'links', link, 'entity']);
                        this.translatedOptions[item] = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links', entityType) +
                            '.' + this.translate(subLink, 'links', relatedEntityType);
                        return;
                    } else {
                        this.translatedOptions[item] = this.translate('Related', 'labels', 'Workflow') + ': ' + this.translate(link, 'links', entityType);
                        return;
                    }
                } else {
                    this.translatedOptions[item] = this.getLanguage().translateOption(item, 'emailAddress', 'BpmnFlowchartElement');
                    return;
                }
            }, this);

            this.translatedOptions['targetEntity'] =
                this.getLanguage().translateOption('targetEntity', 'emailAddress', 'BpmnFlowchartElement') + ': ' + this.translate(entityType, 'scopeNames') + '';
        },

        getLinkOptionList: function (onlyUser, noMultiple) {
            var list = [];

            var entityType = this.model.targetEntityType;

            Object.keys(this.getMetadata().get(['entityDefs', entityType, 'links']) || {}).forEach(function (link) {
                var defs = this.getMetadata().get(['entityDefs', entityType, 'links', link]) || {};
                if (defs.type === 'belongsTo' || defs.type === 'hasMany') {
                    var foreignEntityType = defs.entity;
                    if (!foreignEntityType) {
                        return;
                    }
                    if (defs.type === 'hasMany') {
                        if (noMultiple) return;
                        if (this.getMetadata().get(['entityDefs', entityType, 'fields', link, 'type']) !== 'linkMultiple') {
                            return;
                        }
                    }
                    if (onlyUser && foreignEntityType !== 'User') return;
                    var fieldDefs = this.getMetadata().get(['entityDefs', foreignEntityType, 'fields']) || {};
                    if ('emailAddress' in fieldDefs && fieldDefs.emailAddress.type === 'email') {
                        list.push('link:' + link);
                    }
                } else if (defs.type == 'belongsToParent') {
                    if (onlyUser) return;
                    list.push('link:' + link);
                }
            }, this);

            Object.keys(this.getMetadata().get(['entityDefs', entityType, 'links']) || {}).forEach(function (link) {
                var defs = this.getMetadata().get(['entityDefs', entityType, 'links', link]) || {};
                if (defs.type !== 'belongsTo') return;
                var foreignEntityType = this.getMetadata().get(['entityDefs', entityType, 'links', link, 'entity']);
                if (!foreignEntityType) return;
                if (foreignEntityType === 'User') return;

                if (!noMultiple) {
                    if (this.getMetadata().get(['scopes', foreignEntityType, 'stream'])) {
                        list.push('link:' + link + '.followers');
                    }
                }

                Object.keys(this.getMetadata().get(['entityDefs', foreignEntityType, 'links']) || {}).forEach(function (subLink) {
                    var subDefs = this.getMetadata().get(['entityDefs', foreignEntityType, 'links', subLink]) || {};
                    if (subDefs.type === 'belongsTo' || subDefs.type === 'hasMany') {
                        var subForeignEntityType = subDefs.entity;
                        if (!subForeignEntityType) {
                            return;
                        }
                        if (subDefs.type == 'hasMany') {
                            if (this.getMetadata().get(['entityDefs', subForeignEntityType, 'fields', subLink, 'type']) !== 'linkMultiple') {
                                return;
                            }
                        }
                        if (onlyUser && subForeignEntityType !== 'User') return;
                        var fieldDefs = this.getMetadata().get(['entityDefs', subForeignEntityType, 'fields']) || {};
                        if ('emailAddress' in fieldDefs && fieldDefs.emailAddress.type === 'email') {
                            list.push('link:' + link + '.' + subLink);
                        }
                    }
                }, this);
            }, this);

            Object.keys(this.getMetadata().get(['entityDefs', entityType, 'links']) || {}).forEach(function (link) {
                if (this.getMetadata().get(['entityDefs', entityType, 'links', link, 'type']) === 'belongsToParent') {
                    list.push('link:' + link + '.' + 'assignedUser');
                    if (!onlyUser) {
                        list.push('link:' + link + '.' + 'followers');
                    }
                }
            }, this);

            return list;
        }
    });

});