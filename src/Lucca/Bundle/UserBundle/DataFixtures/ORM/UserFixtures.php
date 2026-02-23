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
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

use Lucca\Bundle\UserBundle\Entity\{Group, User};
use Lucca\Bundle\UserBundle\Manager\UserManager;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    public function __construct(
        private readonly UserManager $userManager,
    )
    {
    }

    /**
     * @inheritdoc
     */
    public function load(ObjectManager $manager): void
    {
        [$adminGroup, $_, $userGroup] = $manager->getRepository(Group::class)->findAll();

        $users = [
            ['username' => 'admin', 'email' => 'admin@lucca.local', 'name' => 'Admin', 'group' => $adminGroup, 'password' => 'password'],
            ['username' => 'user', 'email' => 'user@lucca.local', 'name' => 'User', 'group' => $userGroup, 'password' => 'password'],
        ];

        foreach ($users as $user) {
            $newUser = new User();
            $newUser->setUsername($user['username']);
            $newUser->setEmail($user['email']);
            $newUser->setName($user['name']);
            $newUser->setEnabled(true);
            $newUser->setPlainPassword($user['password']);
            $newUser->addGroup($user['group']);

            $this->userManager->updateUser($newUser);

            $manager->persist($newUser);
        }

        $manager->flush();
    }

    /**
     * @inheritdoc
     */
    public function getDependencies(): array
    {
        return [
            GroupFixtures::class,
        ];
    }
}
