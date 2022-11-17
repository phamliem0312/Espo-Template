<div class="panel panel-default panel-conditions hidden sticked" data-name="conditions">
    <div class="panel-heading"><h4 class="panel-title">{{translate 'Conditions' scope='Workflow'}}</h4></div>
    <div class="panel-body conditions-container">
        {{{conditions}}}
    </div>
</div>

<div class="panel panel-default panel-actions hidden sticked" data-name="actions">
    <div class="panel-heading"><h4 class="panel-title">{{translate 'Actions' scope='Workflow'}}</h4></div>
    <div class="panel-body actions-container">
        {{{actions}}}
    </div>
</div>

{{#if workflowLogRecords}}
<div class="panel panel-default" data-name="workflowLogRecords">
    <div class="panel-heading">
        <h4 class="panel-title">
            <span style="cursor: pointer;" class="action" data-action="refresh" data-panel="workflowLogRecords" title="{{translate 'clickToRefresh' category='messages'}}">{{translate 'workflowLogRecords' scope='Workflow' category='links'}}</span>
        </h4>
    </div>
    <div class="panel-body">
        {{{workflowLogRecords}}}
    </div>
</div>
{{/if}}