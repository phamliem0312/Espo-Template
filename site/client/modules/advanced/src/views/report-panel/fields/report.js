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

Espo.define('advanced:views/report-panel/fields/report', ['views/fields/link', 'advanced:report-helper'], function (Dep, ReportHelper) {

    return Dep.extend({

        createDisabled: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.reportHelper = new ReportHelper(
                this.getMetadata(),
                this.getLanguage(),
                this.getDateTime(),
                this.getConfig(),
                this.getPreferences()
            );
        },

        select: function (model) {
            this.model.set('reportType', model.get('type'), {isManual: true});
            this.model.set('reportEntityType', model.get('entityType'));

            if (model.get('type') !== 'Grid') {
                if (model.get('type') == 'List')
                    this.model.set('displayTotal', false);
                this.model.set('column', null);
            } else {
                var column = null;
                var columns = model.get('columns') || [];
                if (columns.length) {
                    column = columns[0];
                }

                columns = columns.filter(function (item) {
                    return this.reportHelper.isColumnNumeric(item, model.get('entityType'));
                }, this);

                if ((model.get('groupBy') || []).length < 2 && columns.length > 1) {
                    columns.unshift('');
                }

                this.model.set('column', column);
                this.model.trigger('update-columns', columns);
            }

            Dep.prototype.select.call(this, model);
        },

        clearLink: function () {
            Dep.prototype.clearLink.call(this);
            this.model.set('reportType', null, {isManual: true});
            this.model.set('displayTotal', false);
        }
    });
});
