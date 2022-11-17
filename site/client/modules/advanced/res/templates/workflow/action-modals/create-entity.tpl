<div class="row">
    <div class="cell col-sm-6 form-group">
        <label class="control-label">{{translate 'Entity' scope='Workflow'}}</label>
        <div>
            <select class="form-control" name="link">{{{linkOptions}}}</select>
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

<div class="cell form-group hidden" data-name="linkList">
    <label class="control-label">{{translate 'linkList' category='fields' scope='Workflow'}}</label>
    <div class="field" data-name="linkList"></div>
</div>