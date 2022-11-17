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

use Espo\{
    ORM\Entity,
    Core\Utils\Language,
    Modules\Advanced\Core\ReportFilter as ReportFilterUtil,
};

class ReportFilter extends \Espo\Services\Record
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
        } else {
            foreach ($scopeData as $scope => $item) {
                if (empty($item['entity'])) continue;
                if (empty($item['object'])) continue;
                if (!empty($item['disabled'])) continue;
                $entityTypeList[] = $scope;
            }
        }

        foreach ($entityTypeList as $entityType) {
            $removedHash = [];
            $isChanged = false;

            $clientDefs = $this->getMetadata()->getCustom('clientDefs', $entityType, (object) []);
            $filterList = [];
            $toAppend = true;

            if (isset($clientDefs->filterList)) {
                $toAppend = false;
                $filterList = $clientDefs->filterList;
            }

            foreach ($filterList as $i => $item) {
                if (is_string($item)) {
                    if ($item === '__APPEND__') {
                        unset($filterList[$i]);
                        $toAppend = true;
                    }

                    continue;
                }

                if (!empty($item->isReportFilter)) {
                    unset($filterList[$i]);
                    $isChanged = true;
                }
            }

            $filterList = array_values($filterList);

            $entityDefs = $this->getMetadata()->getCustom('entityDefs', $entityType, (object) []);
            $filtersData = (object) [];

            if (isset($entityDefs->collection) && isset($entityDefs->collection->filters)) {
                $filtersData = $entityDefs->collection->filters;
                if (is_array($filtersData)) {
                    $filtersData = (object) [];
                }
            }

            foreach ($filtersData as $filter => $item) {
                if (!empty($item->isReportFilter)) {
                    unset($filtersData->$filter);
                    $removedHash[$filter] = true;
                    $isChanged = true;
                }
            }

            $reportFilterList = $this->getEntityManager()
                ->getRepository('ReportFilter')
                ->where([
                    'isActive' => true,
                    'entityType' => $entityType
                ])
                ->order('order')
                ->find();

            foreach ($reportFilterList as $reportFilter) {
                $isChanged = true;
                $name = 'reportFilter' . $reportFilter->id;

                $o = (object) [
                    'isReportFilter' => true,
                    'name' => $name
                ];

                if (count($reportFilter->getLinkMultipleIdList('teams'))) {
                    $o->accessDataList = [
                        (object) [
                            'teamIdList' => $reportFilter->getLinkMultipleIdList('teams'),
                        ]
                    ];
                }
                $filterList[] = $o;

                unset($removedHash[$name]);

                $filtersData->$name = (object) [
                    'isReportFilter' => true,
                    'className' => ReportFilterUtil::class,
                    'id' => $reportFilter->id,
                ];

                $language->set($entityType, 'presetFilters', $name, $reportFilter->get('name'));
            }

            if ($isChanged) {
                $isAnythingChanged = true;

                $clientDefs = $this->getMetadata()->getCustom('clientDefs', $entityType, (object) []);

                if (!empty($filterList)) {
                    if ($toAppend) {
                        array_unshift($filterList, '__APPEND__');
                    }
                    $clientDefs->filterList = $filterList;
                } else {
                    unset($clientDefs->filterList);
                }
                $this->getMetadata()->saveCustom('clientDefs', $entityType, $clientDefs);

                $entityDefs = $this->getMetadata()->getCustom('entityDefs', $entityType, (object) []);
                if (!isset($entityDefs->collection)) {
                    $entityDefs->collection = (object) [];
                }
                $entityDefs->collection->filters = $filtersData;
                $this->getMetadata()->saveCustom('entityDefs', $entityType, $entityDefs);

                foreach ($removedHash as $name => $item) {
                    $language->delete($entityType, 'presetFilters', $name);
                }
            }
        }
        if ($isAnythingChanged) {
            $language->save();

            $this->getInjection('dataManager')->clearCache();
        }
    }
}
