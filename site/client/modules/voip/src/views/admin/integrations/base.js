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

Espo.define('voip:views/admin/integrations/base', 'views/admin/integrations/edit', function (Dep) {

    return Dep.extend({

        testConnectionButton: false,

        template: 'voip:admin/integrations/base',

        webhookUrl: '/api/v1/Voip/webhook/{CONNECTOR}/{ACCESS_KEY}',

        cronScript: 'command.php voip',

        additionalHideShowElems: [
            'addConnector',
            'generateKey',
        ],

        dependencyFields: {},

        data: function () {
            return _.extend({
                isCustom: this.isCustomConnector(),
                testConnectionButton: this.testConnectionButton
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.cronScript += ' ' + this.integration;
            this.testConnectionButton = this.getMetadata().get('integrations.' + this.integration + '.testConnectionButton') || this.testConnectionButton;

            var parentConnectorName = this.getParentConnectorName();
            if (this.getLanguage().has(parentConnectorName, 'help', 'Integration')) {
                this.helpText = this.translate(parentConnectorName, 'help', 'Integration');
            }

            this.events['click [data-action="addConnector"]'] = function (e) {
                this.addConnector(e);
            };

            this.events['click [data-action="removeConnector"]'] = function (e) {
                this.removeConnector(e);
            };

            this.events['click [data-action="generateKey"]'] = function (e) {
                this.renderAccessKey();
            };

            if (this.testConnectionButton) {
                this.additionalHideShowElems.push('testConnection');

                this.events['click button[data-action="testConnection"]'] = function (e) {
                    this.testConnection(e);
                };
            }

            this.setupDependencyFields();

            this.listenTo(this.model, 'change:enabled', function () {
                if (this.model.get('enabled')) {
                    this.showFields();
                } else {
                    this.hideFields();
                }
            }, this);
        },

        setupDependencyFields: function () {
            Object.keys(this.dependencyFields).forEach(parentField => {
                this.listenTo(this.model, 'change:' + parentField, function () {
                    let dependencyFieldList = this.dependencyFields[parentField];
                    let parentValue = this.model.get(parentField);

                    dependencyFieldList.forEach(field => {
                        if (parentValue) {
                            this.showField(field);
                            return;
                        }

                        this.hideField(field);
                    });
                }.bind(this));
            });
        },

        renderDependencyFields: function () {
            Object.keys(this.dependencyFields).forEach(parentField => {
                this.model.trigger('change:' + parentField);
            });
        },

        createFieldView: function (type, name, readOnly, params) {
            var viewName = this.getFieldManager().getViewName(type);
            if (params && typeof params.view !== "undefined") {
                viewName = params.view;
            }

            this.createView(name, viewName, {
                model: this.model,
                el: this.options.el + ' .field[data-name="'+name+'"]',
                defs: {
                    name: name,
                    params: params
                },
                mode: readOnly ? 'detail' : 'edit',
                readOnly: readOnly,
            });
            this.fieldList.push(name);
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            $.ajax({
                type: 'GET',
                url: 'Admin/action/cronMessage',
                error: function (x) {
                }.bind(this)
            }).done(function (data) {
                var voipCron = data.command.replace("cron.php", this.cronScript);
                this.replaceHelpText('{voipCron}', voipCron);
            }.bind(this));

            if (!this.model.get('accessKey')) {
                this.model.set('accessKey', this.generateKey());
            }

            this.renderVoipUri();

            this.enabledValue = this.model.get('enabled');

            if (this.enabledValue) {
                this.renderDependencyFields();
            }

            if (!this.enabledValue) {
                this.hideFields();
            }
        },

        save: function (name) {
            Dep.prototype.save.call(this);

            //reload page if changed enabled be reload metadata
            if (this.enabledValue != this.model.get('enabled')) {
                this.listenToOnce(this.model, 'sync', function () {
                    window.location.reload();
                }, this);
            }
        },

        addConnector: function(e) {
            this.runAction(e, 'addConnector');
        },

        removeConnector: function(e) {
            this.runAction(e, 'removeConnector');
        },

        runAction: function(e, type) {
            var $el = $(e.currentTarget);

            this.notify(this.translate('creating', 'labels', 'Integration'));
            $el.prop('disabled', true);

            var data = {
                'name': this.integration,
                'parent': this.getParentConnectorName()
            };

            $.ajax({
                type: 'GET',
                url: 'Voip/action/' + type,
                type: 'POST',
                data: JSON.stringify(data)
            }).always(function() {
                $el.prop('disabled', false);
            })
            .done(function(connectorName) {
                if (connectorName) {
                    Espo.Ui.success(this.translate('created', 'labels', 'Integration'));
                    this.getRouter().navigate('#Admin/integrations/name=' + connectorName);
                    window.location.reload();
                } else {
                    Espo.Ui.error(this.translate('Error'));
                }
            }.bind(this))
            .fail(function(xhr) {
                Espo.Ui.error(this.translate('Error'));
            }.bind(this));
        },

        getParentConnectorName: function () {
            return this.getMetadata().get('integrations.' + this.integration + '.parent') || this.integration;
        },

        hideFields: function () {
            this.additionalHideShowElems.forEach(function(name) {
                this.hideField(name);
            }.bind(this));

            this.$el.find('.second-button-container').addClass('hide');
            this.$el.find('.cell [data-action="removeConnector"]').removeClass('pull-right');

            if (!this.isCustomConnector()) {
                this.$el.find('.cell [data-action="removeConnector"]').addClass('hide');
            }
        },

        showFields: function () {
            this.additionalHideShowElems.forEach(function(name) {
                this.showField(name);
            }.bind(this));

            this.$el.find('.second-button-container').removeClass('hide');
            this.$el.find('.cell [data-action="removeConnector"]').addClass('pull-right').removeClass('hide');

            setTimeout(() => {
                this.renderDependencyFields();
            }, 300);
        },

        hideField: function (name) {
            this.$el.find('.cell [data-name="' + name + '"]').addClass('hide');
            this.$el.find('.cell [data-action="' + name + '"]').addClass('hide');
        },

        showField: function (name) {
            this.$el.find('.cell [data-name="'+name+'"]').removeClass('hide');
            this.$el.find('.cell [data-action="'+name+'"]').removeClass('hide');
        },

        getVoipUri: function (url) {
            url = url || this.webhookUrl;

            var accessKey = $(this.el).find('input[data-name="accessKey"]').val() || this.model.get('accessKey') || '';

            var voipUri = url.replace("{ACCESS_KEY}", accessKey);
            voipUri = voipUri.replace("{PARENT_CONNECTOR}", this.getParentConnectorName());
            voipUri = voipUri.replace("{CONNECTOR}", this.integration);
            voipUri = this.getConfig().get('siteUrl') + voipUri;

            return voipUri;
        },

        generateKey: function () {
            var key = Math.random().toString(36).slice(-10) + Math.random().toString(36).slice(-10);
            return key;
        },

        renderVoipUri: function () {
            var voipUri = this.getVoipUri();
            this.replaceHelpText('{voipPostUrl}', voipUri);
        },

        renderAccessKey: function () {
            var oldKey = this.model.get('accessKey');
            var generatedKey = this.generateKey();
            this.model.set('accessKey', generatedKey);

            this.replaceHelpText(new RegExp('\/' + oldKey, 'g'), '/' + generatedKey);
        },

        replaceHelpText: function(search, replace) {
            var html = this.$el.find('.well').html();
            if (html) {
                this.$el.find('.well').html(this.replaceAll(html, search, replace));
            }
        },

        replaceAll: function(string, search, replace) {
          return string.split(search).join(replace);
        },

        isCustomConnector: function() {
            return this.getMetadata().get('integrations.' + this.integration + '.isCustom') || false;
        },

        testConnection: function (e) {
            var $el = e ? $(e.currentTarget) : this.$el.find('button[data-action="testConnection"]');

            this.notify(this.translate('checking', 'labels', 'Integration'));
            $el.addClass('disabled');

            var data = {};
            this.$el.find('.field .main-element').each(function(index, el) {
                if ($(el).attr('data-name') !== undefined) {
                    data[$(el).attr('data-name')] = $(el).val();
                }
            });

            data.connector = this.integration;

            $.ajax({
                type: 'GET',
                url: 'VoipEvent/action/testConnection',
                type: 'POST',
                data: JSON.stringify(data)
            }).always(function() {
                $el.removeClass('disabled');
            })
            .done(function() {
                Espo.Ui.success(this.translate('successConnection', 'labels', 'Integration'));
            }.bind(this))
            .fail(function(xhr) {
                var statusReason = xhr.getResponseHeader('X-Status-Reason') || this.translate('failedConnection', 'labels', 'Integration');
                setTimeout(function() {
                    Espo.Ui.error(statusReason);
                }, 10);
            }.bind(this));
        },

    });

});
