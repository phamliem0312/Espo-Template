{{#if description}}<p>{{{description}}}</p>{{/if}}
{{#if name}}<p>{{name}}</p>{{/if}}
{{#if header}}<p>{{header}}</p>{{/if}}

<table cellspacing="0" cellpadding="5" style="border-collapse: collapse; border: 1px solid #ddd;">
    <!-- {{#each rowList}} -->
        <tr style="border: 1px solid #ddd;">
    <!-- {{#each .}} -->
            <td style="border: 1px solid #ddd;" align="{{attrs.align}}">
                <!-- {{#if isBold}}--> <b> <!-- {{/if}}-->
                {{value}}
                <!-- {{#if isBold}}--> </b> <!-- {{/if}}-->
            </td>
    <!-- {{/each}} -->
        </tr>
    <!-- {{/each}} -->
</table>
