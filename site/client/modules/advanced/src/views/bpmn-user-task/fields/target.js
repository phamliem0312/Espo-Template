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

Espo.define('advanced:views/bpmn-user-task/fields/target', 'views/fields/link-parent', function (Dep) {

    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);

            var scopes = this.getMetadata().get('scopes');
            var entityListToIgnore = this.getMetadata().get('entityDefs.Workflow.entityListToIgnore') || [];
            var scopeList = Object.keys(scopes).filter(function (scope) {
                if (~entityListToIgnore.indexOf(scope)) {
                    return;
                }
                var defs = scopes[scope];
                return (defs.entity && (defs.tab || defs.object || defs.workflow));
            }).sort(function (v1, v2) {
                return this.translate(v1, 'scopeNamesPlural').localeCompare(this.translate(v2, 'scopeNamesPlural'));
            }.bind(this));

            this.foreignScopeList = scopeList;
        }

    });

});