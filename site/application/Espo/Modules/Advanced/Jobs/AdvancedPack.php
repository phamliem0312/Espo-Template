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

namespace Espo\Modules\Advanced\Jobs;

use DateTime;
use DateTimeZone;

class AdvancedPack extends \Espo\Core\Jobs\Base
{
    public function run()
    {
        $job = $this->getEntityManager()->getEntity('Job');

        $job->set([
            'name' => 'AdvancedPackJob',
            'serviceName' => 'AdvancedPack',
            'method' => 'advancedPackJob',
            'methodName' => 'advancedPackJob',
            'executeTime' => $this->getRunTime(),
        ]);

        $this->getEntityManager()->saveEntity($job);

        return true;
    }

    protected function getRunTime()
    {
        $hour = rand(0, 4);
        $minute = rand(0, 59);

        $nextDay = new DateTime('+ 1 day');

        $time = $nextDay->format('Y-m-d') . ' ' . $hour . ':' . $minute . ':00';

        $timeZone = $this->getConfig()->get('timeZone');

        if (empty($timeZone)) {
            $timeZone = 'UTC';
        }

        $datetime = new DateTime($time, new DateTimeZone($timeZone));

        return $datetime->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }
}
