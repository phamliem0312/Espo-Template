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

define('advanced:views/report/record/detail', 'views/record/detail', function (Dep) {

    return Dep.extend({

        editModeDisabled: true,

        printPdfAction: false,

        setup: function () {
            Dep.prototype.setup.call(this);
            if (
                this.getMetadata().get(['scopes', 'ReportCategory', 'disabled'])
                ||
                !this.getAcl().checkScope('ReportCategory', 'read')
            ) {
                this.hideField('category');
            }

            if (!this.getUser().isPortal()) {
                this.setupEmailSendingFieldsVisibility();
            }

            this.hidePanel('emailSending');

            if (!this.getUser().isPortal()) {
                if (this.model.has('emailSendingInterval')) {
                    this.controlEmailSendingPanelVisibility();
                } else {
                    this.listenToOnce(this.model, 'sync', this.controlEmailSendingPanelVisibility, this);
                }
            }

            if (this.getUser().isPortal()) {
                this.hidePanel('default');
            }

            this.controlPortalsFieldVisibility();
            this.listenTo(this.model, 'sync', this.controlPortalsFieldVisibility);

            this.controlDescriptionFieldVisibility();
            this.listenTo(this.model, 'sync', this.controlDescriptionFieldVisibility);
        },

        controlPortalsFieldVisibility: function () {
            if (this.getAcl().get('portalPermission') === 'no') {
                this.hideField('portals');
                return;
            }
            if (this.model.getLinkMultipleIdList('portals').length) {
                this.showField('portals');
            } else {
                this.hideField('portals');
            }
        },

        controlDescriptionFieldVisibility: function () {
            if (this.model.get('description')) {
                this.showField('description');
            } else {
                this.hideField('description');
            }
        },

        controlEmailSendingPanelVisibility: function () {
            if (this.model.get('emailSendingInterval')) {
                this.showPanel('emailSending');
            } else {
                this.hidePanel('emailSending');
            }
        },

        setupEmailSendingFieldsVisibility: function () {
            this.controlEmailSendingIntervalField();
            this.listenTo(this.model, 'change:emailSendingInterval', function () {
                this.controlEmailSendingIntervalField();
            }, this);
        },

        controlEmailSendingIntervalField: function() {
            var inteval = this.model.get('emailSendingInterval');

            if (this.model.get('type') == 'List') {
                if (inteval == '' || !inteval) {
                    this.hideField('emailSendingDoNotSendEmptyReport');
                } else {
                    this.showField('emailSendingDoNotSendEmptyReport');
                }
            } else {
                this.hideField('emailSendingDoNotSendEmptyReport');
            }

            if (inteval === 'Daily') {
                this.showField('emailSendingTime');
                this.showField('emailSendingUsers');
                this.hideField('emailSendingSettingMonth');
                this.hideField('emailSendingSettingDay');
                this.hideField('emailSendingSettingWeekdays');
            } else if (inteval === 'Monthly') {
                this.showField('emailSendingTime');
                this.showField('emailSendingUsers');
                this.hideField('emailSendingSettingMonth');
                this.showField('emailSendingSettingDay');
                this.hideField('emailSendingSettingWeekdays');
            } else if (inteval === 'Weekly') {
                this.showField('emailSendingTime');
                this.showField('emailSendingUsers');
                this.hideField('emailSendingSettingMonth');
                this.hideField('emailSendingSettingDay');
                this.showField('emailSendingSettingWeekdays');
            } else if (inteval === 'Yearly') {
                this.showField('emailSendingTime');
                this.showField('emailSendingUsers');
                this.showField('emailSendingSettingMonth');
                this.showField('emailSendingSettingDay');
                this.hideField('emailSendingSettingWeekdays');
            } else {
                this.hideField('emailSendingTime');
                this.hideField('emailSendingUsers');
                this.hideField('emailSendingSettingMonth');
                this.hideField('emailSendingSettingDay');
                this.hideField('emailSendingSettingWeekdays');
            }
        }

    });

});
