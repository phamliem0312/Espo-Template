<?php
/************************************************************************
 * This file is part of EspoCRM.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2022 Yurii Kuznietsov, Taras Machyshyn, Oleksii Avramenko
 * Website: https://www.espocrm.com
 *
 * EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word.
 ************************************************************************/

namespace Espo\Modules\Crm\Hooks\Contact;

use Espo\ORM\EntityManager;
use Espo\ORM\Entity;

class Opportunities
{
    private EntityManager $entityManager;

    public function __construct(EntityManager $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $data
     */
    public function afterRelate(Entity $entity, array $options = [], array $data = []): void
    {
        $relationName = $data['relationName'] ?? null;
        /** @var ?Entity */
        $foreignEntity = $data['foreignEntity'] ?? null;

        if ($relationName === 'opportunities' && $foreignEntity) {
            if (!$foreignEntity->get('contactId') && $foreignEntity->has('contactId')) {
                $foreignEntity->set('contactId', $entity->getId());

                $this->entityManager->saveEntity($foreignEntity);
            }
        }
    }

    /**
     * @param array<string,mixed> $options
     * @param array<string,mixed> $data
     */
    public function afterUnrelate(Entity $entity, array $options = [], array $data = []): void
    {
        $relationName = $data['relationName'] ?? null;
        /** @var ?Entity */
        $foreignEntity = $data['foreignEntity'] ?? null;

        if ($relationName === 'opportunities' && $foreignEntity) {
            if ($foreignEntity->get('contactId') && $foreignEntity->get('contactId') === $entity->getId()) {
                $foreignEntity->set('contactId', null);

                $this->entityManager->saveEntity($foreignEntity);
            }
        }
    }
}
