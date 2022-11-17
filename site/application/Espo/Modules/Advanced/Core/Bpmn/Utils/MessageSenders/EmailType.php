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

namespace Espo\Modules\Advanced\Core\Bpmn\Utils\MessageSenders;

use \Espo\Core\Exceptions\Error;
use \Espo\ORM\Entity;
use \Espo\Modules\Advanced\Entities\BpmnProcess;
use \Espo\Modules\Advanced\Entities\BpmnFlowNode;

class EmailType
{
    private $container = null;

    public function __construct($container)
    {
        $this->container = $container;
    }

    public function process(Entity $target, BpmnFlowNode $flowNode, BpmnProcess $process, $createdEntitiesData, $variables)
    {
        $elementData = $flowNode->get('elementData');

        if (empty($elementData->from)) {
            throw new Error();
        }
        $from = $elementData->from;

        if (empty($elementData->to)) {
            throw new Error();
        }
        $to = $elementData->to;

        $replyTo = null;
        if (!empty($elementData->replyTo)) {
            $replyTo = $elementData->replyTo;
        }

        if (empty($elementData->emailTemplateId)) {
            throw new Error();
        }
        $emailTemplateId = $elementData->emailTemplateId;

        $doNotStore = false;
        if (isset($elementData->doNotStore)) {
            $doNotStore = $elementData->doNotStore;
        }

        $actionData = (object) [
            'type' => 'SendEmail',
            'from' => $from,
            'to' => $to,
            'replyTo' => $replyTo,
            'emailTemplateId' => $emailTemplateId,
            'doNotStore' => $doNotStore,
            'processImmediately' => true,
            'elementId' => $flowNode->get('elementId'),
            'optOutLink' => $elementData->optOutLink ?? false,
        ];

        if (property_exists($elementData, 'toEmailAddress')) {
            $actionData->toEmail = $elementData->toEmailAddress;
        }
        if (property_exists($elementData, 'fromEmailAddress')) {
            $actionData->fromEmail = $elementData->fromEmailAddress;
        }
        if (property_exists($elementData, 'replyToEmailAddress')) {
            $actionData->replyToEmail = $elementData->replyToEmailAddress;
        }

        if ($to && in_array($to, ['specifiedContacts', 'specifiedUsers', 'specifiedTeams'])) {
            $actionData->toSpecifiedEntityIds = $elementData->{'to' . ucfirst($to) . 'Ids'};
        }
        if ($replyTo && in_array($replyTo, ['specifiedContacts', 'specifiedUsers', 'specifiedTeams'])) {
            $actionData->replyToSpecifiedEntityIds = $elementData->{'replyTo' . ucfirst($replyTo) . 'Ids'};
        }

        $this->getActionImplementation()->process($target, $actionData, $createdEntitiesData, $variables, $process);
    }

    protected function getActionImplementation()
    {
        $name = 'SendEmail';
        $name = str_replace("\\", "", $name);
        $className = '\\Espo\\Custom\\Modules\\Advanced\\Core\\Workflow\\Actions\\' . $name;
        if (!class_exists($className)) {
            $className .= 'Type';
            if (!class_exists($className)) {
                $className = '\\Espo\\Modules\\Advanced\\Core\\Workflow\\Actions\\' . $name;
                if (!class_exists($className)) {
                    $className .= 'Type';
                    if (!class_exists($className)) {
                        throw new Error('Action class ' . $className . ' does not exist.');
                    }
                }
            }
        }
        $impl = new $className($this->container);
        return $impl;
    }
}
