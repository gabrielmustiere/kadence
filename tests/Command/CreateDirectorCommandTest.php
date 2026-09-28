<?php

declare(strict_types=1);

namespace App\Tests\Command;

use App\Enum\Type\Role;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class CreateDirectorCommandTest extends KernelTestCase
{
    public function testCreatesDirectorWithTemporaryPassword(): void
    {
        $email = uniqid('director-', true) . '@example.com';

        $commandTester = $this->commandTester();
        $commandTester->execute(['email' => $email, 'first-name' => 'Diane', 'last-name' => 'Leroy']);

        $commandTester->assertCommandIsSuccessful();
        self::assertStringContainsString('Mot de passe provisoire', $commandTester->getDisplay());

        $userRepository = self::getContainer()->get(UserRepository::class);
        \assert($userRepository instanceof UserRepository);
        $user = $userRepository->findOneByEmail($email);
        self::assertNotNull($user);
        self::assertSame(Role::Direction, $user->getRole());
        self::assertSame('Diane', $user->getFirstName());
        self::assertTrue($user->mustChangePassword());

        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        \assert($entityManager instanceof EntityManagerInterface);
        $entityManager->remove($user);
        $entityManager->flush();
    }

    public function testRefusesAlreadyUsedEmail(): void
    {
        $commandTester = $this->commandTester();
        $commandTester->execute(['email' => 'ADMIN@example.com', 'first-name' => 'Alice', 'last-name' => 'Martin']);

        self::assertSame(Command::FAILURE, $commandTester->getStatusCode());
        self::assertStringContainsString('déjà utilisé', $commandTester->getDisplay());
    }

    private function commandTester(): CommandTester
    {
        $application = new Application(self::bootKernel());

        return new CommandTester($application->find('app:create-director'));
    }
}
