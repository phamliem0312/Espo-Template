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

Espo.define('voip:views/voip-router/record/edit', 'views/record/edit', function (Dep) {

    return Dep.extend({

        tableView: 'voip:views/voip-router/record/table',

        sideView: false,

        getDetailLayout: function (callback) {
            var simpleLayout = [
                {
                    label: '',
                    cells: [
                        {
                            name: 'name',
                            type: 'varchar',
                        },
                    ]
                }
            ];
            callback({
                type: 'record',
                layout: this._convertSimplifiedLayout(simpleLayout)
            });
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.createView('extra', this.tableView, {
                mode: 'edit',
                el: this.options.el + ' .extra',
                model: this.model
            });

            this.listenTo(this.model, 'change:voicemail', function () {
                if (this.model.get('voicemail')) {
                    this.model.set('farewell', false);
                }
            }, this);

            this.listenTo(this.model, 'change:farewell', function () {
                if (this.model.get('farewell')) {
                    this.model.set('voicemail', false);
                }
            }, this);

            this.model.trigger('change:voicemail');
            this.model.trigger('change:farewell');
        },

        /* duplicate code in detail.js */
        beforeBeforeSave: function () {
            var data = this.getView('extra').convertFromTableData();
            if (data) {
                this.model.set(data);
            }

            var initialAttributes = this.attributes;
            var beforeSaveAttributes = this.model.getClonedAttributes();

            data = _.extend(Espo.Utils.cloneDeep(beforeSaveAttributes), data);

            if (!this.model.isNew()) {
                if (_.isEqual(initialAttributes['userOrder'], data['userOrder']) &&
                    !_.isEqual(initialAttributes['rules'], data['rules'])) {
                    this.attributes['userOrder'] = [];
                }
            }
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            var hasSms = this.model.get('sms') || false;
            var hasMms = this.model.get('mms') || false;
            var hasVoice = this.model.get('voice') || false;
            if (!hasSms && !hasMms && !hasVoice) {
                this.hidePanel('defaultAssignment');
            } else if (!hasSms) {
                this.hideField('smsAssignTo');
            } else if (!hasMms) {
                this.hideField('mmsAssignTo');
            } else if (!hasVoice) {
                this.hideField('callAssignTo');
            }
            if (this.model.get('connector') != 'Twilio') {
                this.hidePanel('additionalSettings');
            }
        },
        /* END: duplicate code in detail.js */

    });
});
