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

namespace Espo\Modules\Advanced\Core;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;
use Espo\Core\Utils\Config;

class SignalManager
{
    protected $entityManager;

    protected $workflowManager;

    protected $config;

    public function __construct(EntityManager $entityManager, WorkflowManager $workflowManager, Config $config)
    {
        $this->entityManager = $entityManager;
        $this->workflowManager = $workflowManager;
        $this->config = $config;
    }

    public function trigger($signal, ?Entity $entity = null, array $options = []) : void
    {
        if (is_array($signal)) {
            $signal = implode('.', $signal);
        }

        if ($this->config->get('signalsDisabled')) {
            return;
        }

        if ($entity) {
            $this->workflowManager->process($entity, '$' . $signal, $options);

            return;
        }

        if ($this->config->get('signalsRegularDisabled')) {
            return;
        }

        $listenerList = $this->entityManager
            ->getRepository('BpmnSignalListener')
            ->select(['id'])
            ->order('number')
            ->where([
                'name' => $signal,
                'isTriggered' => false,
            ])
            ->find();

        foreach ($listenerList as $item) {
            $item->set('isTriggered', true);
            $item->set('triggeredAt', date('Y-m-d H:i:s'));

            $this->entityManager->saveEntity($item);
        }
    }

    public function subscribe(string $signal, string $flowNodeId) : ?string
    {
        if ($this->config->get('signalsDisabled')) {
            return null;
        }

        if ($this->config->get('signalsRegularDisabled')) {
            return null;
        }

        $item = $this->entityManager->createEntity('BpmnSignalListener', [
            'name' => $signal,
            'flowNodeId' => $flowNodeId,
        ]);

        return $item->id;
    }

    public function unsubscribe(string $id) : void
    {
        $this->entityManager->getRepository('SignalListener')->deleteFromDb($id);
    }
}
