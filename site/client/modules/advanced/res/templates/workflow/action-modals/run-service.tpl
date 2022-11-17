<div class="cell cell-usersToMakeToFollow form-group">
    <div class="row">
        <div class="cell col-sm-6 form-group">
            <label class="control-label">{{translate 'Entity' scope='Workflow' category='labels'}}</label>
            <div class="field" data-name="target">
                {{{target}}}
            </div>
        </div>
    </div>

    <div class="row">
        <div class="cell col-sm-6 form-group">
            <label class="control-label field-label-methodName">{{translate 'methodName' category='labels' scope='Workflow'}}</label>
            <div class="field" data-name="methodName">{{{methodName}}}</div>
        </div>
    </div>

    <div class="row">
        <div class="cell col-sm-12 form-group">
            <label class="control-label field-label-additionalParameters">{{translate 'additionalParameters' category='labels' scope='Workflow'}}</label>
            <div class="field" data-name="additionalParameters">{{{additionalParameters}}}</div>
        </div>
    </div>

    <div class="row">
        <div class="cell col-sm-12 form-group">
            <div class="field" data-name="helpText">{{{helpText}}}</div>
        </div>
    </div>
</div>