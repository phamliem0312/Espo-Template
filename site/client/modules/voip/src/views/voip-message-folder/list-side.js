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

Espo.define('voip:views/voip-message-folder/list-side', 'view', function (Dep) {

    return Dep.extend({

        template: 'voip:voip-message-folder/list-side',

        events: {
            'click [data-action="selectFolder"]': function (e) {
                e.preventDefault();
                var id = $(e.currentTarget).data('id');
                this.actionSelectFolder(id);
            }
        },

        data: function () {
            var data = {};
            data.selectedFolderId = this.selectedFolderId;
            data.showEditLink = this.options.showEditLink;
            data.scope = this.scope;
            return data;
        },

        setup: function () {
            this.scope = 'VoipMessageFolder';
            this.selectedFolderId = this.options.selectedFolderId || 'all';
            this.voipMessageCollection = this.options.voipMessageCollection;

            this.loadNotReadCounts();

            this.listenTo(this.voipMessageCollection, 'sync', this.loadNotReadCounts);

            this.listenTo(this.voipMessageCollection, 'all-marked-read', function (m) {
                this.countsData = this.countsData || {};
                for (var id in this.countsData) {
                    if (id === 'drafts') {
                        continue;
                    }
                    this.countsData[id] = 0;
                }
                this.renderCounts();
            });

            this.listenTo(this.voipMessageCollection, 'change:isRead', function (model) {
                if (this.countsIsBeingLoaded) return;
                this.manageCountsDataAfterModelChanged(model);
            }, this);

            this.listenTo(this.voipMessageCollection, 'draft-sent', function (m) {
                this.decreaseNotReadCount('drafts');
                this.renderCounts();
            });

            this.listenTo(this.voipMessageCollection, 'model-removing', function (id) {
                var model = this.voipMessageCollection.get(id);
                if (!model) return;
                if (this.countsIsBeingLoaded) return;
                this.manageModelRemoving(model);
            }, this);

            this.listenTo(this.voipMessageCollection, 'moving-to-trash', function (id) {
                var model = this.voipMessageCollection.get(id);
                if (!model) return;
                if (this.countsIsBeingLoaded) return;
                this.manageModelRemoving(model);
            }, this);

            this.listenTo(this.voipMessageCollection, 'retrieving-from-trash', function (id) {
                var model = this.voipMessageCollection.get(id);
                if (!model) return;
                if (this.countsIsBeingLoaded) return;
                this.manageModelRetrieving(model);
            }, this);
        },

        actionSelectFolder: function (id) {
            this.$el.find('li.selected').removeClass('selected');
            this.selectFolder(id);
            this.$el.find('li[data-id="'+id+'"]').addClass('selected');
        },

        selectFolder: function (id) {
            this.voipMessageCollection.reset();
            this.voipMessageCollection.abortLastFetch();

            this.selectedFolderId = id;
            this.trigger('select', id);
        },

        manageModelRemoving: function (model) {
            if (model.get('status') === 'Draft') {
                this.decreaseNotReadCount('drafts');
                this.renderCounts();
                return;
            }

            if (!model.get('isUsers')) return;
            if (model.get('isRead')) return;

            var folderId = model.get('folderId') || 'inbox';
            this.decreaseNotReadCount(folderId);
            this.renderCounts();
        },

        manageModelRetrieving: function (model) {
            if (!model.get('isUsers')) return;
            if (model.get('isRead')) return;
            var folderId = model.get('folderId') || 'inbox';
            this.increaseNotReadCount(folderId);
            this.renderCounts();
        },

        manageCountsDataAfterModelChanged: function (model) {
            if (!model.get('isUsers')) return;
            var folderId = model.get('folderId') || 'inbox';
            if (!model.get('isRead')) {
                this.increaseNotReadCount(folderId);
            } else {
                this.decreaseNotReadCount(folderId);
            }
            this.renderCounts();
        },

        increaseNotReadCount: function (folderId) {
            this.countsData = this.countsData || {};
            this.countsData[folderId] = this.countsData[folderId] || 0;
            this.countsData[folderId]++;
        },

        decreaseNotReadCount: function (folderId) {
            this.countsData = this.countsData || {};
            this.countsData[folderId] = this.countsData[folderId] || 0;
            if (this.countsData[folderId]) {
                this.countsData[folderId]--;
            }
        },

        afterRender: function () {
            if (this.countsData) {
                this.renderCounts();
            }
        },

        loadNotReadCounts: function () {
            if (this.countsIsBeingLoaded) return;

            this.countsIsBeingLoaded = true;
            this.ajaxGetRequest('VoipMessage/action/getFoldersNotReadCounts').then(function (data) {
                this.countsData = data;
                if (this.isRendered()) {
                    this.renderCounts();
                    this.countsIsBeingLoaded = false;
                } else {
                    this.once('after:render', function () {
                        this.renderCounts();
                        this.countsIsBeingLoaded = false;
                    }, this);
                }
            }.bind(this));
        },

        renderCounts: function () {
            var data = this.countsData;
            for (var id in data) {
                var value = '';
                if (data[id]) {
                    value = data[id].toString();
                }
                this.$el.find('li a.count[data-id="'+id+'"]').text(value);
            }
        }

    });
});
