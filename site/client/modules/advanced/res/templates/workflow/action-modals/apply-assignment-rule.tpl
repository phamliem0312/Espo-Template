{{#if target}}
<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'Entity' scope='Workflow' category='labels'}}</label>
        <div class="field" data-name="target">
            {{{target}}}
        </div>
    </div>
</div>
{{/if}}
<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'assignmentRule' scope='Workflow' category='fields'}}</label>
        <div class="field" data-name="assignmentRule">
            {{{assignmentRule}}}
        </div>
    </div>
</div>
<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'targetTeam' scope='Workflow' category='fields'}}</label>
        <div class="field" data-name="targetTeam">
            {{{targetTeam}}}
        </div>
    </div>
</div>
<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'targetUserPosition' scope='Workflow' category='fields'}}</label>
        <div class="field" data-name="targetUserPosition">
            {{{targetUserPosition}}}
        </div>
    </div>
</div>

<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'listReport' scope='Workflow' category='fields'}}</label>
        <div class="field" data-name="listReport">
            {{{listReport}}}
        </div>
    </div>
</div>
