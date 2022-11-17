{{#unless isRead}}<strong>{{/unless}}

    {{#if value}}
        <a href="#{{model.name}}/view/{{model.id}}" class="link{{#if isImportant}} text-warning{{/if}}" data-id="{{model.id}}" title="{{value}}">{{value}}</a>
    {{else}}
        <a href="#{{model.name}}/view/{{model.id}}" class="link{{#if isImportant}} text-warning{{/if}}" data-id="{{model.id}}">{{{translate 'None'}}}</a>
    {{/if}}

{{#unless isRead}}</strong>{{/unless}}
