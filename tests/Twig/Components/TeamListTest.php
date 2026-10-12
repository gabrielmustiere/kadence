<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Repository\UserRepository;
use App\Tests\Support\CreatesTags;
use App\Tests\Support\CreatesUsers;
use App\Tests\Support\ReadsInPlaceFlashes;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class TeamListTest extends KernelTestCase
{
    use CreatesTags;
    use CreatesUsers;
    use InteractsWithLiveComponents;
    use ReadsInPlaceFlashes;

    public function testFiltersApplyAsSoonAsTheyChangeAndClearAtOnce(): void
    {
        $skill = $this->createTag(TagCategory::TechnicalSkill);
        $experience = $this->createTag(TagCategory::FunctionalExperience);
        $manager = $this->createUser();
        $both = $this->giveTags($this->createUser()->setManager($manager), $skill, $experience);
        $skillOnly = $this->giveTags($this->createUser(), $skill);
        $none = $this->createUser();
        $among = [$both, $skillOnly, $none];
        $component = $this->component();

        self::assertSame([$both, $skillOnly], $this->listedAmong($component->set('competence', $skill->getId()), $among));
        self::assertSame([$both], $this->listedAmong($component->set('experience', $experience->getId()), $among));
        self::assertCount(1, $component->render()->crawler()->filter('[data-test="team-filter-reset"]'));

        self::assertSame($among, $this->listedAmong($component->call('clear'), $among));
        self::assertCount(0, $component->render()->crawler()->filter('[data-test="team-filter-reset"]'));
        self::assertSame([$both], $this->listedAmong($component->set('manager', $manager->getId()), $among));
    }

    public function testAnIdThatNamesNoTagOfItsCategoryFiltersNothing(): void
    {
        $experience = $this->createTag(TagCategory::FunctionalExperience);
        $person = $this->createUser();

        $component = $this->component()->set('competence', $experience->getId());

        self::assertSame([$person], $this->listedAmong($component, [$person]));
    }

    public function testDeactivatingThenReactivatingHappensInPlaceAndKeepsTheFilters(): void
    {
        $skill = $this->createTag(TagCategory::TechnicalSkill);
        $member = $this->giveTags($this->createUser(), $skill);
        $component = $this->component()->set('competence', $skill->getId());

        $rendered = $component->call('deactivate', ['member' => $member->getId()])->render();

        self::assertFalse($this->reloadUser($member)->isActive());
        self::assertSame(['type' => 'success', 'message' => \sprintf('Le compte de %s %s est désactivé.', $member->getFirstName(), $member->getLastName())], self::flashOf($component, $rendered));
        self::assertCount(1, $this->row($rendered->crawler(), $member)->filter('[data-test="member-reactivate"]'));
        self::assertSame((string) $skill->getId(), $rendered->crawler()->filter('[data-test="team-filter-competence"] option[selected]')->attr('value'), 'The filter stays.');

        $component->call('reactivate', ['member' => $member->getId()]);

        $member = $this->reloadUser($member);
        self::assertTrue($member->isActive());
        $hasher = self::getContainer()->get(UserPasswordHasherInterface::class);
        \assert($hasher instanceof UserPasswordHasherInterface);
        self::assertTrue($hasher->isPasswordValid($member, 'password'));
    }

    public function testTheLastActiveDirectorCannotBeDeactivated(): void
    {
        $director = $this->createUser(Role::Direction);
        $otherDirectors = array_values(array_filter($this->users()->findBy(['role' => Role::Direction, 'active' => true]), static fn (User $user): bool => $user !== $director));
        $this->setActive($otherDirectors, false);

        try {
            $component = $this->component($director);
            $rendered = $component->call('deactivate', ['member' => $director->getId()])->render();

            self::assertStringContainsString('au moins un membre de la direction actif', self::flashOf($component, $rendered)['message'] ?? '');
            self::assertTrue($this->reloadUser($director)->isActive());
        } finally {
            $this->setActive($otherDirectors, true);
        }
    }

    public function testDeactivatingSomeoneWhoManagesActivePeopleIsRefusedNamingThem(): void
    {
        $manager = $this->createUser();
        $report = $this->createUser()->setFirstName('Zoé')->setLastName(uniqid('Rattachée', false))->setManager($manager);
        $this->entityManager()->flush();
        $component = $this->component();

        $rendered = $component->call('deactivate', ['member' => $manager->getId()])->render();

        $flash = self::flashOf($component, $rendered);
        self::assertNotNull($flash);
        self::assertSame('error', $flash['type']);
        self::assertStringContainsString('Zoé ' . $report->getLastName(), $flash['message']);
        self::assertTrue($this->reloadUser($manager)->isActive());
    }

    public function testDeactivatingAManagerOfDeactivatedPeopleOnlyClearsTheirManager(): void
    {
        $manager = $this->createUser();
        $former = $this->createUser()->setManager($manager)->setActive(false);
        $this->entityManager()->flush();

        $this->component()->call('deactivate', ['member' => $manager->getId()]);

        self::assertFalse($this->reloadUser($manager)->isActive());
        self::assertNull($this->reloadUser($former)->getManager());
    }

    public function testOnlyTheDirectionManagesTheTeam(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createLiveComponent('TeamList')->actingAs($this->createUser(Role::Lead))->render();
    }

    private function component(?User $director = null): TestLiveComponent
    {
        return $this->createLiveComponent('TeamList')->actingAs($director ?? $this->createUser(Role::Direction));
    }

    /**
     * @param list<User> $among
     *
     * @return list<User> the people among these that the list shows
     */
    private function listedAmong(TestLiveComponent $component, array $among): array
    {
        $listed = $component->render()->crawler()->filter('[data-test="team-member-row"]')->each(static fn (Crawler $row): string => (string) $row->attr('data-email'));

        return array_values(array_filter($among, static fn (User $user): bool => \in_array($user->getEmail(), $listed, true)));
    }

    private function row(Crawler $crawler, User $member): Crawler
    {
        return $crawler->filter(\sprintf('[data-test="team-member-row"][data-email="%s"]', $member->getEmail()));
    }

    /**
     * @param list<User> $users
     */
    private function setActive(array $users, bool $active): void
    {
        $entityManager = $this->entityManager();
        foreach ($users as $user) {
            $entityManager->find(User::class, $user->getId())?->setActive($active);
        }
        $entityManager->flush();
    }

    private function users(): UserRepository
    {
        $repository = self::getContainer()->get(UserRepository::class);
        \assert($repository instanceof UserRepository);

        return $repository;
    }
}
