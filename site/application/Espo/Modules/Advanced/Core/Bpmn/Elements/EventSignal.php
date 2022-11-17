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

namespace Espo\Modules\Advanced\Core\Bpmn\Elements;

abstract class EventSignal extends Event
{
    protected function getSignalManager()
    {
        return $this->getContainer()->get('signalManager');
    }

    protected function getSignal(): ?string
    {
        $signal = $this->getAttributeValue('signal');

        if ($signal) {
            $target = $this->getTarget();

            foreach ($target->getAttributeList() as $a) {
                if (!$target->has($a) || !$target->get($a)) {
                    continue;
                }

                $value = $target->get($a);

                if (is_string($value)) {
                    $signal = str_replace('{$'.$a.'}', $value, $signal);
                }
            }

            $variables = $this->getVariables() ?? (object) [];

            foreach (get_object_vars($variables) as $key => $value) {
                if ($value && is_string($value)) {
                    $signal = str_replace('{$$'.$key.'}', $value, $signal);
                }
            }
        }

        return $signal;
    }
}
