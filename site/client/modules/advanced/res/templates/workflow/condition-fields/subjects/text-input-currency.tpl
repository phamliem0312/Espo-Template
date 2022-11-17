{{#if readOnly}}
    <code>{{value}} {{currency}}</code>
{{else}}
    <div class="input-group input-group-sm">
        <input type="text" class="form-control input-sm" data-name="subject" value="{{value}}"> <span class="small input-group-addon">{{currency}}</span>
    </div>
{{/if}}