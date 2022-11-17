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
            {{#if actionData.recipient}}
                <div class="field-row cell form-group">
                    <label class="control-label">{{translate 'recipient' scope='Workflow'}}</label>
                    <div class="field-container">
                        {{recipientLabel}}
                    </div>
                    <div class="field-recipient" data-field="recipient">
                    </div>
                </div>
            {{/if}}

            {{#if actionData.messageTemplate}}
                <div class="field-row cell form-group" data-field="messageTemplate">
                    <label class="control-label">{{translate 'messageTemplate' scope='Workflow' category='labels'}}</label>
                    <div class="field-container field field-messageTemplate" data-field="messageTemplate">{{{messageTemplate}}}</div>
                </div>
            {{/if}}
        </div>
    </div>
</div>