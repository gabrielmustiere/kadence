<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Enum\Type\TagCategory;
use App\Tests\Support\CreatesTags;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

final class AccountControllerTest extends WebTestCase
{
    use CreatesTags;
    use CreatesUsers;

    private const string STRONG_PASSWORD = 'Correct horse battery staple 42';

    public function testMyAccountShowsMyOwnTagsAndManagerReadOnly(): void
    {
        $client = self::createClient();
        $skill = $this->createTag(TagCategory::TechnicalSkill);
        $teamType = $this->createTag(TagCategory::TeamType);
        $othersTag = $this->createTag(TagCategory::FunctionalExperience);
        $manager = $this->createUser()->setFirstName('Zoé')->setLastName(uniqid('Manager', false));
        $this->giveTags($this->createUser()->setManager($manager), $othersTag);
        $user = $this->giveTags($this->createUser()->setManager($manager), $skill, $teamType);
        $client->loginUser($user);

        $client->request('GET', '/mon-compte/mot-de-passe');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('[data-test="account-tags-competence"]', $skill->getLabel());
        self::assertSelectorTextContains('[data-test="account-tags-equipe"]', $teamType->getLabel());
        self::assertSelectorTextContains('[data-test="account-tags-experience"]', 'Aucune');
        self::assertSelectorTextContains('[data-test="account-manager"]', 'Zoé ' . $manager->getLastName());
        self::assertSelectorTextNotContains('[data-test="account-profile"]', $othersTag->getLabel());
        self::assertSelectorNotExists('[data-test="account-profile"] input');
    }

    public function testTheForcedChangeShowsNoProfile(): void
    {
        $client = self::createClient();
        $client->loginUser($this->giveTags($this->createUser(mustChangePassword: true), $this->createTag()));

        $client->request('GET', '/mon-compte/mot-de-passe');

        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[data-test="account-profile"]');
    }

    public function testForcedChangeDoesNotAskForCurrentPassword(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser(mustChangePassword: true));

        $client->request('GET', '/mon-compte/mot-de-passe');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Choisissez votre mot de passe');
        self::assertSelectorNotExists('[data-test="current-password"]');
    }

    public function testForcedChangeRefusesTemporaryPassword(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser(password: 'Temporary password 2026', mustChangePassword: true));

        $this->submitNewPassword($client, 'Temporary password 2026');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="change-password-form"]', 'différent de l\'actuel');
    }

    public function testForcedChangeRefusesShortOrPredictablePassword(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser(mustChangePassword: true));

        $this->submitNewPassword($client, 'court');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="change-password-form"]', 'au moins 12 caractères');

        $this->submitNewPassword($client, 'aaaaaaaaaaaa');
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="change-password-form"]', 'trop prévisible');
    }

    public function testForcedChangeUnlocksApplicationWithoutNewLogin(): void
    {
        $client = self::createClient();
        $user = $this->createUser(mustChangePassword: true);
        $client->loginUser($user);

        $this->submitNewPassword($client, self::STRONG_PASSWORD);

        self::assertResponseRedirects('/saisie');
        self::assertFalse($this->reloadUser($user)->mustChangePassword());
        $client->followRedirect();
        self::assertResponseIsSuccessful();
    }

    public function testVoluntaryChangeRequiresCurrentPassword(): void
    {
        $client = self::createClient();
        $client->loginUser($this->createUser());

        $this->submitNewPassword($client, self::STRONG_PASSWORD, 'wrong password');

        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="change-password-form"]', 'Le mot de passe actuel est incorrect.');
    }

    public function testVoluntaryChangeKeepsUserLoggedIn(): void
    {
        $client = self::createClient();
        $user = $this->createUser();
        $client->loginUser($user);

        $this->submitNewPassword($client, self::STRONG_PASSWORD, 'password');

        self::assertResponseRedirects('/saisie');
        $client->request('GET', '/mon-compte/mot-de-passe');
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Changer mon mot de passe');
    }

    private function submitNewPassword(KernelBrowser $client, string $newPassword, ?string $currentPassword = null): void
    {
        $crawler = $client->request('GET', '/mon-compte/mot-de-passe');
        $values = [
            'change_password[newPassword][first]' => $newPassword,
            'change_password[newPassword][second]' => $newPassword,
        ];
        if (null !== $currentPassword) {
            $values['change_password[currentPassword]'] = $currentPassword;
        }

        $client->submit($crawler->filter('[data-test="change-password-form"]')->form($values));
    }
}
