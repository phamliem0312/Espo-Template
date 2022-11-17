
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
        {{#if linkTranslated}} <span class="text-muted">&raquo;</span> {{{linkTranslated}}}{{/if}}
        {{#if parentEntityTypeTranslated}} <span class="text-muted">&raquo;</span> {{{parentEntityTypeTranslated}}}{{/if}}

        <div class="margin-top">
            <div class="field hidden" data-name="formula">{{{formula}}}</div>
        </div>

    </div>
</div>