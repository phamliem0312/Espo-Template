<?php
/**LICENE**/

namespace Espo\Modules\Advanced\Hooks\BpmnProcess;

use Espo\ORM\Entity;

class StopProcess extends \Espo\Core\Hooks\Base
{
    public function afterSave(Entity $entity, array $options = [])
    {
        if (!empty($options['skipStopProcess'])) return;

        if ($entity->isNew()) return;
        if (!$entity->isAttributeChanged('status')) return;
        if ($entity->get('status') !== 'Stopped') return;

        $manager = new \Espo\Modules\Advanced\Core\Bpmn\BpmnManager($this->getContainer());
        $manager->stopProcess($entity);

        $subProcessList = $this->getEntityManager()->getRepository('BpmnProcess')->where([
            'parentProcessId' => $entity->id,
        ])->find();

        foreach ($subProcessList as $e) {
            $manager->stopProcess($e);
        }
    }
}
