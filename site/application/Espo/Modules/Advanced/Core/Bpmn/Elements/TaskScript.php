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

use Espo\Core\Utils\Json;

class TaskScript extends Activity
{
    public function process()
    {
        $formula = $this->getAttributeValue('formula');

        if (!$formula) {
            $this->processNextElement();
            return;
        }

        if (!is_string($formula)) {
            $GLOBALS['log']->error('Process ' . $this->getProcess()->id . ', formula should be string.');
            $this->setFailed();
            return;
        }

        try {
            $variables = $this->getVariablesForFormula();
            $this->getFormulaManager()->run($formula, $this->getTarget(), $variables);
            $this->getEntityManager()->saveEntity($this->getTarget(), ['skipWorkflow' => true, 'skipModifiedBy' => true]);

            $this->sanitizeVariables($variables);

            $this->getProcess()->set('variables', $variables);
            $this->getEntityManager()->saveEntity($this->getProcess(), ['silent' => true]);
        } catch (\Throwable $e) {
            $GLOBALS['log']->error('Process ' . $this->getProcess()->id . ' formula error: ' . $e->getMessage());
            $this->setFailed();
            return;
        }

        $this->processNextElement();
    }

    protected function getFormulaManager()
    {
        return $this->getContainer()->get('formulaManager');
    }
}
