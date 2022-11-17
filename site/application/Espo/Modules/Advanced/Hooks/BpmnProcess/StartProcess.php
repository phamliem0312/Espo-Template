<?php
/**LICENE**/

namespace Espo\Modules\Advanced\Hooks\BpmnProcess;

use Espo\ORM\Entity;

class StartProcess extends \Espo\Core\Hooks\Base
{
    public function afterSave(Entity $entity, array $options = array())
    {
        if (!$entity->isNew()) return;

        if (!empty($options['skipStartProcessFlow'])) return;

        $manager = new \Espo\Modules\Advanced\Core\Bpmn\BpmnManager($this->getContainer());
        $manager->startCreatedProcess($entity);
    }
}
