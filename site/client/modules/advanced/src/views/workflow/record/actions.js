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

define('advanced:views/workflow/record/actions', 'view', function (Dep) {

    return Dep.extend({

        template: 'advanced:workflow/record/actions',

        events: {
            'click [data-action="addAction"]': function (e) {
                var $target = $(e.currentTarget);
                var actionType = $target.data('type');
                this.addAction(actionType, null, true);
            },
            'click [data-action="removeAction"]': function (e) {
                if (this.confirm) {
                    this.confirm(this.translate('Are you sure?'), function () {
                        var $target = $(e.currentTarget);
                        var id = $target.data('id');
                        this.removeAction(id);
                    }, this);
                } else {
                    if (confirm(this.translate('Are you sure?'))) {
                        var $target = $(e.currentTarget);
                        var id = $target.data('id');
                        this.removeAction(id);
                    }
                }
            }
        },

        data: function () {
            return {
                actionTypeList: this.actionTypeList,
                entityType: this.entityType,
                readOnly: this.readOnly,
                showNoData: this.readOnly && !(this.model.get('actions') || []).length
            };
        },

        removeAction: function (id)    {
            var $target = this.$el.find('[data-id="' + id + '"]');

            this.clearView('action-' + id);

            $target.parent().remove();

            this.trigger('change');
        },

        setup: function () {
            this.readOnly = this.options.readOnly || false;
            this.entityType = this.options.entityType || this.model.get('entityType');
            this.lastCid = 0;

            this.actionTypeList = this.getMetadata().get(['entityDefs', 'Workflow', 'actionList']) || [];
            this.actionTypeList = Espo.Utils.clone(this.actionTypeList);

            this.actionTypeList = Espo.Utils.clone(this.options.actionTypeList || this.actionTypeList);

            if (!this.getMetadata().get(['entityDefs', this.entityType, 'fields', 'assignedUser'])) {
                var index = -1;

                this.actionTypeList.forEach(function (item, i) {
                    if (item === 'applyAssignmentRule') {
                        index = i;
                    }
                }, this);

                if (~index) {
                    this.actionTypeList.splice(index, 1);
                }
            }
        },

        cloneData: function (data) {
            data = Espo.Utils.clone(data);

            if (Espo.Utils.isObject(data) || _.isArray(data)) {
                for (var i in data) {
                    data[i] = this.cloneData(data[i]);
                }
            }

            return data;
        },

        afterRender: function () {
            var actions = Espo.Utils.clone(this.model.get('actions') || []);

            actions.forEach(function (data) {
                data = data || {};
                if (!data.type) {
                    return;
                }

                this.addAction(data.type, this.cloneData(data));
            }, this);

            if (!this.readOnly) {
                var $container = this.$el.find('.actions');

                $container.sortable({
                    handle: '.drag-handle',
                    stop: function () {
                        this.trigger('change');
                    }.bind(this),
                });
            }
        },

        addAction: function (actionType, data, isNew) {
            data = data || {};

            var $container = this.$el.find('.actions');

            var id = data.cid = this.lastCid;
            this.lastCid++;

            var actionId = data.id;
            if (isNew) {
                data.id = actionId = Math.random().toString(36).substr(2, 10);
            }

            var removeLinkHtml = this.readOnly ? '' :
                '<a href="javascript:" class="pull-right" data-action="removeAction" data-id="'+id+'">'+
                '<span class="fas fa-times"></span></a>';

            var html = '<div class="clearfix list-group-item">' + removeLinkHtml +
                '<div class="workflow-action" data-id="' + id + '"></div></div>';

            $container.append($(html));

            if (isNew && !this.readOnly) {
                $container.sortable("refresh");
            }

            this.createView('action-' + id, 'advanced:views/workflow/actions/' + Espo.Utils.camelCaseToHyphen(actionType), {
                el: this.options.el + ' .workflow-action[data-id="' + id + '"]',
                actionData: data,
                model: this.model,
                entityType: this.entityType,
                actionType: actionType,
                id: id,
                actionId: actionId,
                isNew: isNew,
                readOnly: this.readOnly,
                flowcharElementId: this.options.flowcharElementId,
                flowchartCreatedEntitiesData: this.options.flowchartCreatedEntitiesData,
            }, function (view) {
                view.render(function () {
                    if (isNew) {
                        view.edit(true);
                    }
                });

                this.listenTo(view, 'change', function () {
                    this.trigger('change');
                }, this);
            });
        },

        fetch: function () {
            var actions = [];

            this.$el.find('.actions .workflow-action').each(function (index, el) {
                var actionId = $(el).attr('data-id');

                if (~actionId) {
                    var view = this.getView('action-' + actionId);

                    if (view) {
                        actions.push(view.fetch());
                    }
                }
            }.bind(this));

            return actions;
        },

    });
});
