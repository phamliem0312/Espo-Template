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

Espo.define('voip:views/voip-router/fields/connector', 'views/fields/enum', function (Dep) {

    return Dep.extend({
        
        setup: function () {
            $.ajax({
                type: 'GET',
                async: false,
                url: 'Voip/action/getConnectors',
                error: function (xhr) {
                    xhr.errorIsHandled = true;
                },
            }).done(function (connectors) {
                this.translatedOptions = connectors;
                this.params.isSorted = true;
                this.params.options = Object.keys(connectors);
                this.params.options = this.params.options.sort(function (v1, v2) {
                     return (this.translatedOptions[v1] || v1).localeCompare(this.translatedOptions[v2] || v2);
                }.bind(this));
            }.bind(this));
        },
        
    });

});
