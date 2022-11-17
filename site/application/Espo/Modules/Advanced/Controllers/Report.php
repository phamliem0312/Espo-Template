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
use \Espo\Core\Exceptions\Error;

class Report extends \Espo\Core\Controllers\Record
{
    public function actionRunList($params, $data, $request)
    {
        $id = $request->get('id');
        $where = $request->get('where');

        if ($where === '') {
            $where = null;
        }

        if (empty($id)) {
            throw new BadRequest();
        }

        $maxSize = (int) $request->get('maxSize');

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
            'offset' => (int) $request->get('offset'),
            'maxSize' => $maxSize,
            'groupValue' => $request->get('groupValue'),
            'groupIndex' => $request->get('groupIndex'),
        ];

        if ($request->get('select')) {
            $params['select'] = explode(',', $request->get('select'));
        }

        if (array_key_exists('groupValue2', $request->get())) {
            $params['groupValue2'] = $request->get('groupValue2');
        }

        $result = $this->getRecordService()->run($id, $where, $params, [], $this->getUser());

        if ($result) {
            $resultData = [
                'list' => $result['collection']->toArray(),
                'total' => $result['total']
            ];
            if (isset($result['columns'])) {
                $resultData['columns'] = $result['columns'];
            }
            if (isset($result['columnsData'])) {
                $resultData['columnsData'] = $result['columnsData'];
            }
            return $resultData;
        }
    }

    public function actionRun($params, $data, $request)
    {
        $id = $request->get('id');
        $where = $request->get('where');

        if ($where === '') {
            $where = null;
        }

        if (empty($id)) {
            throw new BadRequest();
        }

        return $this->getRecordService()->run($id, $where, null, [], $this->getUser());
    }

    public function actionPopulateTargetList($params, $data, $request)
    {
        if (is_array($data)) $data = (object) $data;

        if (!$request->isPost()) {
            throw new BadRequest();
        }

        if (empty($data->id) || empty($data->targetListId)) {
            throw new BadRequest();
        }

        $id = $data->id;
        $targetListId = $data->targetListId;

        $this->getRecordService()->populateTargetList($id, $targetListId);

        return true;
    }

    public function actionSyncTargetListWithReports($params, $data, $request)
    {
        if (is_array($data)) $data = (object) $data;

        if (!$request->isPost()) {
            throw new BadRequest();
        }
        if (empty($data->targetListId)) {
            throw new BadRequest();
        }
        $targetListId = $data->targetListId;

        $targetList = $this->getEntityManager()->getEntity('TargetList', $targetListId);
        if (!$targetList->get('syncWithReportsEnabled')) {
            throw new Error();
        }

        return $this->getRecordService()->syncTargetListWithReports($targetList);
    }

    public function getActionExportList($params, $data, $request)
    {
        throw new BadRequest();

        $id = $request->get('id');
        $where = $request->get('where');

        if ($where === '') {
            $where = null;
        }

        if (empty($id)) {
            throw new BadRequest();
        }

        $orderBy = $request->get('orderBy');
        $order =  $request->get('order');

        if (!$orderBy) {
            $orderBy = $request->get('sortBy');
        }
        if (!$order) {
            $order = $request->get('asc', 'true') === 'true' ? 'asc' : 'desc';
        }

        $exportParams = [
            'orderBy' => $orderBy,
            'order' => $order,
            'groupValue' => $request->get('groupValue'),
            'groupIndex' => $request->get('groupIndex'),
        ];

        if (array_key_exists('groupValue2', $request->get())) {
            $exportParams['groupValue2'] = $request->get('groupValue2');
        }

        return [
            'id' => $this->getRecordService()->exportList($request->get('id'), $where, $exportParams, $this->getUser())
        ];
    }

    public function postActionExportList($params, $data, $request)
    {
        if (is_array($data)) $data = (object) $data;

        if (empty($data->id)) {
            throw new BadRequest();
        }
        $id = $data->id;

        $where = null;
        if (property_exists($data, 'where')) {
            $where = json_decode(json_encode($data->where), true);
        }

        $groupValue = null;
        if (property_exists($data, 'groupValue')) {
            $groupValue = $data->groupValue;
        }

        $groupIndex = null;
        if (property_exists($data, 'groupIndex')) {
            $groupIndex = $data->groupIndex;
        }

        $orderBy = null;
        if (property_exists($data, 'sortBy')) {
            $orderBy = $data->sortBy;
        }
        if (property_exists($data, 'orderBy')) {
            $orderBy = $data->orderBy;
        }

        $asc = true;
        if (property_exists($data, 'asc')) {
            $asc = $data->asc;
        }

        if (property_exists($data, 'order')) {
            $order = $data->order;
        } else {
            $order = $asc ? 'asc' : 'desc';
        }

        $params = [
            'orderBy' => $orderBy,
            'order' => $order,
            'groupValue' => $groupValue,
            'groupIndex' => $groupIndex,
        ];

        if (property_exists($data, 'groupValue2')) {
            $params['groupValue2'] = $data->groupValue2;
        }

        if (property_exists($data, 'attributeList')) {
            $params['attributeList'] = $data->attributeList;
        }
        if (property_exists($data, 'fieldList')) {
            $params['fieldList'] = $data->fieldList;
        }
        if (property_exists($data, 'format')) {
            $params['format'] = $data->format;
        }

        if (property_exists($data, 'ids')) {
            $params['ids'] = $data->ids;
        }

        return [
            'id' => $this->getRecordService()->exportList($id, $where, $params, $this->getUser())
        ];
    }

    public function postActionGetEmailAttributes($params, $data, $request)
    {
        if (is_array($data)) $data = (object) $data;

        if (empty($data->id)) {
            throw new BadRequest();
        }
        $id = $data->id;

        $where = null;
        if (!empty($data->where)) {
            $where = $data->where;
            $where = json_decode(json_encode($where), true);
        }

        return $this->getServiceFactory()->create('ReportSending')->getEmailAttributes($id, $where);
    }

    public function postActionExportGridXlsx($params, $data, $request)
    {
        if (empty($data->id)) throw new BadRequest();

        $id = $data->id;

        $where = null;
        if (property_exists($data, 'where')) {
            $where = json_decode(json_encode($data->where), true);
        }

        return $this->getRecordService()->exportGridXlsx($id, $where, $this->getUser());
    }

    public function postActionExportGridCsv($params, $data, $request)
    {
        if (empty($data->id)) throw new BadRequest();

        $id = $data->id;

        $column = null;
        if (!empty($data->column)) {
            $column = $data->column;
        }

        $where = null;
        if (property_exists($data, 'where')) {
            $where = json_decode(json_encode($data->where), true);
        }

        return $this->getRecordService()->exportGridCsv($id, $where, $column, $this->getUser());
    }

    public function postActionPrintPdf($params, $data)
    {
        $id = $data->id ?? null;
        $templateId = $data->templateId ?? null;

        $where = null;
        if (property_exists($data, 'where')) {
            $where = json_decode(json_encode($data->where), true);
        }

        if (!$id || !$templateId) throw new BadRequest();

        $attachmentId = $this->getRecordService()->exportPdf($id, $where, $templateId);

        return [
            'id' => $attachmentId,
        ];
    }
}
