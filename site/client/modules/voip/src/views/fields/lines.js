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

Espo.define('voip:views/fields/lines', 'views/fields/enum', function (Dep) {

    return Dep.extend({

        listUrl: 'Voip/action/getLines',

        setup: function () {

            $.ajax({
                url: this.listUrl,
                type: 'GET'
            }).done(function (data) {
                if (data) {
                    Dep.prototype.setup.call(this);

                    var lines = Object.keys(data);
                    this.params.options = lines;
                    this.translatedOptions = data;

                    //set default value
                    if (lines.length > 0 && !~lines.indexOf(this.model.get(this.name))) {
                        this.model.set(this.name, lines[0]);
                    }

                    setTimeout(function() {
                        this.render();
                    }.bind(this), 200);
                }
            }.bind(this));

        },

    });

});
