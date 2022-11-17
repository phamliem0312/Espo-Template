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

Espo.define('advanced:views/bpmn-flowchart-element/fields/conditions', ['views/fields/base', 'model'], function (Dep, Model) {

    return Dep.extend({

        detailTemplate: 'advanced:bpmn-flowchart-element/fields/conditions/detail',

        editTemplate: 'advanced:bpmn-flowchart-element/fields/conditions/detail',

        setup: function () {
            Dep.prototype.setup.call(this);

            this.conditionsModel = new Model();

            this.conditionsModel.set({
                conditionsAll: this.model.get('conditionsAll') || [],
                conditionsAny: this.model.get('conditionsAny') || [],
                conditionsFormula: this.model.get('conditionsFormula') || null
            });

            var isChangedDisabled = true;
            var flowchartCreatedEntitiesData = this.model.flowchartCreatedEntitiesData;
            if (this.model.elementType === 'eventStartConditional') {
                flowchartCreatedEntitiesData = null;
                isChangedDisabled = false;
            }

            this.createView('conditions', 'advanced:views/workflow/record/conditions', {
                entityType: this.model.targetEntityType,
                el: this.getSelector() + ' > .conditions-container',
                readOnly: this.mode !== 'edit',
                model: this.conditionsModel,
                flowchartCreatedEntitiesData: flowchartCreatedEntitiesData,
                isChangedDisabled: isChangedDisabled
            });
        },

        fetch: function () {
            var conditionsData = this.getView('conditions').fetch();

            return {
                'conditionsAll': conditionsData.all,
                'conditionsAny': conditionsData.any,
                'conditionsFormula': conditionsData.formula,
            };
        }

    });

});