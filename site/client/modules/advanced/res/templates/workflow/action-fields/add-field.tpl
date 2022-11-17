        <div class="btn-group">
            <button class="btn btn-default" type="button" data-toggle="dropdown">{{translate 'Add Field' scope='Workflow'}} <span class="caret"></span></button>
            <ul class="dropdown-menu">
            {{#each fieldList}}
                <li><a href="javascript:" data-action="addField" data-field="{{this}}">{{translate this scope=../scope category="fields"}}</a></li>
            {{/each}}
            </ul>
        </div>
