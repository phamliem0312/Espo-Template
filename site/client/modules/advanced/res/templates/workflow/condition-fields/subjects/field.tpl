{{#if readOnly}}
    {{{listHtml}}}
{{else}}
    <select data-name="subject" class="form-control input-sm">
        {{{listHtml}}}
    </select>
{{/if}}