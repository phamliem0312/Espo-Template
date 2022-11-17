{{#each days}}
    <label>
    <input type="checkbox" data-name="{{../name}}" value="{{@index}}"
        {{#ifPropEquals ../selectedWeekdays @index true}} checked {{/ifPropEquals}}
        class="main-element"> {{.}}
   </input> &nbsp;
   </label>
{{/each}}
