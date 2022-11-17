
<ul class="list-group list-group-side list-group-no-border">
    <li data-id="all" class="list-group-item{{#ifEqual 'all' selectedFolderId}} selected{{/ifEqual}}">
        <a href="#VoipMessage/list/folder=all" data-action="selectFolder" data-id="all" class="side-link">{{translate 'all' category='presetFilters' scope='VoipMessage'}}</a>
    </li>
    {{#each collection.models}}
    <li data-id="{{get this 'id'}}" class="list-group-item{{#ifAttrEquals this 'id' ../selectedFolderId}} selected{{/ifAttrEquals}}">
        <a href="#VoipMessage/list/folder={{get this 'id'}}" data-action="selectFolder" data-id="{{get this 'id'}}" class="side-link pull-right count"></a>
        <a href="#VoipMessage/list/folder={{get this 'id'}}" data-action="selectFolder" data-id="{{get this 'id'}}" class="side-link">{{get this 'name'}}</a>
    </li>
    {{/each}}
</ul>