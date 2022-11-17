<?php
/*********************************************************************************
 * The contents of this file are subject to the EspoCRM VoIP Integration
 * Extension Agreement ("License") which can be viewed at
 * https://www.espocrm.com/voip-extension-agreement.
 * By installing or using this file, You have unconditionally agreed to the
 * terms and conditions of the License, and You may not use this file except in
 * compliance with the License.  Under the terms of the license, You shall not,
 * sublicense, resell, rent, lease, distribute, or otherwise  transfer rights
 * or usage to the software.
 * 
 * Copyright (C) 2015-2021 Letrium Ltd.
 * 
 * License ID: e36042ded1ed7ba87a149ac5079bd238
 ***********************************************************************************/

namespace Espo\Modules\Voip\Jobs;

use Espo\Core\Exceptions;

class VoipCleanup extends \Espo\Core\Jobs\Base
{
    protected $cleanupPeriod = '1 month';

    public function run()
    {
        $query = "DELETE FROM voip_event WHERE DATE(modified_at) < '".$this->getCleanupFromDate()."' ";

        $pdo = $this->getEntityManager()->getPDO();
        $sth = $pdo->prepare($query);
        $sth->execute();
    }

    protected function getCleanupFromDate()
    {
        $period = '-' . $this->cleanupPeriod;
        $datetime = new \DateTime();
        $datetime->modify($period);

        return $datetime->format('Y-m-d');
    }
}