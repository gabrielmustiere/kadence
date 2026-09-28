<?php

declare(strict_types=1);

namespace App\Tests;

use App\Repository\UserRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class ApplicationAvailabilityFunctionalTest extends WebTestCase
{
    #[DataProvider('urlProvider')]
    public function testPageIsSuccessful(string $url): void
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $userRepository);
        $user = $userRepository->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', $url);

        self::assertResponseIsSuccessful();
    }

    /**
     * @return \Generator<array{string}>
     */
    public static function urlProvider(): \Generator
    {
        yield ['/'];
        yield ['/design-system'];
        yield ['/login'];
    }
}
