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

define('advanced:views/workflow/conditions/link-parent', 'advanced:views/workflow/conditions/base', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/conditions/base',

        defaultConditionData: {
            comparison: 'notEmpty',
        },

        comparisonList: [
            'notEmpty',
            'isEmpty',
            'equals',
            'notEquals',
            'changed',
            'notChanged',
        ],

        data: function () {
            return _.extend({
            }, Dep.prototype.data.call(this));
        },

        getSubjectInputViewName: function (subjectType) {
            return 'advanced:views/workflow/condition-fields/subjects/link-parent';
        },

        handleSubjectType: function (subjectType, noFetch) {
            if (subjectType === 'typeOf') {
                this.createView('subject', 'advanced:views/workflow/condition-fields/subjects/link-parent-is-type-of', {
                    el: this.options.el + ' .subject',
                    entityType: this.entityType,
                    field: this.field,
                    value: this.getSubjectValue(),
                    conditionData: this.conditionData,
                    readOnly: this.readOnly,
                }, function (view) {
                    view.render(function () {
                        if (!noFetch) {
                            this.fetch();
                        }
                    }.bind(this));
                });
                return;
            }

            Dep.prototype.handleSubjectType.call(this, subjectType, noFetch);
        },

        handleComparison: function (comparison, noFetch) {
            if (comparison == 'equals' || comparison == 'notEquals') {
                this.$el.find('.subject').empty();
                    this.createView('subjectType', 'advanced:views/workflow/condition-fields/subject-type-parent', {
                        el: this.options.el + ' .subject-type',
                        value: this.conditionData.subjectType,
                        readOnly: this.readOnly
                    }, function (view) {
                        view.render(function() {
                            if (!noFetch) {
                                this.fetch();
                            }
                            this.handleSubjectType(this.conditionData.subjectType, noFetch);
                        }.bind(this));
                    }.bind(this));
                return;
            }

            Dep.prototype.handleComparison.call(this, comparison, noFetch);
        },

    });
});
