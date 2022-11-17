<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM Advanced Pack
 * Agreement ("License") which can be viewed at
 * https://www.espocrm.com/advanced-pack-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 *
 * Copyright (C) 2015-2021 Letrium Ltd.
 *
 * License ID: 4bc1026aa50a71b8840665043d28bcbc
 ***********************************************************************************/

namespace Espo\Modules\Advanced\Core\Workflow\Formula\Functions\WorkflowGroup;

use stdClass;

class LastHttpResponseCodeType extends \Espo\Core\Formula\Functions\Base
{
    public function process(stdClass $item)
    {
        $vars = $this->getVariables();

        if ($vars === null) {
            return null;
        }

        return $vars->_lastHttpResponseCode ?? null;
    }
}
