<div class="row report-control-panel margin-bottom">
    <div class="report-runtime-filters-contanier col-md-12">{{{runtimeFilters}}}</div>
    <div class="col-md-4 col-md-offset-8">
        <div class="button-container clearfix">
            <div class="btn-group pull-right">
                <button class="btn btn-default{{#unless hasRuntimeFilters}} hidden{{/unless}}" data-action="run">&nbsp;&nbsp;{{translate 'Run' scope='Report'}}&nbsp;&nbsp;</button>
                <button class="btn btn-default btn-icon btn-icon-wide{{#if hasRuntimeFilters}} hidden{{/if}}" data-action="refresh" title="{{translate 'Refresh'}}"><span class="fas fa-sync-alt"></span></button>
                <button type="button" class="btn btn-default dropdown-toggle" data-toggle="dropdown">
                    <span class="fas fa-ellipsis-h"></span>
                </button>
                <ul class="dropdown-menu">
                    <li><a href="javascript:" data-action="exportReport">{{translate 'Export'}}</a></li>
                    {{#if hasSendEmail}}
                    <li><a href="javascript:" data-action="sendInEmail">{{translate 'Send Email' scope='Report'}}</a></li>
                    {{/if}}
                    {{#if hasPrintPdf}}
                    <li><a href="javascript:" data-action="printPdf">{{translate 'Print to PDF'}}</a></li>
                    {{/if}}
                </ul>
            </div>
        </div>
    </div>
</div>
<div class="report-results-container sections-container"></div>
