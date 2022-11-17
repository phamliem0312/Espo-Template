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

            <div class="cell form-group" data-name="whatToFollow">
                <label class="control-label">{{translate 'whatToFollow' category='fields' scope='Workflow'}}</label>
                <div class="field">{{{targetTranslated}}}</div>
            </div>

            <div class="cell form-group" data-name="recipient">
                <label class="control-label">{{translate 'whoFollow' category='fields' scope='Workflow'}}</label>
                <div class="field" data-name="recipient">{{{recipient}}}</div>
            </div>

            <div class="cell form-group" data-name="usersToMakeToFollow">
                <label class="control-label">{{translate 'User' category='scopeNamesPlural'}}</label>
                <div class="field" data-name="usersToMakeToFollow">{{{usersToMakeToFollow}}}</div>
            </div>

            <div class="cell form-group" data-name="specifiedTeams">
                <label class="control-label">{{translate 'Team' category='scopeNamesPlural'}}</label>
                <div class="field" data-name="specifiedTeams">{{{specifiedTeams}}}</div>
            </div>

        </div>

    </div>
</div>

