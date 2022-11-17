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

define('advanced:views/workflow/record/conditions', ['view', 'advanced:workflow-helper'], function (Dep, Helper) {

    return Dep.extend({

        template: 'advanced:workflow/record/conditions',

        ingoreFieldList: [],

        events: {
            'click [data-action="showAddCondition"]': function (e) {
                var $target = $(e.currentTarget);
                var conditionType = $target.data('type');

                this.createView('modal', 'advanced:views/workflow/modals/add-condition', {
                    scope: this.entityType,
                    createdEntitiesData: this.options.flowchartCreatedEntitiesData
                }, function (view) {
                    view.render();
                    this.listenToOnce(view, 'add-field', function (field) {
                        this.clearView('modal');
                        this.addCondition(conditionType, field, {}, true);
                    }, this);
                }, this);
            },
            'click [data-action="removeCondition"]': function (e) {
                var $target = $(e.currentTarget);
                var id = $target.data('id');
                this.clearView('condition-' + id);

                var $conditionContainer = $target.parent();
                var $container = $conditionContainer.parent();

                $conditionContainer.remove();

                if (!$container.find('.condition').length) {
                    $container.find('.no-data').removeClass('hidden');
                }

                this.trigger('change');
            }
        },

        data: function () {
            var hasConditionsAll = !!(this.model.get('conditionsAll') || []).length;
            var hasConditionsAny = !!(this.model.get('conditionsAny') || []).length;
            var hasConditionsFormula = !!(this.model.get('conditionsFormula') || '');

            return {
                fieldList: this.fieldList,
                entityType: this.entityType,
                readOnly: this.readOnly,
                hasFormula: this.hasFormula,
                showFormula: !this.readOnly || hasConditionsFormula,
                showConditionsAny: !this.readOnly || hasConditionsAny,
                showConditionsAll: !this.readOnly || hasConditionsAll,
                showNoData: this.readOnly && !hasConditionsFormula && !hasConditionsAny && !hasConditionsAll,
                marginForConditionsAny: !this.readOnly || hasConditionsAll,
                marginForFormula: !this.readOnly || hasConditionsAll || hasConditionsAny,
            }
        },

        afterRender: function () {
            var conditionsAll = this.model.get('conditionsAll') || [];
            var conditionsAny = this.model.get('conditionsAny') || [];

            var conditionsFormula = this.model.get('conditionsFormula') || '';

            conditionsAll.forEach(function (data) {
                this.addCondition('all', data.fieldToCompare, data);
            }, this);

            conditionsAny.forEach(function (data) {
                this.addCondition('any', data.fieldToCompare, data);
            }, this);

            if (this.hasFormula) {
                if (!this.readOnly || this.model.get('conditionsFormula')) {
                    this.createView('conditionsFormula', 'views/fields/formula', {
                        name: 'conditionsFormula',
                        model: this.model,
                        mode: this.readOnly ? 'detail' : 'edit',
                        height: 50,
                        el: this.getSelector() + ' .formula-conditions',
                        inlineEditDisabled: true,
                        targetEntityType: this.entityType,
                    }, function (view) {
                        view.render();

                        this.listenTo(view, 'change', function () {
                            this.trigger('change');
                        }, this);
                    }, this);
                }
            }
        },

        setup: function () {
            this.entityType = this.scope = this.options.entityType || this.model.get('entityType');

            this.hasFormula = !!this.getMetadata().get('app.formula.functionList');

            var conditionFieldTypes = this.getMetadata().get('entityDefs.Workflow.conditionFieldTypes') || {};
            var defs = this.getMetadata().get('entityDefs.' + this.entityType + '.fields');

            this.fieldList = Object.keys(defs).filter(function (field) {
                var type = defs[field].type || 'base';

                if (defs[field].disabled) {
                    return;
                }

                return !~this.ingoreFieldList.indexOf(field) && (type in conditionFieldTypes);
            }, this).sort(function (v1, v2) {
                 return this.translate(v1, 'fields', this.scope).localeCompare(this.translate(v2, 'fields', this.scope));
            }.bind(this));

            this.lastCid = 0;
            this.readOnly = this.options.readOnly || false;
        },

        addCondition: function (conditionType, field, data, isNew) {
            data = data || {};

            var fieldType;
            var link = null;
            var foreignField = null;
            var isCreatedEntity = false;

            var overridenEntityType = null;

            if (~field.indexOf('.')) {
                if (field.indexOf('created:') === 0) {
                    isCreatedEntity = true;
                    var arr = field.split('.');
                    var overridenField = arr[1];
                    var aliasId = arr[0].substr(8);

                    if (!this.options.flowchartCreatedEntitiesData || !this.options.flowchartCreatedEntitiesData[aliasId]) {
                        return;
                    }

                    var overridenEntityType = this.options.flowchartCreatedEntitiesData[aliasId].entityType;
                    var link = this.options.flowchartCreatedEntitiesData[aliasId].link;
                    var numberId = this.options.flowchartCreatedEntitiesData[aliasId].numberId;

                    fieldType = this.getMetadata().get(['entityDefs', overridenEntityType, 'fields', overridenField, 'type']) ||
                        'base';
                } else {
                    var arr = field.split('.');
                    foreignField = arr[1];
                    link = arr[0];
                    var foreignEntityType = this.getMetadata().get(['entityDefs', this.entityType, 'links', link, 'entity']);
                    fieldType = this.getMetadata().get(['entityDefs', foreignEntityType, 'fields', foreignField, 'type']) || 'base';
                }
            } else {
                fieldType = this.getMetadata().get(['entityDefs', this.entityType, 'fields', field, 'type']) || 'base';
            }

            var type = this.getMetadata().get('entityDefs.Workflow.conditionFieldTypes.' + fieldType) || 'base';

            var $container = this.$el.find('.' + conditionType.toLowerCase() + '-conditions');

            $container.find('.no-data').addClass('hidden');

            var id = data.cid  = this.lastCid;
            this.lastCid++;

            var label;

            var actualField = field;
            var actualEntityType = this.entityType;

            if (isCreatedEntity) {
                var labelLeftPart = this.translate('Created', 'labels', 'Workflow') + ': ';

                if (link) {
                    labelLeftPart += this.translate(link, 'links', this.entityType) + ' - ';
                }

                labelLeftPart += this.translate(overridenEntityType, 'scopeNames');

                var text = this.options.flowchartCreatedEntitiesData[aliasId].text;

                if (text) {
                    labelLeftPart += ' \'' + text + '\'';
                } else {
                    if (numberId) {
                        labelLeftPart += ' #' + numberId.toString();
                    }
                }

                label = labelLeftPart + '.' + this.translate(overridenField, 'fields', overridenEntityType);

                actualField = overridenField;
                actualEntityType = overridenEntityType;
            } else if (link) {
                label = this.translate(link, 'links', this.entityType) + '.' +
                    this.translate(foreignField, 'fields', foreignEntityType);

                actualField = foreignField;
                actualEntityType = foreignEntityType;
            } else {
                label = this.translate(field, 'fields', this.entityType);
            }

            var fieldNameHtml = '<label class="field-label-name control-label small">' + label + '</label>';
            var removeLinkHtml = this.readOnly ? '' :
                '<a href="javascript:" class="pull-right" data-action="removeCondition" data-id="'+id+'">'+
                '<span class="fas fa-times"></span></a>';

            var html = '<div class="cell form-group" style="margin-left: 20px;">' +
                removeLinkHtml + fieldNameHtml + '<div class="condition small" data-id="' + id + '"></div></div>';

            $container.append($(html));

            this.createView('condition-' + id, 'advanced:views/workflow/conditions/' + Espo.Utils.camelCaseToHyphen(type), {
                el: this.options.el + ' .condition[data-id="' + id + '"]',
                conditionData: data,
                model: this.model,
                field: field,
                entityType: overridenEntityType || this.entityType,
                originalEntityType: this.entityType,
                actualField: actualField,
                actualEntityType: actualEntityType,
                type: type,
                fieldType: fieldType,
                conditionType: conditionType,
                isNew: isNew,
                readOnly: this.readOnly,
                isChangedDisabled: this.options.isChangedDisabled,
            }, function (view) {
                view.render();

                if (isNew) {
                    var $form = view.$el.closest('.form-group');
                    $form.addClass('has-error');
                    setTimeout(function () {
                        $form.removeClass('has-error');
                    }, 1500);

                    this.trigger('change');
                }
            });
        },

        fetch: function () {
            var conditions = {
                all: [],
                any: [],
            };

            for (var i = 0; i < this.lastCid; i++) {
                var view = this.getView('condition-' + i);
                if (view) {
                    if (!(view.conditionType in conditions)) {
                        continue;
                    }

                    var data = view.fetch();

                    data.type = view.conditionType;
                    conditions[view.conditionType].push(data);
                }
            }

            if (this.hasFormula) {
                conditions.formula = (this.getView('conditionsFormula').fetch() || {}).conditionsFormula;
            }

            return conditions;
        },
    });
});
