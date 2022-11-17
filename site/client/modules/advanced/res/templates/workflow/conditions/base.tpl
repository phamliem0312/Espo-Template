{{#if readOnly}}
    <span class="comparison">
        {{translate comparisonValue category='labels' scope='Workflow'}}
    </span>

    <span class="subject-type">
        {{{subjectType}}}
    </span>

    <span class="subject">
        {{{subject}}}
    </span>
{{else}}
    <div class="row">
        <div class="col-sm-3 comparison">
            <select class="form-control input-sm" data-name="comparison">
                {{options comparisonList comparisonValue scope='Workflow' category="labels"}}
            </select>
        </div>

        <div class="col-sm-3 subject-type">
            {{{subjectType}}}
        </div>

        <div class="col-sm-5 subject">
            {{{subject}}}
        </div>
    </div>
{{/if}}

