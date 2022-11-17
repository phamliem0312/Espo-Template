{{#if readOnly}}
    {{translateOption value scope='Workflow' field='subjectType'}}
{{else}}
    <select data-name="subjectType" class="form-control input-sm">
        {{options list value scope='Workflow' field='subjectType'}}
    </select>
{{/if}}
