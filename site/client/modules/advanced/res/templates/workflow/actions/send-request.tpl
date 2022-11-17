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

        <div class="field-list small margin-top">
            <div class="cell form-group" data-name="requestType">
                <label class="control-label">{{translate 'requestType' category='fields' scope='Workflow'}}</label>
                <div class="field" data-name="requestType">{{{requestType}}}</div>
            </div>
            <div class="cell form-group" data-name="contentType">
                <label class="control-label">{{translate 'requestContentType' category='fields' scope='Workflow'}}</label>
                <div class="field" data-name="contentType">{{{contentType}}}</div>
            </div>
            <div class="cell form-group" data-name="requestUrl">
                <label class="control-label">{{translate 'requestUrl' category='fields' scope='Workflow'}}</label>
                <div class="field" data-name="requestUrl">{{{requestUrl}}}</div>
            </div>
            <div class="cell form-group" data-name="content">
                <label class="control-label">{{translate 'requestContent' category='fields' scope='Workflow'}}</label>
                <div class="field" data-name="content">{{{content}}}</div>
            </div>
            <div class="cell form-group" data-name="headers">
                <label class="control-label">{{translate 'headers' category='fields' scope='Workflow'}}</label>
                <div class="field" data-name="headers">{{{headers}}}</div>
            </div>
        </div>
    </div>
</div>
