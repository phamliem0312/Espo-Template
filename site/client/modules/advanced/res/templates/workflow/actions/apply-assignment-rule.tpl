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
            {{#if hasTarget}}
                <div class="cell form-group">
                    <label class="control-label">{{translate 'Entity' scope='Workflow' category='labels'}}</label>
                    <div class="field" data-name="target">{{{targetTranslated}}}
                    </div>
                </div>
            {{/if}}
            <div class="cell form-group">
                <label class="control-label">{{translate 'assignmentRule' scope='Workflow' category='fields'}}</label>
                <div class="field" data-name="assignmentRule">
                </div>
            </div>

            <div class="cell form-group">
                <label class="control-label">{{translate 'targetTeam' scope='Workflow' category='fields'}}</label>
                <div class="field" data-name="targetTeam"></div>
            </div>

            <div class="cell form-group">
                <label class="control-label">{{translate 'targetUserPosition' scope='Workflow' category='fields'}}</label>
                <div class="field" data-name="targetUserPosition"></div>
            </div>

            {{#if hasListReport}}
            <div class="cell form-group">
                <label class="control-label">{{translate 'listReport' scope='Workflow' category='fields'}}</label>
                <div class="field" data-name="listReport"></div>
            </div>
            {{/if}}
        </div>
    </div>
</div>