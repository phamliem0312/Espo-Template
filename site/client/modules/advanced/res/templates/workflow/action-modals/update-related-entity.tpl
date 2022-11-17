<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'Link' scope='Workflow'}}</label>
        <div>
            <select class="form-control" name="link">{{{linkOptions}}}</select>
        </div>
    </div>
</div>
<div class="row">
    <div class="cell col-sm-6 form-group hidden" data-name="parentEntityType">
        <label class="control-label">{{translate 'Entity Type' scope='Workflow'}}</label>
        <div class="field" data-name="parentEntityType">
            {{{parentEntityType}}}
        </div>
    </div>
</div>

<div class="row">
    <div class="cell col-sm-6 form-group add-field-container">
        {{{addField}}}
    </div>
</div>

<div class="field-definitions">
</div>

<div class="cell form-group hidden" data-name="formula">
    <label class="control-label">{{translate 'Formula' scope='Workflow'}}</label>
    <div class="field" data-name="formula"></div>
</div>
