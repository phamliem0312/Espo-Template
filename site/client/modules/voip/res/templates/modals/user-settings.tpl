{{#each userFieldsDefs}}
    <div class="cell cell-{{@key}} form-group" data-name="{{@key}}">
        {{#unless this.noLabel}}

            {{#if this.labelCategory}}
                <label class="control-label field-label-{{@key}}" data-name="{{@key}}">{{translate @key scope='User' category=this.labelCategory}}</label>
            {{else}}
                <label class="control-label field-label-{{@key}}" data-name="{{@key}}">{{translate @key scope='User' category='fields'}}</label>
            {{/if}}

        {{/unless}}

        <div class="field field-{{@key}}" data-name="{{@key}}"> {{{var @key ../this}}} </div>
    </div>
{{/each}}
