<link href="client/modules/voip/css/voip-notification.css" rel="stylesheet">
<a href="javascript:" class="pull-right close" data-action="close" aria-hidden="true">×</a>
<div class="message-item">

    <div class="cell form-group" data-name="from">
        <div class="field" data-name="from">
            <h4>{{#if notificationData.parentName}}{{notificationData.parentName}}, {{notificationData.from}}{{else}}{{notificationData.from}}{{/if}}</h4>
        </div>
    </div>

    <div class="cell form-group" data-name="to">
        <div class="field" data-name="to">
            <label class="control-label" data-name="to">{{translate 'to' category='fields' scope='VoipMessage'}}</label>:
            {{notificationData.to}}
        </div>
    </div>

    <div class="cell form-group" data-name="body">
        <span class="complext-text">{{notificationData.body}}</span>
    </div>

    <div class="btn-group btn-group-sm" role="group">
        <button class="btn btn-default" data-action="open-message">{{translate 'Go to message' category='labels' scope='VoipMessage'}}</button>
    </div>

</div>