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

use \Espo\Core\Exceptions\Error;
use \Espo\Core\Exceptions\NotFound;

use \Espo\Modules\Advanced\Business\Report\EmailBuilder;

class ReportSending extends \Espo\Core\Services\Base
{
    const LIST_REPORT_MAX_SIZE = 3000;

    protected function init()
    {
        parent::init();

        $this->addDependency('entityManager');
        $this->addDependency('serviceFactory');
        $this->addDependency('user');
        $this->addDependency('metadata');
        $this->addDependency('config');
        $this->addDependency('language');
        $this->addDependency('mailSender');
        $this->addDependency('preferences');
        $this->addDependency('fileManager');
        $this->addDependency('fieldManager');
        $this->addDependency('templateFileManager');
        $this->addDependency('injectableFactory');
        $this->addDependency('dateTime');
        $this->addDependency('number');
        $this->addDependency('selectManagerFactory');
        $this->addDependency('fileStorageManager');
        $this->addDependency('htmlizerFactory');
    }

    protected function getPDO()
    {
        return $this->getEntityManager()->getPDO();
    }

    protected function getLanguage()
    {
        return $this->injections['language'];
    }

    protected function getServiceFactory()
    {
        return $this->injections['serviceFactory'];
    }

    protected function getEntityManager()
    {
        return $this->injections['entityManager'];
    }

    protected function getUser()
    {
        return $this->injections['user'];
    }

    protected function getConfig()
    {
        return $this->injections['config'];
    }

    protected function getMetadata()
    {
        return $this->injections['metadata'];
    }

    protected function getMailSender()
    {
        return $this->injections['mailSender'];
    }

    protected function getPreferences()
    {
        return $this->injections['preferences'];
    }

    protected function getFieldManager()
    {
        return $this->getInjection('fieldManager');
    }

    protected function getTemplateFileManager()
    {
        return $this->getInjection('templateFileManager');
    }

    protected function createHtmlizer()
    {
        return $this->getInjection('htmlizerFactory')->create(true);
    }

    protected function getHtmlizer()
    {
        if (empty($this->htmlizer)) {
            $this->htmlizer = $this->createHtmlizer();
        }

        return $this->htmlizer;
    }

    protected function createReportEmailBuilder()
    {
        $reportService = $this->getServiceFactory()->create('Report');

        return new EmailBuilder(
            $this->getMetadata(),
            $this->getEntityManager(),
            null,
            $this->getMailSender(),
            $this->getConfig(),
            $this->getLanguage(),
            $this->getHtmlizer(),
            $this->getTemplateFileManager(),
            $reportService,
            $this->getInjection('fileStorageManager')
        );
    }

    protected function getRecordService($name)
    {
        if ($this->getServiceFactory()->checkExists($name)) {
            $service = $this->getServiceFactory()->create($name);
            $service->setEntityType($name);
        } else {
            $service = $this->getServiceFactory()->create('Record');
            if (method_exists($service, 'setEntityType')) {
                $service->setEntityType($name);
            } else {
                $service->setEntityName($name);
            }
        }

        return $service;
    }

    protected function getSendingListMaxCount()
    {
        return $this->getConfig()->get('reportSendingListMaxCount', self::LIST_REPORT_MAX_SIZE);
    }

    public function getEmailAttributes($id, $where = null, $user = null)
    {
        $service = $this->getServiceFactory()->create('Report');
        $report = $this->getEntityManager()->getEntity('Report', $id);
        if (empty($report)) {
            throw new NotFound();
        }
        if (!$user) {
            $user =  $this->getUser();
        }
        $params = [];

        if ($report->get('type') == 'List') {
            $params = [
                'offset' => 0,
                'maxSize' => $this->getSendingListMaxCount()
            ];
            $orderByList = $report->get('orderByList');
            if ($orderByList) {
                $arr = explode(':', $orderByList);
                $params['sortBy'] = $arr[1];
                $params['asc'] = $arr[0] === 'ASC';
            }
        }

        $additionaParams = [];

        $result = $service->run($id, $where, $params, $additionaParams, $user);
        $reportResult = (isset($result['collection']) && is_object($result['collection'])) ?
            $result['collection']->toArray() : $result;

        $data = new \StdClass();

        $data->userId = $user->id;

        $sender = $this->createReportEmailBuilder();

        $sender->buildEmailData($data, $reportResult, $report);

        $attachmentId = $this->getExportAttachmentId($report, $result, $where, $user);
        if ($attachmentId) {
            $data->attachmentId = $attachmentId;

            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            if ($attachment) {
                $attachment->set([
                    'role' => 'Attachment',
                    'parentType' => 'Email',
                    'relatedId' => $id,
                    'relatedType' => 'Report',
                ]);
                $this->getEntityManager()->saveEntity($attachment);
            }
        }

        $userIdList = $report->getLinkMultipleIdList('emailSendingUsers');

        $nameHash = (object) [];
        $to = '';
        $toArr = [];
        if ($report->get('emailSendingInterval') && count($userIdList)) {
            $userList = $this->getEntityManager()->getRepository('User')->where(array('id' => $userIdList))->find();
            foreach ($userList as $user) {
                $emailAddress = $user->get('emailAddress');
                if ($emailAddress) {
                    $toArr[] = $emailAddress;
                    $nameHash->$emailAddress = $user->get('name');
                }
            }
        }

        $attributes = [
            'isHtml' => true,
            'body' => $data->emailBody,
            'name' => $data->emailSubject,
            'nameHash' => $nameHash,
            'to' => implode(';', $toArr),
        ];

        if ($attachmentId) {
            $attributes['attachmentsIds'] = [$attachmentId];
            $attachment = $this->getEntityManager()->getEntity('Attachment', $attachmentId);
            if ($attachment) {
                $attributes['attachmentsNames'] = [
                    $attachmentId => $attachment->get('name')
                ];
            }
        }

        return $attributes;
    }

    public function sendReport($data)
    {
        if (is_array($data)) {
            $data = (object)$data;
        }

        if (!is_object($data) || !isset($data->userId) || !isset($data->reportId)) {
            throw new Error('Report Sending: Not enough data for sending email.');
        }
        $reportId = $data->reportId;
        $userId = $data->userId;
        $smtpParams = $this->getPreferences()->getSmtpParams();
        $service = $this->getServiceFactory()->create('Report');
        $report = $this->getEntityManager()->getEntity('Report', $reportId);
        if (empty($report)) {
            throw new Error('Report Sending: No Report ' . $reportId);
        }

        $user = $this->getEntityManager()->getEntity('User', $userId);

        if (empty($user)) {
            throw new Error('Report Sending: No user with id ' . $userId);
        }

        $params = [];

        if ($report->get('type') == 'List') {
            $params = [
                'offset' => 0,
                'maxSize' => $this->getSendingListMaxCount()
            ];

            $orderByList = $report->get('orderByList');

            if ($orderByList) {
                $arr = explode(':', $orderByList);
                $params['sortBy'] = $arr[1];
                $params['asc'] = $arr[0] === 'ASC';
            }
        }

        $additionaParams = [];

        $result = $service->run($reportId, [], $params, $additionaParams, $user);

        $reportResult = (isset($result['collection']) && is_object($result['collection'])) ?
            $result['collection']->toArray() : $result;

        if (count($reportResult) == 0 && $report->get('emailSendingDoNotSendEmptyReport')) {
            $GLOBALS['log']->debug('Report Sending: Report ' . $report->get('name') . ' is empty and was not sent');
            return false;
        }
        $sender = $this->createReportEmailBuilder();

        $sender->buildEmailData($data, $reportResult, $report);

        $attachmentId = $this->getExportAttachmentId($report, $result, null, $user);

        if ($attachmentId) {
            $data->attachmentId = $attachmentId;
        }

        $sender->sendEmail($data);

        return true;
    }

    protected function getExportAttachmentId($report, $resultData, $where = null, $user = null)
    {
        $entityType = $report->get('entityType');
        $targetEntityService = $this->getRecordService($entityType);
        if (!method_exists($targetEntityService, 'exportCollection')) {
            return false;
        }

        if ($report->get('type') === 'List') {
            if (!array_key_exists('collection', $resultData)) {
                return false;
            }

            $fieldList = $report->get('columns');

            foreach ($fieldList as $key => $field) {
                if (strpos($field, '.')) {
                    $fieldList[$key] = str_replace('.', '_', $field);

                }
            }

            $attributeList = [];
            foreach ($fieldList as $field) {
                $fieldAttributeList = $this->getFieldManager()->getAttributeList($report->get('entityType'), $field);

                if (is_array($fieldAttributeList) && count($fieldAttributeList) > 0) {
                    $attributeList = array_merge($attributeList, $fieldAttributeList);
                } else {
                    $attributeList[] = $field;
                }
            }

            $exportParams = array(
                'fieldList' => $fieldList,
                'attributeList' => $attributeList,
                'format' => 'xlsx',
                'exportName' => $report->get('name'),
                'fileName' => $report->get('name') . ' ' . date('Y-m-d')
            );
            try {
                return $targetEntityService->exportCollection($exportParams, $resultData['collection']);
            } catch (\Exception $e) {
                $GLOBALS['log']->error('Report export fail[' . $report->id . ']: ' . $e->getMessage());
                return false;
            }
        } else {
            $name = $report->get('name');
            $name = preg_replace("/([^\w\s\d\-_~,;:\[\]\(\).])/u", '_', $name) . ' ' . date('Y-m-d');
            $mimeType = $this->getMetadata()->get(['app', 'export', 'formatDefs', 'xlsx', 'mimeType']);
            $fileExtension = $this->getMetadata()->get(['app', 'export', 'formatDefs', 'xlsx', 'fileExtension']);
            $fileName = $name . '.' . $fileExtension;

            try {
                $service = $this->getServiceFactory()->create('Report');
                $contents = $service->getGridReportXlsx($report->id, $where, $user);

                $attachment = $this->getEntityManager()->getEntity('Attachment');
                $attachment->set(array(
                    'name' => $fileName,
                    'type' => $mimeType,
                    'contents' => $contents,
                    'role' => 'Attachment',
                    'parentType' => 'Email'
                ));
                $this->getEntityManager()->saveEntity($attachment);

                return $attachment->id;
            } catch (\Exception $e) {
                $GLOBALS['log']->error('Report export fail[' . $report->id . ']: ' . $e->getMessage());
                return false;
            }
        }
    }

    public function scheduleEmailSending()
    {
        $reports = $this->getEntityManager()->getRepository('Report')->where([[
            'AND' => [
              ['emailSendingInterval!=' => ''],
              ['emailSendingInterval!=' => NULL]
            ]]])->find();

        $utcTZ = new \DateTimeZone('UTC');
        $now = new \DateTime("now", $utcTZ);

        $defaultTz = $this->getConfig()->get('timeZone');
        $espoTimeZone = new \DateTimeZone($defaultTz);

        foreach ($reports as $report) {
            $scheduleSending = false;
            $check = false;

            $nowCopy = clone $now;
            $nowCopy->setTimezone($espoTimeZone);

            switch ($report->get('emailSendingInterval')) {
                case 'Daily':
                    $check = true;
                    break;
                case 'Weekly':
                    $check = (strpos($report->get('emailSendingSettingWeekdays'), $nowCopy->format('w')) !== false);
                    break;
                case 'Monthly':
                    $check =
                        $nowCopy->format('j') == $report->get('emailSendingSettingDay') ||
                        $nowCopy->format('j') == $nowCopy->format('t') && $nowCopy->format('t') < $report->get('emailSendingSettingDay');
                    break;
                case 'Yearly':
                    $check =
                        (
                            $nowCopy->format('j') == $report->get('emailSendingSettingDay') ||
                            $nowCopy->format('j') == $nowCopy->format('t') && $nowCopy->format('t') < $report->get('emailSendingSettingDay')
                        ) &&
                        $nowCopy->format('n') == $report->get('emailSendingSettingMonth');
                    break;
            }
            if ($check) {
                if ($report->get('emailSendingLastDateSent')) {
                    $lastSent = new \DateTime($report->get('emailSendingLastDateSent'), $utcTZ);
                    $lastSent->setTimezone($espoTimeZone);

                    $nowCopy->setTime(0,0,0);
                    $lastSent->setTime(0,0,0);
                    $diff = $lastSent->diff($nowCopy);

                    if (!empty($diff)) {
                        $dayDiff = (int) ((($diff->invert) ? '-' : '') . $diff->days);
                        if ($dayDiff > 0) {
                            $scheduleSending = true;
                        }
                    }
                } else {
                    $scheduleSending = true;
                }
            }
            if ($scheduleSending) {
                $report->loadLinkMultipleField('emailSendingUsers');
                $users = $report->get('emailSendingUsersIds');
                if (empty($users)) {
                    continue;
                }

                $executeTime = clone $now;

                if ($report->get('emailSendingTime')) {
                    $time = explode(':', $report->get('emailSendingTime'));

                    if (empty($time[0]) || $time[0] < 0 && $time[0] > 23) {
                        $time[0] = 0;
                    }
                    if (empty($time[1]) || $time[1] < 0 && $time[1] > 59) {
                        $time[1] = 0;
                    }

                    $executeTime->setTimezone($espoTimeZone);
                    $executeTime->setTime($time[0], $time[1], 0);
                    $executeTime->setTimezone($utcTZ);
                }

                $report->set('emailSendingLastDateSent', $executeTime->format('Y-m-d H:i:s'));
                $this->getEntityManager()->saveEntity($report);

                $emailManager = $this->createReportEmailBuilder();

                foreach ($users as $userId) {
                    $jobEntity = $this->getEntityManager()->getEntity('Job');

                    $data = array(
                        'userId' => $userId,
                        'reportId' => $report->id
                    );

                    $jobEntity->set(array(
                        'name' => '',
                        'executeTime' => $executeTime->format('Y-m-d H:i:s'),
                        'method' => 'sendReport',
                        'methodName' => 'sendReport',
                        'data' => $data,
                        'serviceName' => 'ReportSending'
                    ));

                    $jobEntityId = $this->getEntityManager()->saveEntity($jobEntity);
                }
            }
        }
    }

    public function buildData($data, $result, $report)
    {
        $helper = $this->createReportEmailBuilder();

        $helper->setIsLocal(true);

        $helper->buildEmailData($data, $result, $report);
    }
}
