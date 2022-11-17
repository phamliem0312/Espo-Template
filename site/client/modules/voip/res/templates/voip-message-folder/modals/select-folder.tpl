<ul class="list-group">
    <li data-id="inbox" class="list-group-item">
        <a href="javascript:" data-action="selectFolder" data-id="inbox" class="side-link">{{translate 'inbox' category='presetFilters' scope='VoipMessage'}}</a>
    </li>
    {{#each collection.models}}
    <li data-id="{{get this 'id'}}" class="list-group-item">
        <a href="javascript:" data-action="selectFolder" data-id="{{get this 'id'}}" class="side-link">{{get this 'name'}}</a>
    </li>
    {{/each}}
</ul>