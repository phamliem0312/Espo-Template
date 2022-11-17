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

 Espo.define('advanced:views/report/fields/email-sending-time', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'time',

        editTemplate: 'advanced:report/fields/email-sending-time/edit',

        timeFormatMap: {
            'HH:mm': 'H:i',
            'hh:mm A': 'h:i A',
            'hh:mm a': 'h:i a',
        },

        data: function () {
            var data = Dep.prototype.data.call(this);
            var m=moment.utc(this.model.get(this.name), 'HH:mm');
            var value = m.format(this.getDateTime().timeFormat);
            data.time = (value == 'Invalid date' ? '' : value);
            return data;
        },

        getValueForDisplay: function () {
            var value = this.model.get(this.name);
            if (!value) {
                if (this.mode == 'edit' || this.mode == 'search') {
                    return '';
                }
                return this.translate('None');
            }
            var m=moment.utc(this.model.get(this.name), 'HH:mm');
            value = m.format(this.getDateTime().timeFormat);
            value = (value == 'Invalid date' ? '' : value);
            return value;
        },

        afterRender: function () {
            var self = this;
            Dep.prototype.afterRender.call(this);

            if (this.mode == 'edit') {
                var $date = this.$date = this.$element;
                var $time = this.$time = this.$el.find('input[data-name="' + this.name + '-time"]');
                $time.timepicker({
                    step: 30,
                    scrollDefaultNow: true,
                    timeFormat: this.timeFormatMap[this.getDateTime().timeFormat]
                });
                $time.parent().find('button.time-picker-btn').on('click', function () {
                    $time.timepicker('show');
                });

                this.$element.on('change.time', function (e) {
                }.bind(this));

                var timeout = false;
                var changeCallback = function () {
                    if (!timeout) {
                        self.trigger('change');
                    }
                    timeout = true;
                    setTimeout(function () {
                        timeout = false;
                    }, 100)
                };
                $time.on('change', changeCallback);
            }
        },

        parse: function (string) {
            var m=moment.utc(string, this.getDateTime().timeFormat);
            return (m.format('HH:mm') == 'Invalid date' ? '' : m.format('HH:mm:ss'));
        },

        fetch: function () {
            var data = {};

            var time = this.$el.find('[data-name="' + this.name + '-time"]').val();

            var value = null;
            if (time != '') {
                value = this.parse(time);
            }
            data[this.name] = value;
            return data;
        },

        validateRequired: function () {
            if (this.isRequired()) {
                if (!this.model.get(this.name)) {
                    var msg = this.translate('fieldIsRequired', 'messages').replace('{field}', this.translate(this.name, 'fields', this.model.name));
                    this.showValidationMessage(msg);
                    return true;
                }
            }
        },

        isRequired: function () {
            return this.params.required || this.model.isRequired(this.name);
        },

    });

});
