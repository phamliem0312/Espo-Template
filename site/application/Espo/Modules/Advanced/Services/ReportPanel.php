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

namespace Espo\Modules\Advanced\Services;

use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\NotFound;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\BadRequest;

use Espo\ORM\Entity;
use Espo\Core\Utils\Language;

class ReportPanel extends \Espo\Services\Record
{
    protected function init()
    {
        parent::init();
        $this->addDependency('fileManager');
        $this->addDependency('dataManager');
    }

    protected $forceSelectAllAttributes = true;

    protected function afterCreateEntity(Entity $entity, $data)
    {
        $this->rebuild($entity->get('entityType'));
    }

    protected function afterUpdateEntity(Entity $entity, $data)
    {
        $this->rebuild($entity->get('entityType'));
    }

    protected function afterDeleteEntity(Entity $entity)
    {
        $this->rebuild($entity->get('entityType'));
    }

    public function loadAdditionalFields(Entity $entity)
    {
        parent::loadAdditionalFields($entity);

        $reportService = $this->getServiceFactory()->create('Report');

        if ($entity->get('reportType') === 'Grid' && $entity->get('reportId')) {
            $report = $this->getEntityManager()->getEntity('Report', $entity->get('reportId'));

            if ($report) {
                $columnList = $report->get('columns');

                $numericColumnList = [];

                foreach ($columnList as $column) {
                    if ($reportService->isColumnNumeric($column, $report->get('entityType'))) {
                        $numericColumnList[] = $column;
                    }
                }

                if (
                    is_array($report->get('groupBy'))
                    &&
                    (count($report->get('groupBy')) === 1 || count($report->get('groupBy')) === 0)
                    &&
                    count($numericColumnList) > 1
                ) {
                    array_unshift($numericColumnList, '');
                }

                $entity->set('columnList', $numericColumnList);
            }
        }

        $displayType = $entity->get('displayType');
        $reportType = $entity->get('reportType');
        $displayTotal = $entity->get('displayTotal');
        $displayOnlyTotal = $entity->get('displayOnlyTotal');

        if (!$displayType) {
            if ($reportType === 'Grid' || $reportType === 'JointGrid') {
                if ($displayOnlyTotal) {
                    $displayType = 'Total';
                }
                else if ($displayTotal) {
                    $displayType = 'Chart-Total';
                }
                else {
                    $displayType = 'Chart';
                }
            }
            else if ($reportType === 'List') {
                if ($displayOnlyTotal) {
                    $displayType = 'Total';
                }
                else {
                    $displayType = 'List';
                }
            }

            $entity->set('displayType', $displayType);
        }
    }

    public function rebuild($specificEntityType = null)
    {
        $scopeData = $this->getMetadata()->get(['scopes'], []);
        $entityTypeList = [];

        if (isset($this->injectableFactory) && method_exists($this->injectableFactory, 'createWith')) {
            $language = $this->injectableFactory->createWith(Language::class, [
                'language' => 'en_US',
            ]);
        } else {
            $language = new Language('en_US', $this->getInjection('fileManager'), $this->getMetadata());
        }

        $isAnythingChanged = false;

        if ($specificEntityType) {
            $entityTypeList[] = $specificEntityType;
        }
        else {
            foreach ($scopeData as $scope => $item) {
                if (empty($item['entity'])) continue;
                if (empty($item['object'])) continue;
                if (!empty($item['disabled'])) continue;

                $entityTypeList[] = $scope;
            }
        }

        $typeList = ['bottom', 'side'];

        foreach ($entityTypeList as $entityType) {
            $clientDefs = $this->getMetadata()->getCustom('clientDefs', $entityType, (object) []);
            $panelListData = [];

            $dynamicLogicToRemoveHash = [];
            $dynamicLogicHash = [];

            foreach ($typeList as $type) {
                $isChanged = false;

                $toAppend = true;

                $panelListData[$type] = [];
                $key = $type . 'Panels';

                if (isset($clientDefs->$key) && isset($clientDefs->$key->detail)) {
                    $toAppend = false;

                    $panelListData[$type] = $clientDefs->$key->detail;
                }

                foreach ($panelListData[$type] as $i => $item) {
                    if (is_string($item)) {
                        if ($item === '__APPEND__') {
                            unset($panelListData[$type][$i]);

                            $toAppend = true;
                        }

                        continue;
                    }

                    if (!empty($item->isReportPanel)) {
                        if (isset($item->name)) {
                            $dynamicLogicToRemoveHash[$item->name] = true;
                        }

                        unset($panelListData[$type][$i]);

                        $isChanged = true;
                    }
                }
                $panelListData[$type] = array_values($panelListData[$type]);

                $reportPanelList = $this->getEntityManager()
                    ->getRepository('ReportPanel')
                    ->where([
                        'isActive' => true,
                        'entityType' => $entityType,
                        'type' => $type
                    ])
                    ->order('name')
                    ->find();

                foreach ($reportPanelList as $reportPanel) {
                    if (!$reportPanel->get('reportId')) {
                        continue;
                    }

                    $report = $this->getEntityManager()->getEntity('Report', $reportPanel->get('reportId'));

                    if (!$report) {
                        continue;
                    }

                    $isChanged = true;

                    $name = 'reportPanel' . $reportPanel->id;

                    $o = (object) [
                        'isReportPanel' => true,
                        'name' => $name,
                        'label' => $reportPanel->get('name'),
                        'view' => 'advanced:views/report-panel/record/panels/report-panel-' . $type,
                        'reportPanelId' => $reportPanel->id,
                        'reportType' => $report->get('type'),
                        'reportEntityType' => $report->get('entityType'),
                        'displayType' => $reportPanel->get('displayType'),
                        'displayTotal'  => $reportPanel->get('displayTotal'),
                        'displayOnlyTotal' => $reportPanel->get('displayOnlyTotal'),
                        'useSiMultiplier' => $reportPanel->get('useSiMultiplier'),
                        'accessDataList' => [
                            (object) [
                                'scope' => $report->get('entityType')
                            ]
                        ],
                    ];
                    if ($type === 'bottom') {
                        $o->order = $reportPanel->get('order');

                        if ($o->order <= 2) {
                            $o->sticked = true;
                        }
                    }

                    if ($reportPanel->get('dynamicLogicVisible')) {
                        $dynamicLogicHash[$name] = (object) [
                            'visible' => $reportPanel->get('dynamicLogicVisible')
                        ];

                        unset($dynamicLogicToRemoveHash[$name]);
                    }

                    if ($report->get('type') === 'Grid') {
                        $o->column = $reportPanel->get('column');
                    }

                    if (count($reportPanel->getLinkMultipleIdList('teams'))) {
                        $o->accessDataList[] = (object) [
                            'teamIdList' => $reportPanel->getLinkMultipleIdList('teams')
                        ];
                    }

                    $panelListData[$type][] = $o;
                }

                if ($isChanged) {
                    $isAnythingChanged = true;

                    $clientDefs = $this->getMetadata()->getCustom('clientDefs', $entityType, (object) []);

                    foreach ($dynamicLogicToRemoveHash as $name => $h) {
                        if (isset($clientDefs->dynamicLogic) && isset($clientDefs->dynamicLogic->panels)) {
                            unset($clientDefs->dynamicLogic->panels->$name);
                        }
                    }

                    if (!empty($dynamicLogicHash)) {
                        if (!isset($clientDefs->dynamicLogic)) {
                            $clientDefs->dynamicLogic = (object) [];
                        }

                        if (!isset($clientDefs->dynamicLogic->panels)) {
                            $clientDefs->dynamicLogic->panels = (object) [];
                        }

                        foreach ($dynamicLogicHash as $name => $item) {
                            $clientDefs->dynamicLogic->panels->$name = $item;
                        }
                    }

                    if (!empty($panelListData[$type])) {
                        if ($toAppend) {
                            array_unshift($panelListData[$type], '__APPEND__');
                        }

                        if (!isset($clientDefs->$key)) {
                            $clientDefs->$key = (object) [];
                        }

                        $clientDefs->$key->detail = $panelListData[$type];
                    } else {
                        if (isset($clientDefs->$key)) {
                            unset($clientDefs->$key->detail);
                        }
                    }

                    $this->getMetadata()->saveCustom('clientDefs', $entityType, $clientDefs);
                }
            }
        }
        if ($isAnythingChanged) {
            $this->getInjection('dataManager')->clearCache();
        }
    }

    public function runList($id, $parentType, $parentId, $params)
    {
        return $this->run('List', $id, $parentType, $parentId, $params);
    }

    public function runGrid($id, $parentType, $parentId)
    {
        return $this->run('Grid', $id, $parentType, $parentId);
    }

    public function run($type, $id, $parentType, $parentId, $params = null)
    {
        $reportPanel = $this->getEntityManager()->getEntity('ReportPanel', $id);

        if (!$reportPanel) {
            throw new NotFound('Report Panel not found.');
        }

        if (!$this->getAcl()->checkScope($reportPanel->get('reportEntityType'))) {
            throw new Forbidden();
        }

        if (!$parentId || !$parentType) {
            throw new BadRequest();
        }

        $parent = $this->getEntityManager()->getEntity($parentType, $parentId);

        if (!$parent) {
            throw new NotFound();
        }

        if (!$this->getAcl()->checkEntity($parent, 'read')) {
            throw new Forbidden();
        }

        if (!$reportPanel->get('reportId')) {
            throw new Error('Bad Report Panel.');
        }

        if ($reportPanel->get('entityType') !== $parentType) {
            throw new Forbidden();
        }

        $teamIdList = $reportPanel->getLinkMultipleIdList('teams');

        if (count($teamIdList) && !$this->getUser()->isAdmin()) {
            $isInTeam = false;

            $userTeamIdList = $this->getUser()->getLinkMultipleIdList('teams');

            foreach ($userTeamIdList as $teamId) {
                if (in_array($teamId, $teamIdList)) {
                    $isInTeam = true;
                    break;
                }
            }
            if (!$isInTeam) {
                throw new Forbidden("Access denied to Report Panel.");
            }
        }

        $report = $this->getEntityManager()->getEntity('Report', $reportPanel->get('reportId'));

        if (!$report) {
            throw new NotFound('Report not found.');
        }

        if ($type === 'List' && $report->get('type') === 'JointGrid') {
            if (empty($params['subReportId'])) {
                throw new BadRequest();
            }

            $joinedReportDataList = $report->get('joinedReportDataList');

            if (empty($joinedReportDataList)) {
                throw new Error();
            }

            $subReport = null;

            foreach ($joinedReportDataList as $subReportItem) {
                if ($params['subReportId'] === $subReportItem->id) {
                    $subReport = $this->getEntityManager()->getEntity('Report', $subReportItem->id);

                    break;
                }
            }

            if (!$subReport) {
                throw new Error();
            }

            $report = $subReport;
        }

        $where = null;
        if ($report->get('type') === 'JointGrid') {
            $subReportIdWhereMap = (object) [];

            foreach ($report->get('joinedReportDataList') as $subReportItem) {
                $subReport = $this->getEntityManager()->getEntity('Report', $subReportItem->id);

                if (!$subReport) {
                    throw new Error('Sub report not found.');
                }

                $subReportIdWhereMap->{$subReport->id} = $this->getWhere($parent, $subReport);
            }

            $params['subReportIdWhereMap'] = $subReportIdWhereMap;

        } else {
            $where = $this->getWhere($parent, $report);
        }

        if ($type === 'Grid') {
            return $this->getServiceFactory()->create('Report')->run(
                $report->id, $where, $params, ['skipRuntimeFiltersCheck' => true], $this->getUser()
            );
        }
        if ($type === 'List') {
            return $this->getServiceFactory()->create('Report')->run(
                $report->id, $where, $params, ['skipRuntimeFiltersCheck' => true], $this->getUser()
            );
        }
    }

    protected function getWhere(Entity $parent, Entity $report)
    {
        $where = null;

        $filterList = $report->get('runtimeFilters');

        if (!$filterList) {
            $filterList = [];
        }

        foreach ($filterList as $item) {
            $link = null;

            $field = $item;

            $entityType = $report->get('entityType');

            if (strpos($item, '.')) {
                list ($link, $field) = explode('.', $item);
                $entityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);

                if (!$entityType) {
                    continue;
                }
            }
            $linkType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $field, 'type']);

            if ($linkType === 'belongsTo' || $linkType === 'hasMany') {
                $foreignEntityType = $this->getMetadata()->get(
                    ['entityDefs', $entityType, 'links', $field, 'entity']
                );

                if ($foreignEntityType !== $parent->getEntityType()) {
                    continue;
                }

                if ($linkType === 'belongsTo') {
                    $where = [
                        [
                            'type' => 'equals',
                            'attribute' => $item . 'Id',
                            'value' => $parent->id
                        ]
                    ];
                } else if ($linkType === 'hasMany') {
                    $where = [
                        [
                            'type' => 'linkedWith',
                            'attribute' => $item,
                            'value' => [$parent->id]
                        ]
                    ];
                }
            } else if ($linkType === 'belongsToParent') {
                $entityTypeList = $this->getMetadata()->get(
                    ['entityDefs', $entityType, 'fields', $field, 'entityList'], []
                );

                if (!in_array($parent->getEntityType(), $entityTypeList)) {
                    continue;
                }

                $where = [
                    [
                        'type' => 'and',
                        'value' => [
                            [
                                'type' => 'equals',
                                'attribute' => $item . 'Id',
                                'value' => $parent->id
                            ],
                            [
                                'type' => 'equals',
                                'attribute' => $item . 'Type',
                                'value' => $parent->getEntityType()
                            ]
                        ]
                    ]
                ];
            }
        }

        if (!$where) {
            $entityType = $report->get('entityType');
            $linkList = array_keys($this->getMetadata()->get(['entityDefs', $entityType, 'links'], []));

            $foundBelongsToList = [];
            $foundHasManyList = [];
            $foundBelongsToParentList = [];
            $foundBelongsToParentEmptyList = [];

            foreach ($linkList as $link) {
                $linkType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'type']);

                if ($linkType === 'belongsTo' || $linkType === 'hasMany') {
                    $foreignEntityType = $this->getMetadata()->get(['entityDefs', $entityType, 'links', $link, 'entity']);

                    if ($foreignEntityType !== $parent->getEntityType()) {
                        continue;
                    }

                    if ($linkType === 'belongsTo') {
                        $foundBelongsToList[] = $link;
                    } else {
                        $foundHasManyList[] = $link;
                    }

                    continue;
                }
                if ($linkType === 'belongsToParent') {
                    $entityTypeList = $this->getMetadata()->get(
                        ['entityDefs', $entityType, 'fields', $link, 'entityList'], []
                    );

                    if (!in_array($parent->getEntityType(), $entityTypeList)) {
                        if (empty($entityTypeList)) {
                            $foundBelongsToParentEmptyList[] = $link;
                        }

                        continue;
                    }

                    $foundBelongsToParentList[] = $link;
                }
            }

            if (count($foundBelongsToList)) {
                $link = $foundBelongsToList[0];

                $where = [
                    [
                        'type' => 'equals',
                        'attribute' => $link . 'Id',
                        'value' => $parent->id
                    ]
                ];
            } else if (count($foundBelongsToParentList)) {
                $link = $foundBelongsToParentList[0];

                $where = [
                    [
                        'type' => 'and',
                        'value' => [
                            [
                                'type' => 'equals',
                                'attribute' => $link . 'Id',
                                'value' => $parent->id
                            ],
                            [
                                'type' => 'equals',
                                'attribute' => $link . 'Type',
                                'value' => $parent->getEntityType()
                            ]
                        ]
                    ]
                ];
            } else if (count($foundHasManyList)) {
                $link = $foundHasManyList[0];

                $where = [
                    [
                        'type' => 'linkedWith',
                        'attribute' => $link,
                        'value' => [$parent->id]
                    ]
                ];
            } else if (count($foundBelongsToParentEmptyList)) {
                $link = $foundBelongsToParentEmptyList[0];

                $where = [
                    [
                        'type' => 'and',
                        'value' => [
                            [
                                'type' => 'equals',
                                'attribute' => $link . 'Id',
                                'value' => $parent->id
                            ],
                            [
                                'type' => 'equals',
                                'attribute' => $link . 'Type',
                                'value' => $parent->getEntityType()
                            ]
                        ]
                    ]
                ];
            }
        }

        if (!$where) {
            $where = [
                [
                    'type' => 'equals',
                    'attribute' => 'id',
                    'value' => null
                ]
            ];
        }

        return $where;
    }
}
