{{#if readOnly}}
    {{translate type scope='Workflow' category='labels'}}
    <span class="field-container hidden">{{{field}}}</span>
    <span class="shift-days-container hidden">{{{shiftDays}}}</span>
{{else}}
    <div class="row">
        <div class="col-sm-2">
            <select name="executionType" class="form-control">
                <option value="immediately" {{#ifEqual type 'immediately'}} selected{{/ifEqual}}>{{translate 'immediately' scope='Workflow' category='labels'}}</option>
                <option value="later" {{#ifEqual type 'later'}} selected{{/ifEqual}}>{{translate 'later' scope='Workflow' category='labels'}}</option>
            </select>
        </div>
        <div class="field-container col-sm-2 hidden">{{{field}}}</div>
        <div class="shift-days-container col-sm-5 hidden">{{{shiftDays}}}</div>
    </div>
{{/if}}