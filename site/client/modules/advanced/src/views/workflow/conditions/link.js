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

Espo.define('advanced:views/workflow/conditions/link', 'advanced:views/workflow/conditions/base', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/conditions/base',

        defaultConditionData: {
            comparison: 'notEmpty'
        },

        comparisonList: [
            'notEmpty',
            'isEmpty',
            'equals',
            'notEquals',
            'changed',
            'notChanged'
        ],

        setupComparisonList: function () {
            Dep.prototype.setupComparisonList.call(this)
            if (this.fieldType === 'image' || this.fieldType === 'file') {
                var comparisonList = [];
                Espo.Utils.clone(this.comparisonList).forEach(function (item) {
                    if (~['equals', 'notEquals', 'wasEqual', 'wasNotEqual'].indexOf(item)) return;
                    comparisonList.push(item);
                }, this);
                this.comparisonList = comparisonList;
            }
        },

        data: function () {
            return _.extend({
            }, Dep.prototype.data.call(this));
        },

        getSubjectInputViewName: function (subjectType) {
            return 'advanced:views/workflow/condition-fields/subjects/link';
        },

    });
});
