<div class="button-container">
    <button class="btn btn-primary" data-action="save">{{translate 'Save'}}</button>
    <button class="btn btn-default" data-action="cancel">{{translate 'Cancel'}}</button>
</div>

<div class="row">
    <div class="col-sm-6">
        <div class="panel">
            <div class="panel-body panel-body-form">

                <div class="cell cell-enabled form-group" data-name="enabled">
                    <label class="control-label" data-name="enabled">{{translate 'enabled' scope='Integration' category='fields'}}</label>
                    <div class="field field-enabled" data-name="enabled">{{{enabled}}}</div>
                </div>

                {{#each dataFieldList}}
                    <div class="cell cell-{{./this}} form-group" data-name="{{./this}}">

                        {{#ifEqual ./this 'accessKey'}}
                            <div class="row">
                                <div class="col-sm-6 cell">
                                    <label class="control-label field-label-{{./this}}" data-name="{{./this}}">{{translate this scope='Integration' category='fields'}}</label>
                                </div>
                                <div class="col-sm-6 text-right">
                                    <a href="javascript:" data-action="generateKey">{{translate 'Generate' scope='Integration' category='labels'}}</a>
                                </div>
                            </div>
                        {{else}}
                            <label class="control-label field-label-{{./this}}" data-name="{{./this}}">{{translate this scope='Integration' category='fields'}}</label>
                        {{/ifEqual}}

                        <div class="field field-{{this}}" data-name="{{./this}}">{{{var this ../this}}}</div>

                    </div>
                {{/each}}

                {{#if testConnectionButton}}
                    <div class="cell" data-name="testConnection">
                        <div class="field" data-name="testConnection">
                            <button class="btn btn-default" data-action="testConnection">{{translate 'testConnection' scope='Integration' category='labels'}}</button>
                        </div>
                    </div>
                {{/if}}

                <div class="cell row form-group field-addConnector" data-name="addConnector" style="margin-top:15px">
                    <div class="col-sm-6" data-name="addConnector">
                        <button class="btn btn-success" data-action="addConnector">{{translate 'Add a new connector' scope='Integration' category='labels'}}</button>
                    </div>

                    <div class="col-sm-6" data-name="removeConnector">
                        <button class="btn btn-danger pull-right" data-action="removeConnector" {{#unless isCustom}}disabled{{/unless}}>{{translate 'Remove this connector' scope='Integration' category='labels'}}</button>
                    </div>
                </div>

            </div>
        </div>
    </div>
    <div class="col-sm-6">
        {{#if helpText}}
        <div class="well">
            {{complexText helpText}}
        </div>
        {{/if}}
    </div>
</div>

<div class="second-button-container">
    <button class="btn btn-primary" data-action="save">{{translate 'Save'}}</button>
    <button class="btn btn-default" data-action="cancel">{{translate 'Cancel'}}</button>
</div>
