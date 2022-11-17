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

define('voip:views/voip-message/list', 'views/list', function (Dep) {

    return Dep.extend({

        createButton: false,

        template: 'voip:voip-message/list',

        folderId: null,

        folderScope: 'VoipMessageFolder',

        currentFolderId: null,

        defaultFolderId: 'inbox',

        keepCurrentRootUrl: true,

        setup: function () {
            Dep.prototype.setup.call(this);

            var params = this.options.params || {};

            this.selectedFolderId = params.folder || this.defaultFolderId;

            if (this.foldersDisabled) {
                this.selectedFolderId = null;
            }

            this.applyFolder();
        },

        data: function () {
            var data = {};
            data.foldersDisabled = this.foldersDisabled;
            return data;
        },

        actionComposeMessage: function () {
            this.actionQuickCreate();
        },

        /* remove after version 5.8.5 */
        actionQuickCreate: function () {
            var attributes = this.getCreateAttributes() || {};

            this.notify('Loading...');
            var viewName = this.getMetadata().get('clientDefs.' + this.scope + '.modalViews.edit') || 'views/modals/edit';
            var options = {
                scope: this.scope,
                attributes: attributes
            };
            if (this.keepCurrentRootUrl) {
                options.rootUrl = this.getRouter().getCurrentUrl();
            }

            var returnDispatchParams = {
                controller: this.scope,
                action: null,
                options: {
                    isReturn: true
                }
            };
            this.prepareCreateReturnDispatchParams(returnDispatchParams);
            _.extend(options, {
                returnUrl: this.getRouter().getCurrentUrl(),
                returnDispatchParams: returnDispatchParams
            });

            this.createView('quickCreate', viewName, options, function (view) {
                view.render();
                view.notify(false);
                this.listenToOnce(view, 'after:save', function () {
                    this.collection.fetch();
                }, this);
            }.bind(this));
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            if (!this.foldersDisabled && !this.hasView('folders')) {
                this.loadFolders();
            }
        },

        getFolderCollection: function (callback) {
            this.getCollectionFactory().create(this.folderScope, function (collection) {
                collection.url = 'VoipMessageFolder/action/listAll';
                collection.maxSize = 200;

                collection.folderCollection = collection;

                this.listenToOnce(collection, 'sync', function () {
                    callback.call(this, collection);
                }, this);
                collection.fetch();
            }, this);
        },

        loadFolders: function () {
            var xhr = null;
            this.getFolderCollection(function (collection) {
                this.createView('folders', 'voip:views/voip-message-folder/list-side', {
                    collection: collection,
                    voipMessageCollection: this.collection,
                    el: this.options.el + ' .folders-container',
                    showEditLink: this.getAcl().check(this.folderScope, 'edit'),
                    selectedFolderId: this.selectedFolderId
                }, function (view) {
                    view.render();
                    this.listenTo(view, 'select', function (id) {
                        this.selectedFolderId = id;
                        this.applyFolder();

                        if (xhr && xhr.readyState < 4) {
                            xhr.abort();
                        }

                        this.notify('Please wait...');
                        xhr = this.collection.fetch({
                            success: function () {
                                this.notify(false);
                            }.bind(this)
                        });

                        if (id !== this.defaultFolderId) {
                            this.getRouter().navigate('#VoipMessage/list/folder=' + id);
                        } else {
                            this.getRouter().navigate('#VoipMessage');
                        }
                        this.updateLastUrl();
                    }, this);
                }, this);
            }, this);
        },

        applyFolder: function () {
            this.collection.data.folderId = this.selectedFolderId;
        },

        applyRoutingParams: function (params) {
            var id;

            if ('folder' in params) {
                id = params.folder || 'inbox';
            } else {
                return;
            }

            if (!params.isReturnThroughLink && id !== this.selectedFolderId) {
                var foldersView = this.getView('folders');
                if (foldersView) {
                    foldersView.actionSelectFolder(id);
                    foldersView.reRender();
                    $(window).scrollTop(0);
                }
            }
        }

    });
});
