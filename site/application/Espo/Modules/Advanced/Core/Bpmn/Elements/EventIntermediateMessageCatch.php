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

use \Espo\Core\Exceptions\Error;

class EventIntermediateMessageCatch extends Event
{
    public function process()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->set([
            'status' => 'Pending',
        ]);
        $this->getEntityManager()->saveEntity($flowNode);
    }

    public function proceedPending()
    {
        $repliedToAliasId = $this->getAttributeValue('repliedTo');
        $messageType = $this->getAttributeValue('messageType') ?? 'Email';
        $relatedTo = $this->getAttributeValue('relatedTo');

        $conditionsFormula = $this->getAttributeValue('conditionsFormula');
        $conditionsFormula = trim($conditionsFormula, " \t\n\r");
        if (strlen($conditionsFormula) && substr($conditionsFormula, -1) === ';') {
            $conditionsFormula = substr($conditionsFormula, 0, -1);
        }

        $target = $this->getTarget();

        $createdEntitiesData = $this->getCreatedEntitiesData() ?? (object) [];

        $repliedToId = null;

        if ($repliedToAliasId) {
            if (!isset($createdEntitiesData->$repliedToAliasId)) return;
            $repliedToId = $createdEntitiesData->$repliedToAliasId->entityId ?? null;
            $repliedToType = $createdEntitiesData->$repliedToAliasId->entityType ?? null;

            if (!$repliedToId || $messageType !== $repliedToType) {
                $this->fail();
                return;
            }
        }

        $flowNode = $this->getFlowNode();

        if ($messageType === 'Email') {
            $from = $flowNode->getDataItemValue('checkedAt') ?? $flowNode->get('createdAt');

            $selectParams = [
                'createdAt>=' => $from,
                'status' => 'Archived',
                'dateSent>=' => $flowNode->get('createdAt'),
                [
                    'OR' => [
                        'sentById' => null,
                        'sentBy.type' => 'portal',
                    ]
                ],
            ];

            if ($repliedToId) {
                $selectParams['repliedId'] = $repliedToId;

            } else if ($relatedTo) {
                $relatedTarget = $this->getSpecificTarget($relatedTo);

                if (!$relatedTarget) {
                    $this->updateCheckedAt();
                    return;
                }

                if ($relatedTarget->getEntityType() === 'Account') {
                    $selectParams['accountId'] = $relatedTarget->id;
                } else {
                    $selectParams['parentId'] = $relatedTarget->id;
                    $selectParams['parentType'] = $relatedTarget->getEntityType();
                }
            }

            if (!$repliedToId && !$relatedTo) {
                if ($target->getEntityType() === 'Contact' && $target->get('accountId')) {
                    $selectParams[] = [
                        'OR' => [
                            [
                                'parentType' => 'Contact',
                                'parenentId' => $target->id,
                            ],
                            [
                                'parentType' => 'Account',
                                'parenentId' => $target->get('accountId'),
                            ],
                        ]
                    ];
                } else if ($target->getEntityType() === 'Account') {
                    $selectParams['accountId'] = $target->id;
                } else {
                    $selectParams['parentId'] = $target->id;
                    $selectParams['parentType'] = $target->getEntityType();
                }
            }

            $limit = $this->getContainer()->get('config')->get('bpmnMessageCatchLimit', 50);

            $emailList = $this->getEntityManager()->getRepository('Email')
                ->leftJoin(['sentBy'])
                ->where($selectParams)
                ->limit(0, $limit)
                ->find();

            if (!count($emailList)) {
                $this->updateCheckedAt();
                return;
            }

            if ($conditionsFormula) {
                $isFound = false;
                foreach ($emailList as $email) {
                    $formulaResult = $this->getFormulaManager()->run($conditionsFormula, $email, $this->getVariablesForFormula());
                    if ($formulaResult) {
                        $isFound = true;
                        break;
                    }
                }
                if (!$isFound) {
                    $this->updateCheckedAt();
                    return;
                }
            }

        } else {
            $this->fail();
            return;
        }

        $flowNode = $this->getFlowNode();
        $flowNode->set('status', 'In Process');
        $this->getEntityManager()->saveEntity($flowNode);

        $this->proceedPendingFinal();
    }

    protected function proceedPendingFinal()
    {
        $this->rejectConcurrentPendingFlows();
        $this->processNextElement();
    }

    protected function updateCheckedAt()
    {
        $flowNode = $this->getFlowNode();
        $flowNode->setDataItemValue('checkedAt', date('Y-m-d H:i:s'));
        $this->getEntityManager()->saveEntity($flowNode);
    }

    protected function getFormulaManager()
    {
        return $this->getContainer()->get('formulaManager');
    }
}
