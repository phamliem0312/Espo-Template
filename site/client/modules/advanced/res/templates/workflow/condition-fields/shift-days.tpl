{{#if readOnly}}
    {{translate shiftDaysOperator scope='Workflow'}} {{value}} {{translate 'days' scope='Workflow'}}
{{else}}
    <div class="row">
        <div class="col-sm-6">
            <select class="form-control input-sm" data-name="shiftDaysOperator">
                <option {{#ifEqual shiftDaysOperator 'plus'}}selected{{/ifEqual}} value="plus">{{translate 'plus' scope='Workflow'}}</option>
                <option {{#ifEqual shiftDaysOperator 'minus'}}selected{{/ifEqual}} value="minus">{{translate 'minus' scope='Workflow'}}</option>
            </select>
        </div>
        <div class="col-sm-6">
            <div class="input-group input-group-sm">
                <input data-name="shiftDays" class="form-control input-sm" value="{{value}}">
                <span class="small input-group-addon">{{translate 'days' scope='Workflow'}}</span>
            </div>
        </div>
    </div>
{{/if}}