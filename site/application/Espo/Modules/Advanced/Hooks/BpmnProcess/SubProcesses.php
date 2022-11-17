<?php
/**LICENE**/

namespace Espo\Modules\Advanced\Hooks\BpmnProcess;

use Espo\ORM\Entity;

class SubProcesses extends \Espo\Core\Hooks\Base
{
    public function afterRemove(Entity $entity, array $options = [])
    {
        $subProcessList = $this->getEntityManager()->getRepository('BpmnProcess')->where([
            'parentProcessId' => $entity->id,
        ])->find();

        foreach ($subProcessList as $e) {
            $this->getEntityManager()->removeEntity($e, $options);
        }
    }
}
