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

Espo.define('advanced:views/workflow/fields/target-user-position', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            this.translatedOptions = {
                '': '--' + this.translate('All') + '--'
            };

            this.params.options = [''];
            if (this.model.get('targetUserPosition') && this.model.get('targetTeamId')) {
                this.params.options.push(this.model.get('targetUserPosition'));
            }

            this.loadRoleList(function () {
                if (this.mode == 'edit') {
                    if (this.isRendered()) {
                        this.render();
                    }
                }
            }, this);

            this.listenTo(this.model, 'change:targetTeamId', function () {
                this.loadRoleList(function () {
                    this.render();
                }, this);
            }, this);
        },

        loadRoleList: function (callback, context) {
            var teamId = this.model.get('targetTeamId');
            if (!teamId) {
                this.params.options = [''];
            }

            this.getModelFactory().create('Team', function (team) {
                team.id = teamId;

                this.listenToOnce(team, 'sync', function () {
                    this.params.options = team.get('positionList') || [];
                    this.params.options.unshift('');
                    callback.call(context);
                }, this);

                team.fetch();
            }, this);
        }


    });

});
