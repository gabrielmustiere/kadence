<?php

namespace DataFixtures;

use App\Entity\User;
use App\Enum\Type\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $manager->persist($this->createUser('admin@example.com', 'Alice', 'Martin', Role::Direction));
        $manager->persist($this->createUser('lead@example.com', 'Louis', 'Bernard', Role::Lead));
        $manager->persist($this->createUser('prod@example.com', 'Paula', 'Durand', Role::Prod));
        $manager->persist($this->createUser('ancien@example.com', 'Arthur', 'Petit', Role::Prod)->setActive(false));
        $manager->flush();
    }

    /**
     * @param non-empty-string $email
     * @param non-empty-string $firstName
     * @param non-empty-string $lastName
     */
    private function createUser(string $email, string $firstName, string $lastName, Role $role): User
    {
        $user = new User()
            ->setEmail($email)
            ->setFirstName($firstName)
            ->setLastName($lastName)
            ->setRole($role);

        return $user->setPassword($this->passwordHasher->hashPassword($user, 'password'));
    }
}
