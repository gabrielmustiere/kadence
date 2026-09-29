<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Repository\UserRepository;
use Psr\Cache\CacheItemPoolInterface;
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

    public function testDevLoginShortcutIsAbsentOutsideDev(): void
    {
        $client = self::createClient();

        $client->request('GET', '/login');

        self::assertSelectorNotExists('[data-test="dev-login-user"]');
        self::assertInputValueSame('_username', '');
        self::assertInputValueSame('_password', '');
    }

    public function testLoginWithValidCredentials(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->submitForm('Se connecter', [
            '_username' => 'admin@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/saisie');
        $client->followRedirect();
        self::assertSelectorTextContains('h1', 'Ma semaine');
    }

    public function testLoginWithInvalidCredentials(): void
    {
        $client = self::createClient();
        $this->resetLoginThrottling();
        $client->request('GET', '/login');

        $client->submitForm('Se connecter', [
            '_username' => 'admin@example.com',
            '_password' => 'wrong-password',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorExists('[role="alert"]');
    }

    public function testLoginIsCaseInsensitiveOnEmail(): void
    {
        $client = self::createClient();
        $client->request('GET', '/login');

        $client->submitForm('Se connecter', [
            '_username' => 'Admin@Example.COM',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/saisie');
    }

    public function testDeactivatedUserCannotLoginAndGetsGenericMessage(): void
    {
        $client = self::createClient();
        $this->resetLoginThrottling();
        $client->request('GET', '/login');
        $client->submitForm('Se connecter', [
            '_username' => 'admin@example.com',
            '_password' => 'wrong-password',
        ]);
        $client->followRedirect();
        $invalidCredentialsMessage = $client->getCrawler()->filter('[role="alert"]')->text();

        $client->request('GET', '/login');
        $client->submitForm('Se connecter', [
            '_username' => 'ancien@example.com',
            '_password' => 'password',
        ]);

        self::assertResponseRedirects('/login');
        $client->followRedirect();
        self::assertSelectorTextSame('[role="alert"]', $invalidCredentialsMessage);
    }

    public function testLoginIsThrottledAfterRepeatedFailures(): void
    {
        $client = self::createClient();
        $this->resetLoginThrottling();

        for ($attempt = 1; $attempt <= 6; ++$attempt) {
            $client->request('GET', '/login');
            $client->submitForm('Se connecter', [
                '_username' => 'prod@example.com',
                '_password' => 'wrong-password',
            ]);
        }

        $client->followRedirect();
        self::assertSelectorTextContains('[role="alert"]', 'Plusieurs tentatives de connexion ont échoué');
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

    private function resetLoginThrottling(): void
    {
        $rateLimiterCache = self::getContainer()->get('cache.rate_limiter');
        \assert($rateLimiterCache instanceof CacheItemPoolInterface);
        $rateLimiterCache->clear();
    }
}
