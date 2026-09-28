<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class SecurityControllerTest extends WebTestCase
{
    public function testAnonymousUserIsRedirectedToLogin(): void
    {
        $client = self::createClient();

        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageIsDisplayed(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Se connecter');
    }

    public function testLoginWithValidCredentials(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->submitForm('Se connecter', [
            '_username' => 'admin@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Tableau de bord');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->submitForm('Se connecter', [
            '_username' => 'admin@example.com',
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
    }

    public function testLogout(): void
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);
        self::assertInstanceOf(UserRepository::class, $userRepository);
        $user = $userRepository->findOneBy(['email' => 'admin@example.com']);
        self::assertNotNull($user);
        $client->loginUser($user);

        $client->request('GET', '/logout');
        $client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }
}
