<style type="text/css">
    .field-toSpecifiedTeams .list-group, .field-toSpecifiedUsers .list-group, .field-toSpecifiedContacts .list-group {
        margin-bottom: 0px;
    }
</style>
<div class="execution-time-container form-group">{{{executionTime}}}</div>
<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'From' scope='Workflow'}}</label>
        <div>
            <select class="form-control" name="from">{{{fromOptions}}}</select>
        </div>
    </div>
    <div class="cell col-sm-6 from-email-container hidden form-group">
        <label class="control-label">{{translate 'Email Address' scope='Workflow'}}</label>
        <div>
            <input class="form-control" name="fromEmail" value="{{fromEmailValue}}">
        </div>
    </div>
</div>
<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'To' scope='Workflow'}}</label>
        <div>
            <select class="form-control" name="to">{{{toOptions}}}</select>
        </div>
    </div>
    <div class="cell col-sm-6 to-email-container hidden form-group">
        <label class="control-label">{{translate 'Email Address' scope='Workflow'}}</label>
        <div>
            <input class="form-control" name="toEmail" value="{{toEmailValue}}">
        </div>
    </div>
    <div class="cell col-sm-6 toSpecifiedTeams-container hidden form-group">
        <label class="control-label">{{translate 'Team' category='scopeNamesPlural'}}</label>
        <div class="field-toSpecifiedTeams">
            {{{toSpecifiedTeams}}}
        </div>
    </div>
    <div class="cell col-sm-6 toSpecifiedUsers-container hidden form-group">
        <label class="control-label">{{translate 'User' category='scopeNamesPlural'}}</label>
        <div class="field-toSpecifiedUsers">
            {{{toSpecifiedUsers}}}
        </div>
    </div>
    <div class="cell col-sm-6 toSpecifiedContacts-container hidden form-group">
        <label class="control-label">{{translate 'Contact' category='scopeNamesPlural'}}</label>
        <div class="field-toSpecifiedContacts">
            {{{toSpecifiedContacts}}}
        </div>
    </div>
</div>
<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'Reply-To' scope='Workflow'}}</label>
        <div>
            <select class="form-control" name="replyTo">{{{replyToOptions}}}</select>
        </div>
    </div>
    <div class="cell col-sm-6 reply-to-email-container hidden form-group">
        <label class="control-label">{{translate 'Email Address' scope='Workflow'}}</label>
        <div>
            <input class="form-control" name="replyToEmail" value="{{replyToEmailValue}}">
        </div>
    </div>
</div>
<div class="row">
    <div class="cell cell-emailTemplate col-sm-6 form-group">
        <label class="control-label">{{translate 'Email Template' scope='Workflow'}}</label>
        <div class="field field-emailTemplate">{{{emailTemplate}}}</div>
    </div>
</div>
<div class="row">
    <div class="cell col-sm-6 doNotStore-container form-group">
        <label class="control-label">{{translate 'doNotStore' scope='Workflow'}}</label>
        <div class="field-doNotStore">
            {{{doNotStore}}}
        </div>
    </div>
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'optOutLink' scope='Workflow' category='fields'}}</label>
        <div class="field" data-name="optOutLink">
            {{{optOutLink}}}
        </div>
    </div>
</div>