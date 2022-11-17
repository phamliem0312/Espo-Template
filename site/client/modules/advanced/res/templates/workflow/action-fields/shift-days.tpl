{{#if readOnly}}
    {{translate shiftDaysOperator scope='Workflow' category='labels'}} {{value}} {{translate unitValue scope='Workflow' category='labels'}}
{{else}}
<div class="row">
    <div class="col-sm-4">
        <select class="form-control" name="shiftDaysOperator">
            <option {{#ifEqual shiftDaysOperator 'plus'}}selected{{/ifEqual}} value="plus">{{translate 'plus' scope='Workflow' category='labels'}}</option>
            <option {{#ifEqual shiftDaysOperator 'minus'}}selected{{/ifEqual}} value="minus">{{translate 'minus' scope='Workflow' category='labels'}}</option>
        </select>
    </div>
    <div class="col-sm-8">
        <div class="input-group">
            <input name="shiftDays" class="form-control" value="{{value}}">
            <span class="input-group-btn">
            <select class="form-control" name="shiftUnit">
                <option {{#ifEqual unitValue 'days'}}selected{{/ifEqual}} value="days">{{translate 'days' scope='Workflow' category='labels'}}</option>
                <option {{#ifEqual unitValue 'hours'}}selected{{/ifEqual}} value="hours">{{translate 'hours' scope='Workflow' category='labels'}}</option>
                <option {{#ifEqual unitValue 'minutes'}}selected{{/ifEqual}} value="minutes">{{translate 'minutes' scope='Workflow' category='labels'}}</option>
                <option {{#ifEqual unitValue 'months'}}selected{{/ifEqual}} value="months">{{translate 'months' scope='Workflow' category='labels'}}</option>
            </select>
            </span>
        </div>
    </div>
</div>
{{/if}}