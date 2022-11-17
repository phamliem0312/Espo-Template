
<div class="panel panel-default">
    <div class="panel-heading">
        <h4 class="panel-title">{{translate 'TeamUsers' scope='VoipRouter'}}</h4>
    </div>
    <div class="panel-body">

        {{#if noUserData}}

            {{translate 'No Data'}}

        {{else}}

            <div class="no-margin">

                <table class="table no-margin">

                    <thead>
                        <tr>
                            <th>{{translate 'User' scope='VoipRouter'}}</th>

                            {{#each actionList}}
                                <th width="12%">{{translate this scope='VoipRouter' category='actions'}}</th>
                            {{/each}}

                            <th width="30">&nbsp;</th>
                        </tr>
                    </thead>

                    <tbody class="item-list-internal-container ui-sortable">

                    {{#each tableDataList}}
                        <tr class="item-container-cid{{userId}}" style="background-color:#fff" data-id="{{userId}}">
                            <td>
                                {{#if ../editMode}}
                                    {{userName}}
                                {{else}}
                                    <a href="#User/view/{{userId}}">{{userName}}</a>
                                {{/if}}
                            </td>

                            {{#each list}}
                            <td>
                                <input type="checkbox" name="{{name}}" class="main-element" {{#if level}} checked{{/if}} {{#ifNotEqual ../../editMode true}} disabled {{/ifNotEqual}} >
                                {{#if ../../editMode}}{{#if notice}}
                                    <a href="javascript:" class="text-danger notice-sign">
                                        <span class="glyphicon glyphicon-exclamation-sign "></span>
                                        <input type="hidden" value="{{translate 'Current Number' scope='VoipRouter'}} <b>{{notice}}</b>" class="notice-text" />
                                    </a>
                                {{/if}}{{/if}}
                                </input>
                            </td>
                            {{/each}}

                            <td>
                                <div class="{{#if ../editMode}} detail-field-container{{/if}}">
                                    {{#if ../editMode}}
                                    <span class="glyphicon glyphicon-magnet drag-icon text-muted" style="cursor: pointer;"></span>
                                    {{/if}}
                                </div>
                            </td>
                        </tr>
                    {{/each}}
                    </tbody>
                </table>

            </div>

        {{/if}}

    </div>
</div>
