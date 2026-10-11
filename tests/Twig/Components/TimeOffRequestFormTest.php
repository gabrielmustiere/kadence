<?php

declare(strict_types=1);

namespace App\Tests\Twig\Components;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Repository\TimeOffRequestRepository;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\UX\LiveComponent\Test\InteractsWithLiveComponents;
use Symfony\UX\LiveComponent\Test\TestLiveComponent;

final class TimeOffRequestFormTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesTimeOff;
    use CreatesUsers;
    use InteractsWithLiveComponents;

    protected function setUp(): void
    {
        self::mockTime('2026-10-12 10:00');
    }

    public function testThePreviewFollowsTheDatesWithTheBalanceLeft(): void
    {
        $user = $this->createUser();
        $this->createEntitlement($user, TimeOffType::PaidLeave, 2026, 50);
        $this->createTimeOff($user, '2026-12-21', '2026-12-31');
        $component = $this->component($user);
        self::assertStringContainsString('Choisissez vos dates', $component->render()->crawler()->filter('[data-test="time-off-preview"]')->text());

        $component->set('time_off_request.startDay', '2026-11-09')->set('time_off_request.endDay', '2026-11-13');
        $crawler = $component->render()->crawler();

        self::assertSame('4 j', $crawler->filter('[data-test="time-off-preview-days"]')->text());
        self::assertSame('13 j', $crawler->filter('[data-test="time-off-preview-remaining"]')->text(), '25 j, less 8 j approved around Christmas and the 4 j asked.');
        self::assertCount(0, $crawler->filter('[data-test="time-off-preview-overrun"]'));
    }

    public function testThePreviewWarnsOfAnOverrun(): void
    {
        $component = $this->component($this->createUser());

        $component->set('time_off_request.startDay', '2026-11-16')->set('time_off_request.endDay', '2026-11-16');

        self::assertSame('-1 j', $component->render()->crawler()->filter('[data-test="time-off-preview-remaining"]')->text());
        self::assertCount(1, $component->render()->crawler()->filter('[data-test="time-off-preview-overrun"]'));
    }

    public function testSendingRecordsAPendingRequest(): void
    {
        $manager = $this->createUser(Role::Lead);
        $user = $this->createUser()->setManager($manager);
        $this->entityManager()->flush();

        $response = $this->component($user)->submitForm(['time_off_request' => [
            'type' => 'rtt',
            'startDay' => '2026-11-16',
            'startHalf' => 'afternoon',
            'endDay' => '2026-11-17',
            'endHalf' => 'afternoon',
            'comment' => 'Pont',
        ]], 'save')->response();

        self::assertSame(302, $response->getStatusCode());
        $requests = $this->requests($user);
        self::assertCount(1, $requests);
        self::assertSame(TimeOffType::Rtt, $requests[0]->getType());
        self::assertSame(TimeOffStatus::Pending, $requests[0]->getStatus());
        self::assertSame('Pont', $requests[0]->getComment());
    }

    public function testAnOverlappingRequestIsRefusedAndRecordsNothing(): void
    {
        $user = $this->createUser();
        $this->createTimeOff($user, '2026-11-16', '2026-11-16', TimeOffStatus::Pending);

        try {
            $this->component($user)->submitForm(['time_off_request' => [
                'type' => 'cp',
                'startDay' => '2026-11-16',
                'startHalf' => 'morning',
                'endDay' => '2026-11-17',
                'endHalf' => 'afternoon',
            ]], 'save');
            self::fail('An overlapping request is not sent.');
        } catch (UnprocessableEntityHttpException) {
            self::assertCount(1, $this->requests($user));
        }
    }

    private function component(User $user): TestLiveComponent
    {
        return $this->createLiveComponent('TimeOffRequestForm')->actingAs($user);
    }

    /**
     * @return list<TimeOffRequest>
     */
    private function requests(User $user): array
    {
        $repository = self::getContainer()->get(TimeOffRequestRepository::class);
        \assert($repository instanceof TimeOffRequestRepository);

        return $repository->findOfUser($user);
    }
}
