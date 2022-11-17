{{#if showConditionsAll}}
<div>
    <h5>{{translate 'All' scope='Workflow'}} <small>({{translate 'allMustBeMet' category='texts' scope='Workflow'}})</small></h5>
    <div class="all-conditions">
        <div class="no-data form-group small" style="margin-left: 20px;">{{translate 'No Data'}}</div>
    </div>
    {{#unless readOnly}}
        <button class="btn btn-default btn-sm btn-icon" type="button" data-action="showAddCondition" data-type="all" title="{{translate 'Add Condition' scope='Workflow'}}"><span class="fas fa-plus"></span></button>
    {{/unless}}
</div>
{{/if}}
{{#if showConditionsAny}}
<div {{#if marginForConditionsAny}}style="margin-top: 30px;"{{/if}}>
    <h5>{{translate 'Any' scope='Workflow'}} <small>({{translate 'atLeastOneMustBeMet' category='texts' scope='Workflow'}})</small></h5>
    <div class="any-conditions">
        <div class="no-data form-group small" style="margin-left: 20px;">{{translate 'No Data'}}</div>
    </div>
    {{#unless readOnly}}
        <button class="btn btn-default btn-sm btn-icon" type="button" data-action="showAddCondition" data-type="any" title="{{translate 'Add Condition' scope='Workflow'}}"><span class="fas fa-plus"></span></button>
    {{/unless}}
</div>
{{/if}}
{{#if hasFormula}}
{{#if showFormula}}
<div {{#if marginForFormula}}style="margin-top: 30px;"{{/if}}>
    <h5>{{translate 'Formula' scope='Workflow'}} <small>({{translate 'formulaInfo' category='texts' scope='Workflow'}})</small></h5>
    <div class="formula-conditions" {{#if readOnly}}style="margin-left: 20px;"{{/if}}>
    </div>
</div>
{{/if}}
{{/if}}

{{#if showNoData}}
<div class="no-data small">
    {{translate 'No Data'}}
</div>
{{/if}}
