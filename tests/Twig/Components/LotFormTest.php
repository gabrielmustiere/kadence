<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Enum\Type\Role;
use App\Enum\Type\TagCategory;
use App\Tests\Support\CreatesProjects;
use App\Tests\Support\CreatesTags;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class LotFormTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesProjects;
    use CreatesTags;
    use CreatesUsers;
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        self::mockTime('2026-10-02 10:00');
    }

    public function testAnAddedRowGivesAFullShareAndOffersOnlyActivePeople(): void
    {
        $former = $this->createUser();
        $this->planLot($this->createLot($this->createProject(), 5), new \DateTimeImmutable('2026-10-05'), [[$former, 100]]);
        $former->setActive(false);
        $this->entityManager()->flush();
        $active = $this->createUser();
        $lot = $this->createLot($this->createProject(), 5);
        $component = $this->component(['lot' => $lot]);
        self::assertCount(0, $this->rows($component->render()->crawler()));

        $rows = $this->rows($component->call('addMember')->render()->crawler());

        self::assertCount(1, $rows);
        self::assertSame('100', $rows->filter('[data-test="lot-member-share"] option[selected]')->attr('value'));
        self::assertSame(
            ['10 %', '20 %', '30 %', '40 %', '50 %', '60 %', '70 %', '80 %', '90 %', '100 %'],
            $rows->filter('[data-test="lot-member-share"] option')->each(static fn (Crawler $option): string => $option->text()),
        );
        self::assertCount(1, $rows->filter(\sprintf('[data-test="lot-member-user"] option[value="%d"]', $active->getId())));
        self::assertCount(0, $rows->filter(\sprintf('[data-test="lot-member-user"] option[value="%d"]', $former->getId())));

        self::assertCount(0, $this->rows($component->call('removeMember', ['index' => 0])->render()->crawler()));
    }

    public function testTheTagFilterOffersThePeopleCarryingEveryTagAndKeepsTheOneChosen(): void
    {
        $skill = $this->createTag(TagCategory::TechnicalSkill);
        $experience = $this->createTag(TagCategory::FunctionalExperience);
        $both = $this->giveTags($this->createUser(), $skill, $experience);
        $skillOnly = $this->giveTags($this->createUser(), $skill);
        $chosen = $this->createUser();
        $lot = $this->planLot($this->createLot($this->createProject(), 5), new \DateTimeImmutable('2026-10-05'), [[$chosen, 100]]);
        $component = $this->component(['lot' => $lot]);

        $component->set('tagFilter.competence', (string) $skill->getId())->set('tagFilter.experience', (string) $experience->getId());

        $offered = $this->rows($component->render()->crawler())->filter('[data-test="lot-member-user"] option:not([value=""])')->each(static fn (Crawler $option): string => (string) $option->attr('value'));
        self::assertEqualsCanonicalizing([(string) $both->getId(), (string) $chosen->getId()], $offered);
        self::assertNotContains((string) $skillOnly->getId(), $offered);

        $component->set('tagFilter.experience', '');
        self::assertCount(1, $this->rows($component->render()->crawler())->filter(\sprintf('option[value="%d"]', $skillOnly->getId())));
    }

    public function testTheTeamShowsTheOwnerItWillHave(): void
    {
        $owner = $this->createUser()->setFirstName('Olga');
        $member = $this->createUser()->setFirstName('Marc');
        $lot = $this->planLot($this->createLot($this->createProject(), 5, $owner), new \DateTimeImmutable('2026-10-05'), [[$member, 50]]);

        $team = $this->component(['lot' => $lot])->render()->crawler()->filter('[data-test="lot-team"]')->text();

        self::assertStringContainsString('Marc User · 50 %', $team);
        self::assertStringContainsString('Olga User (responsable) · 100 %', $team);
    }

    public function testAPersonTwiceInTheTeamIsSaidAsSoonAsARowIsTouched(): void
    {
        $person = $this->createUser();
        $lot = $this->planLot($this->createLot($this->createProject(), 5), new \DateTimeImmutable('2026-10-05'), [[$person, 100]]);
        $component = $this->component(['lot' => $lot])->call('addMember');

        try {
            $component->set('lot.members.1.user', (string) $person->getId())->set('validatedFields', ['lot.members.1.user'])->render();
            self::fail('A person twice in the team is refused.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertStringContainsString('Une personne ne figure qu\'une fois dans l\'équipe.', $exception->getMessage());
        }
    }

    public function testOnlyThoseWhoMayEditTheLotOpenItsForm(): void
    {
        $lot = $this->createLot($this->createProject(), 5);

        $this->expectException(AccessDeniedException::class);
        $this->createLiveComponent('LotForm', ['lot' => $lot])->actingAs($this->createUser())->render();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function component(array $data): TestLiveComponent
    {
        return $this->createLiveComponent('LotForm', $data)->actingAs($this->createUser(Role::Lead));
    }

    private function rows(Crawler $crawler): Crawler
    {
        return $crawler->filter('[data-test="lot-member-row"]');
    }
}
