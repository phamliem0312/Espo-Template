{{#if readOnly}}
    <span class="subject">
        {{#if subject}} {{{subject}}} {{else}} {{translate 'today' scope='Workflow' category='labels'}} {{/if}}
    </span>
    <span class="shift-days">
        {{{shiftDays}}}
    </span>
{{else}}
<div class="row">
    <div class="col-sm-2 subject-type">
        <select class="form-control" name="subjectType">
            {{options subjectTypeList subjectTypeValue field='subjectType' scope='Workflow'}}
        </select>
    </div>

    <div class="col-sm-2 subject">
        {{{subject}}}
    </div>

    <div class="col-sm-4 shift-days">
        {{{shiftDays}}}
    </div>
</div>
{{/if}}