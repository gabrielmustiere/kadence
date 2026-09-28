<?php

declare(strict_types=1);

namespace App\Tests\Security;

use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountGuardTest extends WebTestCase
{
    use CreatesUsers;

    public function testOpenSessionOfDeactivatedUserIsCut(): void
    {
        $client = self::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $client->request('GET', '/');
        self::assertResponseIsSuccessful();

        $this->reloadUser($user)->setActive(false);
        $this->entityManager()->flush();

        $client->request('GET', '/');
        self::assertResponseRedirects();

        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testUserWithTemporaryPasswordIsConfinedToPasswordChange(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser(mustChangePassword: true));

        $client->request('GET', '/');
        self::assertResponseRedirects('/mon-compte/mot-de-passe');

        $client->request('GET', '/mon-compte/mot-de-passe');
        self::assertResponseIsSuccessful();

        $client->request('GET', '/logout');
        $client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }
}
