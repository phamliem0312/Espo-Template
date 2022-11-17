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

define('advanced:views/workflow/conditions/int', 'advanced:views/workflow/conditions/base', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/conditions/base',

        comparisonList: [
            'equals',
            'wasEqual',
            'notEquals',
            'wasNotEqual',
            'greaterThan',
            'lessThan',
            'greaterThanOrEquals',
            'lessThanOrEquals',
            'isEmpty',
            'notEmpty',
            'changed',
            'notChanged',
        ],

        defaultConditionData: {
            comparison: 'equals',
            subjectType: 'value',
        },

        fetchSubject: function () {
            var $subject = this.$el.find('[data-name="subject"]');

            delete this.conditionData.value;
            delete this.conditionData.field;

            if ($subject.length) {
                switch (this.conditionData.subjectType) {
                    case 'field':
                        this.conditionData.field = $subject.val();

                        break;

                    case 'value':
                        var value = $subject.val();

                        if (value === '') {
                            value = null;
                        } else {
                            value = parseInt(value)
                        }

                        this.conditionData.value = value;

                        break;
                }
            }
        },

        getSubjectValue: function () {
            return this.conditionData.value;
        }
    });
});
