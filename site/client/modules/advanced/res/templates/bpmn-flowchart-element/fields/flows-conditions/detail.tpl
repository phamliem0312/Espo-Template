<div class="flow-list-container list-group">
    {{#each flowDataList}}
    <div class="list-group-item" data-id="{{id}}">
        <div>
            {{#if ../isEditMode}}
            <div class="btn-group pull-right">
                <button class="btn btn-default{{#if isTop}} hidden{{/if}}" data-action="moveUp" data-id="{{id}}"><span class="fas fa-arrow-up"></span></button>
                <button class="btn btn-default{{#if isBottom}} hidden{{/if}}" data-action="moveDown" data-id="{{id}}"><span class="fas fa-arrow-down"></span></button>
            </div>
            {{/if}}
            <h5>{{label}}</h5>
        </div>
        <div class="flow" data-id="{{id}}" style="margin-top: 20px;">{{{var id ../this}}}</div>
    </div>
    {{/each}}
</div>