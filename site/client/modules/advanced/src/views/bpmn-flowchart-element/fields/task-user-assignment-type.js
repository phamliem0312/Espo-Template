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

Espo.define('advanced:views/bpmn-flowchart-element/fields/task-user-assignment-type', ['views/fields/enum', 'advanced:views/bpmn-flowchart-element/fields/task-send-message-from'], function (Dep, From) {

    return Dep.extend({

        setupOptions: function () {
            Dep.prototype.setupOptions.call(this);

            this.params.options = Espo.Utils.clone(this.params.options);

            var linkOptionList = From.prototype.getLinkOptionList.call(this, true, true);
            linkOptionList.forEach(function (item) {
                this.params.options.push(item);
            }, this);

            this.translateOptions(this);
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
                    this.translatedOptions[item] = this.getLanguage().translateOption(item, 'assignmentType', 'BpmnFlowchartElement')
                    return;
                }
            }, this);
        }

    });

});