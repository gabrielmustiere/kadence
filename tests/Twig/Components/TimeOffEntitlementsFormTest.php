<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Model\TimeOff\TimeOffPeriod;
use App\Repository\TimeOffEntitlementRepository;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use App\Tests\Support\ReadsInPlaceFlashes;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class TimeOffEntitlementsFormTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesTimeOff;
    use CreatesUsers;
    use InteractsWithLiveComponents;
    use ReadsInPlaceFlashes;

    protected function setUp(): void
    {
        self::mockTime('2026-10-12 10:00');
    }

    public function testTheBalanceFollowsTheEntitlementsTypedBeforeTheyAreSaved(): void
    {
        $person = $this->createUser();
        $this->createEntitlement($person, TimeOffType::Rtt, 2026, 20);
        $this->createTimeOff($person, '2026-11-16', '2026-11-17', TimeOffStatus::Approved, TimeOffType::Rtt);
        $component = $this->component();
        self::assertSame('8 j', $this->remainingOf($component, $person));
        self::assertCount(0, $component->render()->crawler()->filter('[data-test="entitlements-dirty"]'));

        $component->set($this->field($person), '12.5');

        self::assertSame('10,5 j', $this->remainingOf($component, $person));
        self::assertCount(1, $component->render()->crawler()->filter('[data-test="entitlements-dirty"]'));
        self::assertSame(20, $this->savedHalfDaysOf($person), 'Nothing is saved while typing.');
    }

    public function testSavingDeclaresTheEntitlementsInPlaceAndSaysSo(): void
    {
        $person = $this->createUser();
        $component = $this->component()->set($this->field($person), '10.5');

        $rendered = $component->call('save')->render();

        self::assertSame(21, $this->savedHalfDaysOf($person));
        self::assertSame(['type' => 'success', 'message' => 'Les droits RTT 2026 sont enregistrés.'], self::flashOf($component, $rendered));
        self::assertCount(0, $rendered->crawler()->filter('[data-test="entitlements-dirty"]'));
        self::assertSame('RTT 2026', $rendered->crawler()->filter('[data-test="entitlements-period"]')->text());
    }

    public function testEntitlementsOffTheHalfDayAreRefusedAndNothingIsSaved(): void
    {
        $person = $this->createUser();
        $component = $this->component()->set($this->field($person), '10.3');

        try {
            $component->call('save');
            self::fail('Entitlements off the half-day are not saved.');
        } catch (UnprocessableEntityHttpException $exception) {
            self::assertStringContainsString('Les droits se déclarent au demi-jour près.', $exception->getMessage());
            self::assertNull($this->savedHalfDaysOf($person));
        }
    }

    public function testOnlyTheDirectionDeclaresEntitlements(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->createLiveComponent('TimeOffEntitlementsForm', ['type' => 'rtt', 'year' => 2026])->actingAs($this->createUser(Role::Lead))->render();
    }

    private function component(): TestLiveComponent
    {
        return $this->createLiveComponent('TimeOffEntitlementsForm', ['type' => 'rtt', 'year' => 2026])->actingAs($this->createUser(Role::Direction));
    }

    private function field(User $person): string
    {
        return \sprintf('time_off_entitlements.days.%d', $person->getId());
    }

    private function remainingOf(TestLiveComponent $component, User $person): string
    {
        return $component->render()->crawler()->filter(\sprintf('[data-test="entitlements-row"][data-person="%d"] [data-test="entitlements-remaining"]', $person->getId()))->text();
    }

    private function savedHalfDaysOf(User $person): ?int
    {
        $repository = self::getContainer()->get(TimeOffEntitlementRepository::class);
        \assert($repository instanceof TimeOffEntitlementRepository);

        return $repository->findOneFor($person, TimeOffPeriod::of(TimeOffType::Rtt, 2026))?->getHalfDays();
    }
}
