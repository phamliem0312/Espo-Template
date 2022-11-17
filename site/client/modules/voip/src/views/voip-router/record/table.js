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

Espo.define('voip:views/voip-router/record/table', 'view', function (Dep) {

    return Dep.extend({

        template: 'voip:voip-router/table',

        scopeList: null,

        voiceActionList: ['inQueue', 'incoming', 'outgoing'],

        actionList: [],

        userList: [],

        updateModel: false,

        mode: 'detail',

        tableData: null,

        userOrder: [],

        data: function () {
            var data = {};
            data.editMode = this.mode === 'edit';
            data.actionList = this.actionList;
            data.tableDataList = this.convertToTableData();
            data.noUserData = (this.userList.length == 0) ? true : false;
            return data;
        },

        convertToTableData: function () {
            var routerData = this.model.get('rules') || {};
            var outgoingRoutes = this.model.get('outgoingRoutes') || {};

            var userNames = {};
            this.userList.forEach(function (user) {
                userNames[user.id] = user.name;
            });

            if (this.actionList.indexOf('outgoing') != -1) {
                this.userList.forEach(function (user) {
                    var userId = user.id;
                    if (!(routerData[userId] || false)) {
                        routerData[userId] = [];
                    }
                });

                $.each(routerData, function(userId, rule) {
                    var outgoing = false;
                    var notice = false;
                    if ((outgoingRoutes[userId] || false)) {
                        if (outgoingRoutes[userId] == this.routerName) {
                            outgoing = true;
                        } else {
                            notice = outgoingRoutes[userId];
                        }
                    }
                    if (!this.updateModel && routerData[userId]['outgoing'] != outgoing) {
                        this.updateModel = true;
                    }
                    routerData[userId]['outgoing'] = outgoing;
                    routerData[userId]['outgoingNotice'] = notice;
                }.bind(this));
            }

            var dataList = [];

            this.userOrder.forEach(function (userId) {
                var o = {};
                var list = [];

                this.actionList.forEach(function (action, j) {

                    list.push({
                        name: userId + '-' + action,
                        action: action,
                        level: (routerData[userId] || false) && (routerData[userId][action] || false),
                        notice: (routerData[userId] || false) && (routerData[userId][action + 'Notice'] || false)
                    });
                }, this);

                dataList.push({
                    list: list,
                    userName: userNames[userId],
                    userId: userId
                });
            }, this);

            return dataList;
        },

        convertFromTableData: function () {
            var data = {};

            data.rules = {};
            data.outgoingRoutes = this.model.get('outgoingRoutes') || {};

            var initRules = this.model.get('rules') || {};
            var userOrder = this.userOrder;
            var userList = this.userList;
            var actionList = this.actionList;
            var updateModel = this.updateModel || false;

            if (updateModel) {
                data['update'] = true;
            }

            for (var i in userList) {
                var user = userList[i];
                var userId = user.id;
                o = {};
                for (var j in actionList) {
                    var action = actionList[j];

                    var isChecked = this.$el.find('input[name="' + userId + '-' + action + '"]').is(":checked");
                    o[action] = isChecked;

                    switch (action) {
                        case 'outgoing':
                            if (isChecked) {
                                data.outgoingRoutes[userId] = this.routerName;
                                break;
                            }

                            if (typeof initRules[userId] !== 'undefined' && initRules[userId][action] !== isChecked) {
                                data.outgoingRoutes[userId] = isChecked ? this.routerName : '';
                            }
                            break;
                    }
                }
                data['rules'][userId] = o;
            }
            data['userOrder'] = userOrder;
            return data;
        },

        setup: function () {
            this.mode = this.options.mode || 'detail';

            this.final = this.options.final || false;

            this.setupData();

            this.listenTo(this.model, 'change', function () {
                if (this.model.hasChanged('teamId') ||
                    this.model.hasChanged('name') ||
                    this.model.hasChanged('sms') ||
                    this.model.hasChanged('voice') ||
                    this.model.hasChanged('mms')
                    ) {

                    this.setupData();
                    if (this.isRendered()) {
                        this.reRender();
                    }
                }

            }, this);
        },

        setupData: function () {
            this.routerName = this.model.get('connector') + '::' + this.model.get('name');

            this.setupUserList();
            this.setupActionList();
        },

        setupActionList: function () {
           this.actionList = [];
           if (this.model.get('voice')) {
                this.actionList = this.voiceActionList.slice(0);
           }
           if (this.model.get('sms')) {
                this.actionList.push('sms');
           }
           if (this.model.get('mms')) {
                this.actionList.push('mms');
           }
        },

        setupUserList: function () {
            this.userList = [];
            this.userOrder = [];
            var currentUserOrder = this.model.get('userOrder') || [];
            //fix
            if (!(this.model.get('teamId') || false) || (this.model.get('name') || '') == '') {
                return;
            }
            $.ajax({
                type: 'GET',
                async: false,
                url: 'Team/' + this.model.get('teamId') + '/users?primaryFilter=&maxSize=200&offset=0&sortBy=userName&asc=true',
                error: function (xhr) {
                    xhr.errorIsHandled = true;
                },
            }).done(function (users) {
                this.userList = users.list || [];
                var userIds = [];

                this.userList.forEach(function (user) {
                    userIds.push(user.id);
                });
                if (currentUserOrder.length == 0) {
                    this.userOrder = userIds;
                } else {
                    currentUserOrder.forEach(function (userId, idx) {
                        if (userIds.indexOf(userId) != -1 && this.userOrder.indexOf(userId) == -1) {
                            this.userOrder.push(userId);
                        }
                    }, this);
                    userIds.forEach(function (userId, idx) {
                        if (this.userOrder.indexOf(userId) == -1) {
                            this.userOrder.push(userId);
                        }
                    }, this);
                }
            }.bind(this));
        },

        afterRender: function () {
            if (this.mode == 'edit') {
                var allNoticeSigns = this.$el.find('.notice-sign');
                allNoticeSigns.each(function (i, el) {
                    var content = $(el).parent().find('.notice-text:first').val();
                    $(el).popover({
                        placement: 'bottom',
                        container: 'body',
                        html: true,
                        content: content,
                        trigger: 'click',
                    }).on('shown.bs.popover', function () {
                        $('body').one('click', function () {
                            $(el).popover('hide');
                        });
                    });

                });

                var fixHelper = function(e, ui) {
                    ui.children().each(function() {
                        $(this).width($(this).width());
                    });
                    return ui;
                };

                this.$el.find('.item-list-internal-container').sortable({
                    handle: '.drag-icon',
                    helper: fixHelper,
                    stop: function () {
                        var idList = [];
                        this.$el.find('.item-list-internal-container').children().each(function (i, el) {
                            idList.push($(el).attr('data-id'));
                        });
                        this.userOrder = idList;
                        this.model.set('userOrder', idList);
                    }.bind(this),
                });
            }
        },

    });
});
