<link href="client/modules/voip/css/voip-notification.css" rel="stylesheet">
<div class="call-item">
    <div class="cell cell-name">
        <div class="field field-phone">
            <h4> {{#if notificationData.line}} [{{translate notificationData.lineId scope='Integration' category='voipLines'}}]:{{/if}} {{#if notificationData.phoneNumber}}{{notificationData.phoneNumber}}{{else}}{{translate 'Unknown' category='labels' scope='VoipEvent'}}{{/if}} </h4>
        </div>
    </div>

    {{#each displayedFieldList}}
    <div class="cell cell-{{fieldName}} row">
        <div class="cell col-sm-5">
            <label class="field-label-{{fieldName}} control-label">{{translate scope category='scopeNames'}}: </label>
        </div>

        <div class="col-sm-7">
            <div class="cell cell-{{fieldName}} form-group" data-name="{{fieldName}}">
                {{{var viewName ../this}}}
            </div>
        </div>
    </div>
    {{/each}}

    {{#if notificationData.quickCreateEntities}}
        {{#each notificationData.quickCreateEntities}}
        <div class="cell cell-{{toLowerCase ./this}} row">
            <div class="cell col-sm-5">
                <label class="field-label-{{./this}} control-label">{{translate ./this category='scopeNames'}}: </label>
            </div>

            <div class="col-sm-7">
                <div class="field field-{{toLowerCase ./this}}"> <a href="#{{./this}}/create" data-action="quickCreateEntity" data-scope="{{./this}}">{{translate 'Create'}}</a> </div>
            </div>
        </div>
        {{/each}}
    {{/if}}

    {{#each additionalFieldList}}
    <div class="cell cell-{{fieldName}} row">

        {{#unless this.fullWidth}}
            <div class="cell col-sm-5">
                <label class="field-label-{{fieldName}} control-label">{{translateFieldLabel fieldName}}: </label>
            </div>
        {{/unless}}

        <div class="col-sm-{{#if this.fullWidth}}12{{else}}7{{/if}}">
            <div class="field field-{{fieldName}}" data-name="{{fieldName}}"> {{{var viewName ../this}}} </div>
        </div>

    </div>
    {{/each}}

    <div class="button-container">
        <div class="btn-group btn-group-sm" role="group">
            {{#unless editDisabled}}
            <button class="btn btn-primary" data-action="save">{{translate 'Save'}}</button>
            {{/unless}}
            <button class="btn btn-default" data-action="cancel">{{translate 'Cancel'}}</button>
        </div>
        {{#if forwardEnabled}}
        <button class="btn btn-info pull-right" data-action="forward">{{translate 'Forward'}}</button>
        {{/if}}
    </div>


</div>
