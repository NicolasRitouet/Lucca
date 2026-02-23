<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\DepartmentBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use Lucca\Bundle\DepartmentBundle\Entity\Department;

class DepartmentFixtures extends Fixture
{
    public const DEPARTMENT_DEMO_REFERENCE = 'department-demo';

    /**
     * @inheritdoc
     */
    public function load(ObjectManager $manager): void
    {
        $department = new Department();
        $department->setCode('demo');
        $department->setName('Démo');

        $manager->persist($department);
        $manager->flush();

        $this->addReference(self::DEPARTMENT_DEMO_REFERENCE, $department);
    }
}
