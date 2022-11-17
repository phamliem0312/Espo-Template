
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
        {{translate actionType scope='Workflow' category='actionTypes'}}  <span class="text-muted">&raquo;</span> {{{linkTranslated}}}

        <div class="field-list small" style="margin-top: 12px;">
            <div class="field-row cell form-group" data-name="entity">
                <div class="field-container field" data-name="entity"></div>
            </div>
        </div>
    </div>
</div>