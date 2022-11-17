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

Espo.define('advanced:views/report-panel/list', 'views/list', function (Dep) {

    return Dep.extend({

        setup: function () {
            this.featureIsDisbled = false;

            var version = this.getConfig().get('version') || '';

            var arr = version.split('.');
            if (version !== 'dev' && arr.length > 2) {
                if (parseInt(arr[0]) < 5 || parseInt(arr[0]) === 5 && parseInt(arr[1]) === 0) {
                    this.featureIsDisbled = true;
                }
            }

            if (this.featureIsDisbled) {
                this._template = 'Report Panels are available only with EspoCRM version 5.1.0 and above.'
            }

            Dep.prototype.setup.call(this);
        },

        actionRebuildPanels: function () {
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));
            this.ajaxPostRequest('ReportPanel/action/rebuild', {}).then(function (view) {
                Espo.Ui.success(this.translate('Done'));
            }.bind(this));
        }
    });
});
