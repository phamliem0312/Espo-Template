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

define('advanced:views/report/detail', 'views/detail', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var version = this.getConfig().get('version') || '';
            var arr = version.split('.');

            if (
                version === 'dev' || arr.length > 2 && parseInt(arr[0]) * 100 + parseInt(arr[1]) >= 506 ||
                version === '@@version'
            ) {
                var iconHtml;
                if (~['Grid', 'JointGrid'].indexOf(this.model.get('type'))) {
                    iconHtml = '<span class="fas fa-chart-bar"></span> ';
                } else {
                    iconHtml = '';
                }

                this.addMenuItem('buttons', {
                    action: 'show',
                    link: '#Report/show/' + this.model.id,
                    html: iconHtml + this.translate('Results View', 'labels', 'Report'),
                });
            }
        },

        actionShow: function () {
            var options = {
                id: this.model.id,
                model: this.model
            };

            var rootUrl = this.options.rootUrl || this.options.params.rootUrl;
            if (rootUrl) {
                options.rootUrl = rootUrl;
            }

            this.getRouter().navigate('#Report/show/' + this.model.id, {trigger: false});
            this.getRouter().dispatch('Report', 'show', options);
        },

    });
});
