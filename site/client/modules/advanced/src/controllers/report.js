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

Espo.define('advanced:controllers/report', 'controllers/record', function (Dep) {

    return Dep.extend({

        create: function (options) {
            options = options || {};

            var hasAttributes = !!options.attributes;

            options.attributes = options.attributes || {};

            if ('type' in options) {
                options.attributes.type = options.type;
            } else if (!options.attributes.type) {
                options.attributes.type = 'Grid';
            }

            if ('entityType' in options) {
                options.attributes.entityType = options.entityType;
            } else {
                if (!hasAttributes && options.attributes.type !== 'JointGrid') {
                    throw new Espo.Exceptions.NotFound();
                }
            }
            if ('categoryId' in options) {
                options.attributes.categoryId = options.categoryId;
            }
            if ('categoryName' in options) {
                options.attributes.categoryName = options.categoryName;
            }

            Dep.prototype.create.call(this, options);
        },

        actionShow: function (options) {
            var id = options.id;

            if (!id) throw new Espo.Exceptions.NotFound("No report id.");

            var createView = function (model) {
                var view = this.getViewName('result');
                this.main(view, {
                    scope: this.name,
                    model: model,
                    returnUrl: options.returnUrl,
                    returnDispatchParams: options.returnDispatchParams,
                    params: options
                });
            }.bind(this);

            var model = options.model;

            if (model && model.id && model.get('type') && model.get('groupBy')) {
                createView(model);

                this.showLoadingNotification();

                model.fetch().then(function () {
                    this.hideLoadingNotification();
                }.bind(this));

                this.listenToOnce(this.baseController, 'action', function () {
                    model.abortLastFetch();
                }, this);
            } else {
                this.getModel().then(function (model) {
                    model.id = id;

                    this.showLoadingNotification();

                    model.fetch({main: true}).then(function () {
                        createView(model);
                    }.bind(this));

                    this.listenToOnce(this.baseController, 'action', function () {
                        model.abortLastFetch();
                    }, this);
                }.bind(this));
            }
        },
    });
});