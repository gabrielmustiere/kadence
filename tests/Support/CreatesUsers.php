<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\User;
use App\Enum\Type\Role;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

trait CreatesUsers
{
    private function createUser(Role $role = Role::Prod, string $password = 'password', bool $mustChangePassword = false): User
    {
        $container = static::getContainer();
        $hasher = $container->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);

        $user = new User()
            ->setEmail(uniqid('user-', true) . '@example.com')
            ->setFirstName('Test')
            ->setLastName('User')
            ->setRole($role)
            ->setMustChangePassword($mustChangePassword);
        $user->setPassword($hasher->hashPassword($user, $password));

        $entityManager = $this->entityManager();
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }

    private function reloadUser(User $user): User
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $reloaded = $entityManager->find(User::class, $user->getId());
        \assert($reloaded instanceof User);

        return $reloaded;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);

        return $entityManager;
    }
}
