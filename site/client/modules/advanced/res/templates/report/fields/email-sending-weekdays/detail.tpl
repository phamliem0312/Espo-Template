{{#each days}}
    <input type="checkbox" {{#ifPropEquals ../selectedWeekdays @index true}} checked {{/ifPropEquals}} class="main-element" disabled> {{.}} </input> &nbsp;
{{/each}}
