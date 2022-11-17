<div class="row filters-row grid-auto-fill-xs">
    {{#each filterDataList}}
        <div class="filter col-sm-4 col-md-3" data-name="{{name}}">{{{var key ../this}}}</div>
    {{/each}}
</div>
