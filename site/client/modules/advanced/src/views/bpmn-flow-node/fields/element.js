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

Espo.define('advanced:views/bpmn-flow-node/fields/element', 'views/fields/varchar', function (Dep) {

    return Dep.extend({

        listTemplate: 'advanced:bpmn-flow-node/fields/element/detail',

        getValueForDisplay: function () {
            var stringValue = this.translate(this.model.get('elementType'), 'elements', 'BpmnFlowchart');

            var elementData = this.model.get('elementData') || {};
            var data = this.model.get('data') || {};

            var text = elementData.text;
            if (text) {
                stringValue += ': ' + text + '';
            }

            var elementType = this.model.get('elementType') ;

            if (elementType === 'taskUser' && this.model.get('userTaskId')) {
                stringValue = '<a href="#BpmnUserTask/view/'+this.model.get('userTaskId')+'">' + stringValue + '</a>';
            }

            if (elementType === 'callActivity' || elementType === 'subProcess' || elementType === 'eventSubProcess') {
                if (
                    (elementData.callableType === 'Process' || elementType === 'subProcess' || elementType === 'eventSubProcess') &&
                    data.subProcessId
                ) {
                    stringValue = '<a href="#BpmnProcess/view/' + data.subProcessId + '">' + stringValue + '</a>';
                }
                if (data.errorTriggered) {
                    var errorPart = this.translate('Error', 'labels', 'BpmnFlowchart');;
                    if (data.errorCode) {
                        errorPart += ' ' + data.errorCode;
                    }
                    stringValue += ': <span class="text-danger">' + errorPart + '</span>';
                }
            }

            if (
                elementType === 'eventIntermediateConditionalBoundary' || elementType === 'eventStartConditionalEventSubProcess'
            ) {
                if (data.isOpposite) {
                    stringValue = this.translate('Reset', 'labels', 'BpmnFlowNode') + ': ' + stringValue;
                }
            }

            var text = Handlebars.Utils.escapeExpression(this.getHelper().stripTags(stringValue));

            stringValue = '<span title="'+text+'">'+stringValue+'</span>';

            return stringValue;
        },

    });
});
