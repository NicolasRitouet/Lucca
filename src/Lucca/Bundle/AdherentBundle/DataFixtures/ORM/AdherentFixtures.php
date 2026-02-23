<?php

/*
 * Copyright (c) 2025. Numeric Wave
 *
 * Affero General Public License (AGPL) v3
 *
 * For more information, please refer to the LICENSE file at the root of the project.
 */

namespace Lucca\Bundle\AdherentBundle\DataFixtures\ORM;

use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

use Lucca\Bundle\AdherentBundle\Entity\Adherent;
use Lucca\Bundle\DepartmentBundle\DataFixtures\ORM\DepartmentFixtures;
use Lucca\Bundle\DepartmentBundle\Entity\Department;
use Lucca\Bundle\UserBundle\Entity\{Group, User};
use Lucca\Bundle\UserBundle\DataFixtures\ORM\{GroupFixtures, UserFixtures};
use Lucca\Bundle\UserBundle\Manager\UserManager;

class AdherentFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserManager $userManager,
    ) {
    }

    /**
     * @inheritdoc
     */
    public function load(ObjectManager $manager): void
    {
        $department = $this->getReference(DepartmentFixtures::DEPARTMENT_DEMO_REFERENCE, Department::class);
        $groups = $manager->getRepository(Group::class)->findAll();
        $superAdminGroup = $groups[0];

        /** Create a super admin user with an adherent profile */
        $user = new User();
        $user->setUsername('superadmin');
        $user->setEmail('superadmin@lucca.local');
        $user->setName('Super Admin');
        $user->setEnabled(true);
        $user->setPlainPassword('superadmin');
        $user->addGroup($superAdminGroup);

        $this->userManager->updateUser($user);
        $manager->persist($user);

        $adherent = new Adherent();
        $adherent->setUser($user);
        $adherent->setName('Admin');
        $adherent->setFirstname('Super');
        $adherent->setDepartment($department);
        $adherent->setFunction('Administrateur');

        $manager->persist($adherent);
        $manager->flush();
    }

    /**
     * @inheritdoc
     */
    public function getDependencies(): array
    {
        return [
            GroupFixtures::class,
            UserFixtures::class,
            DepartmentFixtures::class,
        ];
    }
}
