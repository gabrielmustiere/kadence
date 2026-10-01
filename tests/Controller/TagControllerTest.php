<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\Tag;
use App\Entity\User;
use App\Enum\Type\TagCategory;
use App\Repository\TagRepository;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesTags;
use App\Tests\Support\CreatesUsers;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;

final class TagControllerTest extends WebTestCase
{
    use CreatesTags;
    use CreatesUsers;

    #[DataProvider('nonDirectorProvider')]
    public function testTagManagementIsForbiddenToNonDirectors(string $email): void
    {
        $client = $this->clientAs($email);
        $tag = $this->createTag();

        foreach (['/tags', '/tags/' . $tag->getId() . '/renommer'] as $url) {
            $client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }

        $client->request('POST', '/tags/' . $tag->getId() . '/supprimer');
        self::assertResponseStatusCodeSame(403);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function nonDirectorProvider(): \Generator
    {
        yield 'lead' => ['lead@example.com'];
        yield 'prod' => ['prod@example.com'];
    }

    public function testTheDirectionAddsATagToEachCategory(): void
    {
        $client = $this->clientAs('admin@example.com');

        foreach (TagCategory::cases() as $category) {
            $label = uniqid('Tag ', true);
            $this->submitTag($client, '/tags', ['category' => $category->value, 'label' => '  ' . $label . ' ']);
            self::assertResponseRedirects('/tags');

            $crawler = $client->followRedirect();
            self::assertCount(1, $this->row($crawler, $category, $label), $category->value);
        }
    }

    public function testALabelAlreadyTakenInItsCategoryIsRefusedIgnoringCaseButAllowedInAnother(): void
    {
        $client = $this->clientAs('admin@example.com');
        $tag = $this->createTag(TagCategory::TechnicalSkill, uniqid('Rust ', true));
        $variant = '  ' . mb_strtoupper($tag->getLabel()) . ' ';

        $this->submitTag($client, '/tags', ['category' => 'competence', 'label' => $variant]);
        self::assertResponseStatusCodeSame(422);
        self::assertSelectorTextContains('[data-test="tag-form"]', 'Un tag de cette catégorie porte déjà ce libellé.');

        $this->submitTag($client, '/tags', ['category' => 'experience', 'label' => $variant]);
        self::assertResponseRedirects('/tags');
    }

    public function testRenamingATagChangesItOnEveryPersonCarryingIt(): void
    {
        $client = $this->clientAs('admin@example.com');
        $tag = $this->createTag(TagCategory::FunctionalExperience);
        $other = $this->createTag(TagCategory::FunctionalExperience);
        $holder = $this->giveTags($this->createUser(), $tag);
        $label = uniqid('Paie ', true);

        $this->submitTag($client, '/tags/' . $tag->getId() . '/renommer', ['label' => $other->getLabel()]);
        self::assertResponseStatusCodeSame(422);

        $this->submitTag($client, '/tags/' . $tag->getId() . '/renommer', ['label' => $label]);
        self::assertResponseRedirects('/tags');

        $crawler = $client->request('GET', '/equipe');
        self::assertStringContainsString($label, $crawler->filter(\sprintf('[data-email="%s"] [data-test="member-tags"]', $holder->getEmail()))->text());
    }

    public function testDeletingATagAnnouncesHowManyPeopleCarryItThenRemovesItFromTheirProfile(): void
    {
        $client = $this->clientAs('admin@example.com');
        $tag = $this->createTag(TagCategory::TeamType);
        $first = $this->giveTags($this->createUser(), $tag);
        $second = $this->giveTags($this->createUser()->setActive(false), $tag);

        $crawler = $client->request('GET', '/tags');
        $row = $this->row($crawler, TagCategory::TeamType, $tag->getLabel());
        self::assertSame('2', $row->filter('[data-test="tag-holders"]')->attr('data-count'));
        self::assertStringContainsString('Il est porté par 2 personnes', $row->filter('[data-test="tag-delete-holders"]')->text());

        $client->submit($row->filter('[data-test="tag-delete-confirm"]')->form());
        self::assertResponseRedirects('/tags');

        self::assertNull($this->reloadUser($first)->teamType());
        self::assertNull($this->reloadUser($second)->teamType());
        self::assertNull($this->tagRepository()->find((int) $tag->getId()));
    }

    public function testDeletingWithAnInvalidCsrfTokenIsRefused(): void
    {
        $client = $this->clientAs('admin@example.com');
        $tag = $this->createTag();

        $client->request('POST', '/tags/' . $tag->getId() . '/supprimer', ['_token' => 'invalid']);

        self::assertResponseStatusCodeSame(403);
        self::assertInstanceOf(Tag::class, $this->tagRepository()->find((int) $tag->getId()));
    }

    /**
     * @param array<string, string> $values
     */
    private function submitTag(KernelBrowser $client, string $url, array $values): void
    {
        $crawler = $client->request('GET', $url);
        $fields = [];
        foreach ($values as $field => $value) {
            $fields['tag[' . $field . ']'] = $value;
        }

        $client->submit($crawler->filter('[data-test="tag-form"]')->form($fields));
    }

    private function row(Crawler $crawler, TagCategory $category, string $label): Crawler
    {
        return $crawler->filter(\sprintf('[data-test="tag-category"][data-category="%s"] [data-test="tag-row"]', $category->value))
            ->reduce(static fn (Crawler $row): bool => $row->attr('data-label') === $label);
    }

    private function clientAs(string $email): KernelBrowser
    {
        $client = self::createClient();
        $userRepository = self::getContainer()->get(UserRepository::class);
        \assert($userRepository instanceof UserRepository);
        $user = $userRepository->findOneByEmail($email);
        self::assertInstanceOf(User::class, $user);
        $client->loginUser($user);

        return $client;
    }

    private function tagRepository(): TagRepository
    {
        $entityManager = $this->entityManager();
        $entityManager->clear();
        $repository = self::getContainer()->get(TagRepository::class);
        \assert($repository instanceof TagRepository);

        return $repository;
    }
}
