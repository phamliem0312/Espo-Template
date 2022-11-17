<div class="row">
    {{#unless readOnly}}
        <div class="col-md-1">
            <button class="btn btn-default btn-sm btn-icon" type="button" data-action='editAction'><span class="fas fa-pencil-alt fa-sm"></span></button>
            <div>
                <a class="btn btn-text btn-sm btn-icon drag-handle"><span class="fas fa-magnet fa-sm"></span></a>
            </div>
        </div>
    {{/unless}}

    <div class="col-md-10">
        {{translate actionType scope='Workflow' category='actionTypes'}}

        <div class="field-list small" style="margin-top: 12px;">
            <div class="field-row cell form-group execution-time-container" data-field="execution-time">
                <div class="field" data-field="execution-time">{{{executionTime}}}</div>
            </div>

            {{#if actionData.from}}
                <div class="field-row cell form-group" data-field="from">
                    <label class="control-label">{{translate 'From' scope='Workflow'}}</label>
                    <div class="field-container field" data-field="from">
                        {{#ifEqual actionData.from 'specifiedEmailAddress'}}
                            {{actionData.fromEmail}}
                        {{else}}
                            {{fromLabel}}
                        {{/ifEqual}}
                    </div>
                </div>
            {{/if}}

            {{#if actionData.to}}
                <div class="field-row cell form-group" data-field="to">
                    <label class="control-label">{{translate 'To' scope='Workflow'}}</label>
                    <div class="field-container field" data-field="to">
                        {{#ifEqual actionData.to 'specifiedEmailAddress'}}
                            {{actionData.toEmail}}
                        {{else}}
                            {{toLabel}}
                        {{/ifEqual}}
                        {{#ifEqual actionData.to 'specifiedTeams'}}
                            <div class="field-container field field-toSpecifiedTeams" data-field="toSpecifiedTeams">{{{toSpecifiedTeams}}}</div>
                        {{/ifEqual}}
                        {{#ifEqual actionData.to 'specifiedUsers'}}
                            <div class="field-container field field-toSpecifiedUsers" data-field="toSpecifiedUsers">{{{toSpecifiedUsers}}}</div>
                        {{/ifEqual}}
                        {{#ifEqual actionData.to 'specifiedContacts'}}
                            <div class="field-container field field-toSpecifiedContacts" data-field="toSpecifiedContacts">{{{toSpecifiedContacts}}}</div>
                        {{/ifEqual}}
                    </div>
                </div>
            {{/if}}

            {{#if actionData.replyTo}}
                <div class="field-row cell form-group" data-field="replyTo">
                    <label class="control-label">{{translate 'Reply-To' scope='Workflow'}}</label>
                    <div class="field-container field" data-field="replyTo">
                        {{#ifEqual actionData.replyTo 'specifiedEmailAddress'}}
                            {{actionData.replyToEmail}}
                        {{else}}
                            {{replyToLabel}}
                        {{/ifEqual}}
                    </div>
                </div>
            {{/if}}

            {{#if actionData.emailTemplateId}}
                <div class="field-row cell form-group" data-field="emailTemplate">
                    <label class="control-label">{{translate 'Email Template' scope='Workflow' category='labels'}}</label>
                    <div class="field-container field" data-field="emailTemplate">{{{emailTemplate}}}</div>
                </div>
            {{/if}}

            {{#if actionData.doNotStore}}
                <div class="field-row cell form-group" data-field="doNotStore">
                    <label class="control-label">{{translate 'doNotStore' scope='Workflow'}}</label>
                    <div class="field-container field-doNotStore" data-field="doNotStore">{{{doNotStore}}}</div>
                </div>
            {{/if}}
            <div class="field-row cell form-group" data-name="optOutLink">
                <label class="control-label">{{translate 'optOutLink' scope='Workflow' category='fields'}}</label>
                <div class="field-container field" data-name="optOutLink">{{{optOutLink}}}</div>
            </div>
        </div>
    </div>
</div>
