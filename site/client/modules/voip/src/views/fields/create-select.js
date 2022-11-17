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

Espo.define('voip:views/fields/create-select', 'views/fields/base', function (Dep) {

    return Dep.extend({

        type: 'create-select',

        listTemplate: 'voip:fields/create-select/detail',

        detailTemplate: 'voip:fields/create-select/detail',

        editTemplate: 'voip:fields/create-select/detail',

        voipFieldList: [],

        viewName: null,

        fieldName: null,

        data: function () {
            return _.extend({
                displayedFieldList: [{
                    fieldName: this.fieldName,
                    viewName: this.viewName
                }]
            }, Dep.prototype.data.call(this));
        },

        setup: function () {
            var fieldEntity = this.options.foreignScope;
            var fieldData = this.options.voipFieldData;
            var ids = Object.keys(fieldData);

            this.fieldName = this.toModelFieldName(fieldEntity);
            this.viewName = this.fieldName + 'Field';

            var view = 'voip:views/fields/link';
            var readOnly = true;
            var modelData = {};

            switch (ids.length) {
                case 0:
                    readOnly = false;

                    this.model.defs.fields[this.fieldName] = {type: "link"};
                    this.model.defs.fields[this.fieldName + 'Id'] = {type: "varchar"};
                    this.model.defs.fields[this.fieldName + 'Name'] = {type: "varchar"};

                    modelData[this.fieldName + 'Id'] = null;
                    modelData[this.fieldName + 'Name'] = null;
                    break;

                case 1:
                    this.model.defs.fields[this.fieldName] = {type: "link"};
                    this.model.defs.fields[this.fieldName + 'Id'] = {type: "varchar"};
                    this.model.defs.fields[this.fieldName + 'Name'] = {type: "varchar"};

                    ids.forEach(function(id){
                        modelData[this.fieldName + 'Id'] = id;
                        modelData[this.fieldName + 'Name'] = fieldData[id].name;
                    }.bind(this));
                    break;

                default:
                    view = 'views/fields/link-multiple';

                    this.model.defs.fields[this.fieldName] = {type: "linkMultiple"};
                    this.model.defs.fields[this.fieldName + 'Ids'] = {type: "jsonArray"};
                    this.model.defs.fields[this.fieldName + 'Names'] = {type: "jsonObject"};

                    modelData[this.fieldName + 'Ids'] = ids;
                    modelData[this.fieldName + 'Names'] = {};

                    ids.forEach(function(id) {
                        modelData[this.fieldName + 'Names'][id] = fieldData[id].name;
                    }.bind(this));
                    break;
            }

            this.model.set(modelData);
            this.voipFieldList = Object.keys(modelData);

            this.createView(this.viewName, view, {
                el: this.options.el + ' .field[data-name="' + this.fieldName + '"]',
                model: this.model,
                mode: 'edit',
                foreignScope: fieldEntity,
                defs: {
                    name: this.fieldName
                },
                readOnly: readOnly,
                createDisabled: this.getAcl().check(fieldEntity, 'create') ? false : true
            });
        },

        toModelFieldName: function (fieldName) {
            return 'voip' + Espo.Utils.upperCaseFirst(fieldName);
        },

    });

});
