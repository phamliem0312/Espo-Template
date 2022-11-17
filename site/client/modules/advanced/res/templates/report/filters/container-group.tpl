

<a href="javascript:" class="pull-right" data-action="removeGroup" style="left: 50px; position: relative;"><span class="fas fa-times"></span></a>

<div>
    <span class="">{{#if showGroupTypeLabel}}<span>{{translate type category='filtersGroupTypes' scope='Report'}}</span> {{/if}}(</label>
</div>
<div class="node" style="{{#unless noOffset}}left: 50px; position: relative;{{else}}margin-left: 30px;{{/unless}}">{{{node}}}</div>
<div class="form-group">)</div>