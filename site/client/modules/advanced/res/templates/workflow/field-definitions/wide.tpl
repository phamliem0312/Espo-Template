{{#if readOnly}}
    <div class="subject">
        {{{subject}}}
    </div>
{{else}}
<div class="row">
    <div class="col-sm-3 subject-type">
        {{readOnly}}
        <select class="form-control" name="subjectType">
            {{options subjectTypeList subjectTypeValue field='subjectType' scope='Workflow'}}
        </select>
    </div>

    <div class="col-sm-9 subject">
        {{{subject}}}
    </div>
</div>
{{/if}}