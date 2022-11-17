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

Espo.define('voip:views/call-notification', ['views/popup-notification', 'model'], function (Dep, Model) {

    return Dep.extend({

        entityName: 'VoipEvent',

        type: 'voipNotification',

        style: 'primary',

        template: 'voip:notification/call',

        displayedFieldList: {},

        additionalFieldList: {},

        voipFieldList: {},

        cssClasses: {
            "incomingCall": "warning",
            "outgoingCall": "info"
        },

        data: function () {
            return _.extend({
                displayedFieldList: this.displayedFieldList[this.notificationData.id],
                additionalFieldList: this.additionalFieldList[this.notificationData.id],
                editDisabled: !this.getAcl().check('Call', 'edit'),
                forwardEnabled: false
            }, Dep.prototype.data.call(this));
        },

        events: {
            'click button[data-action="save"]': function (e) {
                this.confirm();
            },
            'click button[data-action="cancel"]': function (e) {
                this.cancel();
            },
            'click [data-action="createEntity"]': function (e) {
                this.actionCreateEntity(e);
                return false;
            },
            'click [data-action="quickCreateEntity"]': function (e) {
                this.actionQuickCreateEntity(e);
                return false;
            },
            'click [data-action="forward"]': function (e) {
                this.forwardCall();
            },
        },

        setup: function () {

            var data = this.notificationData;

            this.style = this.cssClasses[data.type];
            this.notificationData = data;

            this.model = new Model(this.notificationData);
            this.model.id = this.notificationData.id;
            this.model.name = this.entityName;
            this.model.urlRoot = this.entityName;
            this.model.url = this.entityName;
            this.model.defs = {
                fields: {},
                links: {}
            };

            /* create views for entities */
            this.voipFieldList[this.model.id] = [];
            this.displayedFieldList[this.model.id] = [];

            _.each(this.notificationData['entities'], function(fieldData, scope) {

                if (!this.getAcl().check(scope, 'read')) {
                    return;
                }

                var fieldName = this.toModelFieldName(scope);
                var viewName = fieldName + 'Container';

                this.displayedFieldList[this.model.id].push({
                    fieldName: fieldName,
                    viewName: viewName,
                    scope: scope,
                });

                this.createView(viewName, 'voip:views/fields/create-select', {
                    el: this.options.el + ' .cell[data-name="' + fieldName + '"]',
                    model: this.model,
                    mode: 'edit',
                    foreignScope: scope,
                    voipFieldData: fieldData
                }, function (view) {

                    view.voipFieldList.forEach(function (fieldName) {
                        this.voipFieldList[this.model.id].push(fieldName);
                        this.listenTo(view.model, 'change:' + fieldName, function (model, value, data) {
                            if (!data.skipStorage) {
                                this.setStorageNotificationData(fieldName, model.get(fieldName));
                            }
                        }.bind(this));
                    }.bind(this));

              }.bind(this));

            }.bind(this));
            /* END */

            this.additionalFieldList[this.model.id] = [];
            this.additionalFields = this.getAdditionalFieldsDefs();

            //create views based on additionalFields
            for (name in this.additionalFields) {
                if (this.additionalFields[name].display == true || ~this.additionalFields[name].display.indexOf(this.notificationData.connector)) {
                    var fieldName = this.additionalFields[name].field;
                    var fieldEntityName = this.additionalFields[name].entity;
                    var fieldDefs = this.getMetadata().get('entityDefs.' + fieldEntityName + '.fields.' + fieldName) || {};
                    var linkDefs = this.getMetadata().get('entityDefs.' + fieldEntityName + '.links.' + fieldName) || {};

                    this.model.defs.fields[name] = fieldDefs;
                    if (linkDefs) {
                        this.model.defs.links[name] = fieldDefs;
                    }

                    if (!this.model.get(name)) {
                        var fieldValue = this.model.get(name) || fieldDefs.default || null;
                        this.model.set(name, fieldValue);
                    }

                    if (this.getAcl().check(fieldEntityName, 'read')) {
                        this.createFieldViewForAdditionalField(name, fieldEntityName, fieldName);
                    }
                }
            }

            Handlebars.registerHelper('toLowerCase', function(str) {
                return str.toLowerCase();
            });

            Handlebars.registerHelper('translateFieldLabel', function(name) {
                var fieldName = this.fromModelFieldName(name);
                return this.translate(this.additionalFields[fieldName].field, 'fields', this.additionalFields[fieldName].entity);
            }.bind(this));

            var storageNotificationName = this.getStorageNotificationName();

            window.addEventListener('storage', function (e) {
                if (e.key == storageNotificationName) {
                    var voipNotificationData = this.getStorageNotificationData();
                    var changedAttrName = this.getStorageChangedAttrName(e);

                    if (~this.voipFieldList[this.model.id].indexOf(changedAttrName)) {
                        this.model.set(changedAttrName, voipNotificationData[changedAttrName], {skipStorage: true});
                    }
                }
            }.bind(this), false);

            this.autoOpenCallerInfo();
        },

        afterRender: function() {
            var voipNotificationData = this.getStorageNotificationData();
            this.voipFieldList[this.model.id].forEach(function(fieldName) {
                if (voipNotificationData[fieldName]) {
                    this.model.set(fieldName, voipNotificationData[fieldName], {skipStorage: true});
                }
            }.bind(this));
        },

        onConfirm: function () {
            var data = {};

            data.entities = {};
            Object.keys(this.notificationData.entities).forEach(function(entityName) {
                var modelFieldName = this.toModelFieldName(entityName);

                data.entities[entityName] = {};

                if (this.model.hasField(modelFieldName + 'Ids')) {
                    var ids = this.model.get(modelFieldName + 'Ids');
                    var names = this.model.get(modelFieldName + 'Names');

                    if (ids) {
                        ids.forEach(function(id) {
                            data.entities[entityName][id] = {
                                name: names[id]
                            };
                        });
                    }
                    return;
                }

                if (this.model.hasField(modelFieldName + 'Id')) {
                    id = this.model.get(modelFieldName + 'Id');
                    name = this.model.get(modelFieldName + 'Name');

                    if (id) {
                        data.entities[entityName][id] = {
                            name: name
                        };
                    }
                }
            }.bind(this));

            this.additionalFieldList[this.model.id].forEach(function(fieldData) {
                var modelFieldName = fieldData.fieldName;

                if (this.model.hasField(modelFieldName)) {
                    var normalizedFieldName = this.fromModelFieldName(modelFieldName);
                    data[normalizedFieldName] = this.model.get(modelFieldName);
                }
            }.bind(this));

            this.runAction('save', data);
        },

        onCancel: function () {
            this.runAction('cancel');
        },

        forwardCall: function () {
            //TODO
        },

        runAction: function(actionName, actionData) {
            var self = this;

            if (!actionData) {
                actionData = {};
            }

            actionData.eventId = this.notificationData.id;

            this.$el.find('.btn').prop("disabled", true);

            this.notify(this.translate('Saving...', 'labels'));

            var jqxhr = $.ajax({
                type : 'POST',
                contentType : 'application/json',
                dataType: 'json',
                url: this.entityName + '/action/' + actionName,
                data: JSON.stringify(actionData)
            }).done(function (data) {
                self.removeNotification();
            }.bind(this));

            jqxhr.fail(function() {
                self.$el.find('.btn').prop("disabled", false);
            });

            //remove local storage
            this.removeStorageNotification();
        },

        removeNotification: function () {
            this.notify(false);

            var storageNotificationName = this.getStorageNotificationName();
            if (localStorage[storageNotificationName]) {
                delete localStorage[storageNotificationName];
            }
        },

        actionCreateEntity: function (e) {
            $el = $(e.currentTarget);

            var data = this.notificationData;
            var scope = $el.attr('data-scope');

            url = '#' + scope + '/create';

            var attributes = {
                "phoneNumberData": [
                    {
                        "phoneNumber": data.phoneNumber,
                        "primary": true
                    }
                ],
                "voipUniqueid": data.id,
                "voipLine": data.lineId
            };

            var router = this.getRouter();
            setTimeout(function () {
                router.dispatch(scope, 'create', {
                    attributes: attributes
                });
                router.navigate(url, {trigger: false});
            }.bind(this), 10);
        },

        getStorageNotificationName: function (definedSymbol) {
            var symbol = definedSymbol || '-';
            return this.type + symbol + this.notificationId;
        },

        getStorageNotificationData: function () {
            var storageNotificationName = this.getStorageNotificationName();
            var data = localStorage[storageNotificationName] ? JSON.parse(localStorage.getItem(storageNotificationName)) : {};

            return data;
        },

        setStorageNotificationData: function (name, value) {
            var storageNotificationName = this.getStorageNotificationName();
            var data = this.getStorageNotificationData();
            data[name] = value;
            localStorage.setItem(storageNotificationName, JSON.stringify(data));
        },

        getStorageChangedAttrName: function (e) {
            var oldValueJson = JSON.parse(e.oldValue) || {};
            var newValueJson = JSON.parse(e.newValue) || {};

            var attrName = null;

            Object.keys(newValueJson).forEach(function(name) {
                if (typeof oldValueJson[name] === 'undefined' || newValueJson[name] != oldValueJson[name]) {
                    attrName = name;
                    return;
                }
            });

            return attrName;
        },

        removeStorageNotification: function() {
            var storageNotificationName = this.getStorageNotificationName();
            localStorage.removeItem(storageNotificationName);
        },

        onShow: function () {
            var mute = this.getUser().get('voipMute');
            if (mute) {
                return;
            }

            Dep.prototype.onShow.call(this);
        },

        actionQuickCreateEntity: function (e) {
            $el = $(e.currentTarget);

            var data = this.notificationData;
            var scope = $el.attr('data-scope');

            url = '#' + scope + '/create';

            var attributes = {
                "voipEventData": data
            };

            var router = this.getRouter();
            setTimeout(function () {
                router.dispatch(scope, 'create', {
                    attributes: attributes
                });
                router.navigate(url, {trigger: false});
            }.bind(this), 10);
        },

        autoOpenCallerInfo: function() {
            var connector = this.notificationData.connector;
            var autoOpenCallerInfo = this.getMetadata().get('app.voip.options.' + connector + '.autoOpenCallerInfo') || this.getMetadata().get('app.voip.defaults.autoOpenCallerInfo');

            var storageNotificationData = this.getStorageNotificationData();

            if (!storageNotificationData.autoOpened && autoOpenCallerInfo == 'yes' || autoOpenCallerInfo == this.notificationData.type) {
                if (document.visibilityState == 'visible') {
                    for (entityName in this.notificationData.entities) {
                        if (!this.getAcl().check(entityName, 'read')) {
                            continue;
                        }

                        for (entityId in this.notificationData.entities[entityName]) {
                            this.setStorageNotificationData('autoOpened', true);
                            this.gotoEntity(entityName, entityId);
                            return;
                        }
                    }
                } else {
                    var self = this;
                    setTimeout(function() {
                        self.autoOpenCallerInfo();
                    }.bind(this), 1000);
                }
            }
        },

        gotoEntity: function (entityName, id) {
            var url = '#' + entityName + '/view/' + id;
            var router = this.getRouter();
            router.navigate(url, {trigger: true});
        },

        createFieldViewForAdditionalField: function (name, scope, fieldName) {
            var fieldDefs = this.getMetadata().get('entityDefs.' + scope + '.fields.' + fieldName);
            var view = fieldDefs.view || 'views/fields/' + Espo.Utils.lowerCaseFirst(fieldDefs.type);

            delete fieldDefs.name;

            switch(fieldDefs.type) {
                case "enum":
                case "multiEnum":
                    fieldDefs.translatedOptions = this.translate(fieldName, 'options', scope) || {};
                    if (typeof fieldDefs.translatedOptions !== 'object') {
                        fieldDefs.translatedOptions = {};
                    }
                    break;
            }

            var viewName = name + 'Field';
            var fieldParams = {
                fieldName: name,
                viewName: viewName
            };

            this.voipFieldList[this.model.id].push(name);
            this.additionalFieldList[this.model.id].push(
                _.extend(fieldParams, this.additionalFields[name])
            );

            this.createView(viewName, view, {
                model: this.model,
                mode: 'edit',
                el: '#' + this.id + ' .field[data-name="' + name + '"]',
                defs: {
                    name: name,
                    params: fieldDefs
                }
            });

            this.listenTo(this.model, 'change:' + name, function (model) {
                this.setStorageNotificationData(name, model.get(name));
            }.bind(this));
        },

        toModelFieldName: function (fieldName) {
            return 'voip' + Espo.Utils.upperCaseFirst(fieldName);
        },

        fromModelFieldName: function (modelFieldName) {
            var fieldName = modelFieldName.replace(/^voip/, '').replace(/Field$/, '');
            return Espo.Utils.lowerCaseFirst(fieldName);
        },

        getAdditionalFieldsDefs: function () {
            var additionalFieldsDefs = this.getMetadata().get('app.popupNotifications.voipNotification.additionalFields');

            //order additionalFields
            var indexedList = [];
            for (name in additionalFieldsDefs) {
                var fieldDefs = additionalFieldsDefs[name];
                fieldDefs.name = name;
                fieldDefs.order = additionalFieldsDefs[name].order || 10;

                indexedList.push(fieldDefs);
            }

            indexedList.sort(function(a, b) {
                return a.order - b.order;
            });

            var orderedAdditionalFieldsDefs = {};

            indexedList.forEach(function(item) {
                orderedAdditionalFieldsDefs[item.name] = item;
            });

            return orderedAdditionalFieldsDefs;
        },

    });
});
