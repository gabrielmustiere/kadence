<?php

declare(strict_types=1);

namespace DataFixtures;

use App\Entity\User;
use App\Enum\Type\Role;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public const string DIRECTOR = 'user-director';
    public const string LEAD = 'user-lead';
    public const string PROD = 'user-prod';
    public const string FORMER = 'user-former';

    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
    }

    public function load(ObjectManager $manager): void
    {
        $users = [
            self::DIRECTOR => $this->createUser('thomas.cerqueus@anyti.me', 'Thomas', 'Cerqueus', Role::Direction),
            self::LEAD => $this->createUser('olivier.thomas@anyti.me', 'Olivier', 'Thomas', Role::Lead),
            self::PROD => $this->createUser('antoine.leroy@anyti.me', 'Antoine', 'Leroy', Role::Prod),
            self::FORMER => $this->createUser('arthur.petit@anyti.me', 'Arthur', 'Petit', Role::Prod)->setActive(false),
        ];

        foreach ($users as $reference => $user) {
            $manager->persist($user);
            $this->addReference($reference, $user);
        }
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
