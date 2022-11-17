<?php
/**LICENE**/

namespace Espo\Modules\Advanced\Hooks\BpmnUserTask;

use Espo\ORM\Entity;

class Resolve extends \Espo\Core\Hooks\Base
{
    public function afterSave(Entity $entity)
    {
        if (!$entity->get('flowNodeId')) return;

        if (!$entity->getFetched('isResolved') && $entity->get('isResolved')) {
            $flowNode = $this->getEntityManager()->getEntity('BpmnFlowNode', $entity->get('flowNodeId'));
            if (!$flowNode) return;
            if ($flowNode->get('status') !== 'In Process') return;

            $manager = new \Espo\Modules\Advanced\Core\Bpmn\BpmnManager($this->getContainer());
            $manager->completeFlow($flowNode);
            return;
        }
    }
}
