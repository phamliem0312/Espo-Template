<div class="row">
    <div class="col-md-4">
        <select data-name="timerBase" class="form-control">
            {{#each timerBaseOptionDataList}}
            <option value="{{value}}"{{#if isSelected}} selected{{/if}}>{{label}}</option>
            {{/each}}
        </select>
    </div>
    <div class="col-md-2">
        <select data-name="timerShiftOperator" class="form-control hidden">
            {{#each timerShiftOperatorOptionDataList}}
            <option value="{{value}}"{{#if isSelected}} selected{{/if}}>{{label}}</option>
            {{/each}}
        </select>
    </div>
    <div class="col-md-3">
        <input data-name="timerShift" class="form-control hidden" value="{{timerShiftValue}}">
    </div>
    <div class="col-md-3">
        <select data-name="timerShiftUnits" class="form-control hidden">
            {{#each timerShiftUnitsOptionDataList}}
            <option value="{{value}}"{{#if isSelected}} selected{{/if}}>{{label}}</option>
            {{/each}}
        </select>
    </div>
</div>

<div class="formula-container">{{{timerFormula}}}</div>