<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\UserBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

use Lucca\Bundle\UserBundle\Entity\Group;

class GroupFixtures extends Fixture
{
    /**
     * @inheritdoc
     */
    public function load(ObjectManager $manager): void
    {
        $groups = [
            ['name' => 'Super Admin', 'displayed' => false, 'role' => ['ROLE_SUPER_ADMIN']],
            ['name' => 'Admin', 'displayed' => true, 'role' => ['ROLE_ADMIN']],
            ['name' => 'Lucca', 'displayed' => true, 'role' => ['ROLE_LUCCA']],
            ['name' => 'Visu', 'displayed' => true, 'role' => ['ROLE_VISU']],
        ];

        foreach ($groups as $group) {

            $newGroup = new Group();
            $newGroup->setName($group['name']);
            $newGroup->setDisplayed($group['displayed']);
            $newGroup->setRoles($group['role']);

            $manager->persist($newGroup);
        }

        $manager->flush();
    }
}
