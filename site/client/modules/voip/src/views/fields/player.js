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

Espo.define('voip:views/fields/player', ['views/fields/base', 'lib!soundManager', 'lib!Player'], function (Dep, soundManager) {

    return Dep.extend({

        type: 'player',

        listTemplate: 'voip:fields/player/detail',

        detailTemplate: 'voip:fields/player/detail',

        editTemplate: 'voip:fields/player/detail',

        inlineEditDisabled: true,

        readOnly: true,

        data: function () {
            var isRecording = false;
            if (this.getAcl().check('Call', 'read')) {
                isRecording = this.getValueForDisplay() ? true : false;
            }

            return _.extend({
                isRecording: isRecording
            }, Dep.prototype.data.call(this));
        },

        remove: function () {
            Dep.prototype.remove.call(this);

            if (typeof soundManager !== 'undefined') {
                soundManager.stopAll();
            }
        },

        afterRender: function () {
            if (typeof soundManager !== 'undefined') {
                var $player = this.$el.find('.sm2-bar-ui');

                if ($player.length) {
                    soundManager.setup({
                      html5PollingInterval: 50,
                      flashVersion: 9
                    });
                    sm2BarPlayers.push(new SM2BarPlayer($player[0]));
                    soundManager.reboot();
                }
            }
        }

    });

});
