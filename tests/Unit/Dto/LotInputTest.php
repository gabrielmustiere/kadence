<?php

declare(strict_types=1);

namespace App\Tests\Unit\Dto;

use App\Dto\LotInput;
use App\Dto\LotMemberInput;
use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

final class LotInputTest extends TestCase
{
    public function testOwnerMissingFromTheTeamIsAddedAtFullShare(): void
    {
        $owner = $this->user();
        $member = $this->user();
        $input = new LotInput();
        $input->owner = $owner;
        $input->members = [LotMemberInput::of($member, 50)];

        self::assertSame([[$member, 50], [$owner, 100]], $input->effectiveMembers());
    }

    public function testOwnerAlreadyInTheTeamKeepsTheirShare(): void
    {
        $owner = $this->user();
        $input = new LotInput();
        $input->owner = $owner;
        $input->members = [LotMemberInput::of($owner, 25)];

        self::assertSame([[$owner, 25]], $input->effectiveMembers());
    }

    public function testOwnerWhoseRowIsRemovedComesBackWithTheShareTheyHad(): void
    {
        $owner = $this->user();
        $lot = $this->lot()->setOwner($owner);
        new LotMember($lot, $owner, 50);
        $input = LotInput::fromLot($lot);
        $input->members = [];

        self::assertSame([[$owner, 50]], $input->effectiveMembers());
    }

    public function testPlanningChangesWithTheStartDateOrTheTeamOnly(): void
    {
        $owner = $this->user();
        $lot = $this->lot()->setOwner($owner)->setStartDate(new \DateTimeImmutable('2026-10-05'));
        new LotMember($lot, $owner, 100);

        $unchanged = LotInput::fromLot($lot);
        $unchanged->title = 'Renommé';
        $unchanged->estimateDays = 12;
        $moved = LotInput::fromLot($lot);
        $moved->startDate = new \DateTimeImmutable('2026-10-12');
        $reshared = LotInput::fromLot($lot);
        $reshared->members[0]->share = 50;
        $joined = LotInput::fromLot($lot);
        $joined->members[] = LotMemberInput::of($this->user(), 25);

        self::assertFalse($unchanged->planningChanged());
        self::assertTrue($moved->planningChanged());
        self::assertTrue($reshared->planningChanged());
        self::assertTrue($joined->planningChanged());
        self::assertFalse(new LotInput()->planningChanged());
    }

    public function testRowsWithoutAPersonOrWithAnInvalidShareAreLeftOut(): void
    {
        $member = $this->user();
        $invalid = LotMemberInput::of($this->user(), 25);
        $invalid->share = 30;
        $input = new LotInput();
        $input->members = [new LotMemberInput(), $invalid, LotMemberInput::of($member, 75)];

        self::assertSame([[$member, 75]], $input->effectiveMembers());
    }

    public function testEditingALeafStartsFromItsStartDateAndTeam(): void
    {
        $member = $this->user();
        $lot = $this->lot()->setStartDate(new \DateTimeImmutable('2026-10-05'));
        new LotMember($lot, $member, 75);

        $input = LotInput::fromLot($lot);

        self::assertSame('2026-10-05', $input->startDate?->format('Y-m-d'));
        self::assertCount(1, $input->members);
        self::assertSame($member, $input->members[0]->user);
        self::assertSame(75, $input->members[0]->share);
    }

    public function testFirstSubLotTakesOverThePlanningOfItsLotButNextOnesDoNot(): void
    {
        $lot = $this->lot()->setStartDate(new \DateTimeImmutable('2026-10-05'));
        new LotMember($lot, $this->user(), 100);

        $first = LotInput::forSubLotOf($lot);
        new Lot($lot->getProject(), $lot);
        $next = LotInput::forSubLotOf($lot);

        self::assertSame('2026-10-05', $first->startDate?->format('Y-m-d'));
        self::assertCount(1, $first->members);
        self::assertNull($next->startDate);
        self::assertSame([], $next->members);
    }

    private function lot(): Lot
    {
        return new Lot(new Project()->setTitle('Kadence'))->setTitle('Lot');
    }

    private function user(): User
    {
        return new User()->setFirstName('Paula')->setLastName('Durand')->setEmail('paula@example.com');
    }
}
