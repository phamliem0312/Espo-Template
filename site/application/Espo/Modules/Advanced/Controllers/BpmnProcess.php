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

namespace Espo\Modules\Advanced\Controllers;

use \Espo\Core\Exceptions\BadRequest;
use \Espo\Core\Exceptions\Forbidden;

class BpmnProcess extends \Espo\Core\Controllers\Record
{
    public function postActionStop($params, $data)
    {
        if (is_array($data)) $data = (object) $data;

        if (empty($data->id)) {
            throw new BadRequest();
        }

        if (!$this->getAcl()->checKScope('BpmnProcess', 'edit')) {
            throw new Forbidden();
        }

        $this->getRecordService()->stopProcess($data->id);

        return true;
    }

    public function postActionRejectFlowNode($params, $data)
    {
        $id = $data->id ?? null;
        if (!$id) throw new BadRequest();

        if (!$this->getAcl()->checKScope('BpmnProcess', 'edit')) throw new Forbidden();

        $this->getRecordService()->rejectFlowNode($id);

        return true;
    }

    public function postActionStartFlowFromElement($params, $data)
    {
        $processId = $data->processId ?? null;
        $elementId = $data->elementId ?? null;
        if (!$processId) throw new BadRequest();
        if (!$elementId) throw new elementId();

        if (!$this->getAcl()->checKScope('BpmnProcess', 'edit')) throw new Forbidden();

        $this->getRecordService()->startFlowFromElement($processId, $elementId);

        return true;
    }
}
