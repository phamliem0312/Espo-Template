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
            <div class="cell form-group" data-name="target">
                <label class="control-label">{{translate 'target' category='fields' scope='Workflow'}}</label>
                <div class="field">{{{targetTranslated}}}</div>
            </div>

            {{#if actionData.flowchartId}}
                <div class="field-row cell form-group" data-name="flowchart">
                    <label class="control-label">{{translate 'BpmnFlowchart' category='scopeNames'}}</label>
                    <div class="field" data-name="flowchart">{{{flowchart}}}</div>
                </div>
            {{/if}}

            {{#if actionData.elementId}}
                <div class="field-row cell form-group" data-name="elementId">
                    <label class="control-label">{{translate 'startElementId' scope='BpmnProcess' category='fields'}}</label>
                    <div class="field" data-name="elementId">{{{elementId}}}</div>
                </div>
            {{/if}}
        </div>
    </div>
</div>
