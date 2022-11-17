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

use \Espo\Core\Exceptions\Forbidden;

class ReportPanel extends \Espo\Core\Controllers\Record
{
    public function postActionRebuild()
    {
        $this->getRecordService()->rebuild();
        return true;
    }

    public function actionRunList($params, $data, $request)
    {
        $id = $request->get('id');

        $parentType = $request->get('parentType');
        $parentId = $request->get('parentId');

        if (empty($id)) {
            throw new BadRequest();
        }

        $maxSize = $request->get('maxSize');
        if ($maxSize > 200) {
            throw new BadRequest('List max size exceeded.');
        }

        $orderBy = $request->get('orderBy');
        $order =  $request->get('order');

        if (!$orderBy) {
            $orderBy = $request->get('sortBy');
        }
        if (!$order) {
            $order = $request->get('asc', 'true') === 'true' ? 'asc' : 'desc';
        }

        $params = [
            'order' => $order,
            'orderBy' => $orderBy,
            'sortBy' => $orderBy,
            'asc' => $order === 'asc',
            'offset' => $request->get('offset'),
            'maxSize' => $maxSize,
            'groupValue' => $request->get('groupValue'),
            'groupIndex' => $request->get('groupIndex'),
            'subReportId' => $request->get('subReportId'),
        ];
        if (array_key_exists('groupValue2', $request->get())) {
            $params['groupValue2'] = $request->get('groupValue2');
        }

        $result = $this->getRecordService()->runList($id, $parentType, $parentId, $params);

        if ($result) {
            return [
                'list' => $result['collection']->getValueMapList(),
                'total' => isset($result['total']) ? $result['total'] : null,
                'columns' => isset($result['columnsData']) ? $result['columns'] : null,
                'columnsData' => isset($result['columns']) ? $result['columnsData'] : null,
            ];
        }
    }

    public function actionRunGrid($params, $data, $request)
    {
        $id = $request->get('id');
        $parentType = $request->get('parentType');
        $parentId = $request->get('parentId');

        if (empty($id)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->runGrid($id, $parentType, $parentId);
    }
}
