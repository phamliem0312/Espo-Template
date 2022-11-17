<div class="cell form-group" data-name="exportFormat">
    <label class="control-label" data-name="exportFormat">{{translate 'exportFormat' category='fields' scope='Report'}}</label>
    <div class="field" data-name="exportFormat">{{{exportFormat}}}</div>
</div>

{{#if column}}
<div class="cell form-group" data-name="column">
    <label class="control-label" data-name="column">{{translate 'column' category='fields' scope='Report'}}</label>
    <div class="field" data-name="column">{{{column}}}</div>
</div>
{{/if}}