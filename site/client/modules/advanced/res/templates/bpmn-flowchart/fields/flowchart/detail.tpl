{{#if loadFontAwesome}}
<link href="{{basePath}}client/css/font-awesome.min.css" rel="stylesheet">
{{/if}}

<div class="flowchart-group-container">
    <div class="button-container clearfix">
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