<link href="{{basePath}}client/modules/advanced/css/bpmn.css" rel="stylesheet">

<div class="flowchart-group-container">
    <div class="button-container">
        <div class="btn-group">
            <button type="button" class="btn btn-default action" data-action="resetState" title="{{translate 'Hand tool' scope='BpmnFlowchart'}}"><span class="bpmn-icon-hand"></span></button>
        </div>
        <div class="btn-group">
            <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle add-event-element" data-toggle="dropdown" title="{{translate 'Create Event tool' scope='BpmnFlowchart'}}">
                    <span class="bpmn-icon-event"></span>
                    {{translate 'Events' scope='BpmnFlowchart'}}
                    <span class="caret"></span></button>
                <ul class="dropdown-menu">
                    {{#each elementEventDataList}}
                    {{#ifEqual name '_divider'}}
                    <li class="divider"></li>
                    {{else}}
                    <li>
                        <a class="action" href="javascript:" data-name="{{name}}" data-action="setStateCreateFigure">
                            <span class="fas fa-check pull-right{{#ifNotEqual ../currentElement name}} hidden{{/ifNotEqual}}"></span>
                            <div style="padding-right: 20px; color: {{color}}">{{translate name category='elements' scope='BpmnFlowchart'}}</div>
                        </a>
                    </li>
                    {{/ifEqual}}
                    {{/each}}
                </ul>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle add-gateway-element" data-toggle="dropdown" title="{{translate 'Create Gateway tool' scope='BpmnFlowchart'}}"><span class="bpmn-icon-gateway"></span>
                    {{translate 'Gateways' scope='BpmnFlowchart'}}
                    <span class="caret"></span></button>
                <ul class="dropdown-menu">
                    {{#each elementGatewayDataList}}
                    <li>
                        <a class="action" href="javascript:" data-name="{{name}}" data-action="setStateCreateFigure">
                            <span class="fas fa-check pull-right{{#ifNotEqual name ../currentElement}} hidden{{/ifNotEqual}}"></span>
                            <div style="padding-right: 20px;">{{translate name category='elements' scope='BpmnFlowchart'}}</div>
                        </a>
                    </li>
                    {{/each}}
                </ul>
            </div>
            <div class="btn-group">
                <button type="button" class="btn btn-default dropdown-toggle add-task-element" data-toggle="dropdown" title="{{translate 'Create Activity tool' scope='BpmnFlowchart'}}"><span class="bpmn-icon-task"></span>
                    {{translate 'Activities' scope='BpmnFlowchart'}}
                    <span class="caret"></span></button>
                <ul class="dropdown-menu">
                    {{#each elementTaskDataList}}
                    {{#ifEqual name '_divider'}}
                    <li class="divider"></li>
                    {{else}}
                    <li>
                        <a class="action" href="javascript:" data-name="{{name}}" data-action="setStateCreateFigure">
                            <span class="fas fa-check pull-right{{#ifNotEqual ../currentElement name}} hidden{{/ifNotEqual}}"></span>
                            <div style="padding-right: 20px;">{{translate name category='elements' scope='BpmnFlowchart'}}</div>
                        </a>
                    </li>
                    {{/ifEqual}}
                    {{/each}}
                </ul>
            </div>
        </div>

        <div class="btn-group">
            <button type="button" class="btn btn-default action" data-action="setStateCreateFlow" title="{{translate 'Connect tool' scope='BpmnFlowchart'}}"><i class="fa fa-long-arrow-alt-right fa-long-arrow-right"></i></button>
        </div>

        <button type="button" class="btn btn-default action" data-action="setStateRemove" title="{{translate 'Erase tool' scope='BpmnFlowchart'}}"><i class="fa fa-eraser"></i></button>

        <button type="button" class="btn btn-text action hidden" data-action="apply" title="{{translate 'Apply'}}"><i class="fas fa-save"></i></button>

        <div class="btn-group pull-right">
            <button type="button" class="btn btn-text action" data-action="switchFullScreenMode" title="{{translate 'Full Screen' scope='BpmnFlowchart'}}"><i class="fas fa-arrows-alt"></i></button>
        </div>

        <div class="btn-group pull-right">
            <button type="button" class="btn btn-text action" data-action="zoomOut" title="{{translate 'Zoom Out' scope='BpmnFlowchart'}}"><span class="fas fa-minus"></span></button>
            <button type="button" class="btn btn-text action" data-action="zoomIn" title="{{translate 'Zoom In' scope='BpmnFlowchart'}}"><span class="fas fa-plus"></span></button>
        </div>
    </div>

    <div class="flowchart-container" style="width: 100%; height: {{heightString}};"></div>
</div>