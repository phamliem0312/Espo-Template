/*********************************************************************************
 * The contents of this file are subject to the EspoCRM VoIP Integration
 * Extension Agreement ("License") which can be viewed at
 * https://www.espocrm.com/voip-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2021 Letrium Ltd.
 * 
 * License ID: e36042ded1ed7ba87a149ac5079bd238
 ***********************************************************************************/

Espo.define('voip:views/voip-router/fields/team-user', 'views/fields/link', function (Dep) {

    return Dep.extend({
        
       getSelectFilters: function () {
            if (this.model.get('teamId')) {
                var hash = {};
                hash[this.model.get('teamId')] = this.model.get('teamName');
                return {
                    'teams': {
                        type: 'linkedWith',
                        field: 'teams',
                        value: [this.model.get('teamId')],
                        nameHash: hash,
                    }
                };
            }
        },

    });

});
