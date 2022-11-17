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

define('advanced:views/workflow/action-modals/execute-formula',
    ['advanced:views/workflow/action-modals/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        template: 'advanced:workflow/action-modals/execute-formula',

        setup: function () {
            Dep.prototype.setup.call(this);

            var model = new Model;

            model.set('formula', this.actionData.formula || null);

            this.createView('formula', 'views/fields/formula', {
                name: 'formula',
                model: model,
                mode: this.readOnly ? 'detail' : 'edit',
                height: 200,
                el: this.getSelector() + ' .field[data-name="formula"]',
                inlineEditDisabled: true,
                targetEntityType: this.entityType,
            });
        },

        fetch: function () {
            var formulaView = this.getView('formula');

            this.actionData.formula = formulaView.fetch().formula;

            return true;
        },

    });
});
