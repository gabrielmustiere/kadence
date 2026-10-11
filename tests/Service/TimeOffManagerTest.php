<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\Dto\TimeOffRequestInput;
use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use App\Exception\TimeOffRefusedException;
use App\Service\TimeOffManager;
use App\Tests\Support\CreatesTimeOff;
use App\Tests\Support\CreatesUsers;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Clock\Test\ClockSensitiveTrait;
use Symfony\Component\Mime\Email;

final class TimeOffManagerTest extends KernelTestCase
{
    use ClockSensitiveTrait;
    use CreatesTimeOff;
    use CreatesUsers;

    protected function setUp(): void
    {
        self::mockTime('2026-10-12 10:00');
    }

    public function testARequestIsPendingAndSentToTheManager(): void
    {
        $manager = $this->createUser(Role::Lead);
        $requester = $this->createUser()->setManager($manager);

        $request = $this->manager()->request($this->input($requester, '2026-11-16', '2026-11-20', ' Vacances '));

        self::assertSame(TimeOffStatus::Pending, $request->getStatus());
        self::assertSame('Vacances', $request->getComment());
        $emails = $this->sentEmails();
        self::assertCount(1, $emails);
        self::assertSame($manager->getEmail(), $emails[0]->getTo()[0]->getAddress());
        self::assertSame('[Kadence] Demande de congé de Test User', $emails[0]->getSubject());
        self::assertStringContainsString('5 j', (string) $emails[0]->getHtmlBody());
    }

    public function testApprovingTellsThePersonOnly(): void
    {
        $manager = $this->createUser(Role::Lead);
        $requester = $this->createUser()->setManager($manager);
        $request = $this->createTimeOff($requester, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);

        $this->manager()->approve($request, $manager);

        self::assertSame(TimeOffStatus::Approved, $request->getStatus());
        self::assertSame($manager, $request->getDecidedBy());
        self::assertSame([$requester->getEmail()], $this->recipients());
    }

    public function testTheDirectionDecidingInsteadOfTheManagerTellsThemToo(): void
    {
        $manager = $this->createUser(Role::Lead);
        $requester = $this->createUser()->setManager($manager);
        $request = $this->createTimeOff($requester, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);

        $this->manager()->refuse($request, $this->createUser(Role::Direction), 'Mise en production ce jour-là');

        self::assertSame(TimeOffStatus::Refused, $request->getStatus());
        self::assertSame('Mise en production ce jour-là', $request->getRefusalReason());
        self::assertSame([$requester->getEmail(), $manager->getEmail()], $this->recipients());
        self::assertStringContainsString('Mise en production ce jour-là', (string) $this->sentEmails()[0]->getHtmlBody());
    }

    public function testRefusingWithoutReasonIsRefused(): void
    {
        $manager = $this->createUser(Role::Lead);
        $request = $this->createTimeOff($this->createUser()->setManager($manager), '2026-11-16', '2026-11-20', TimeOffStatus::Pending);

        try {
            $this->manager()->refuse($request, $manager, '  ');
            self::fail('A refusal without reason is refused.');
        } catch (TimeOffRefusedException) {
            self::assertSame(TimeOffStatus::Pending, $request->getStatus());
        }
    }

    public function testARequestDecidedAlreadyCannotBeDecidedAgain(): void
    {
        $manager = $this->createUser(Role::Lead);
        $request = $this->createTimeOff($this->createUser()->setManager($manager), '2026-11-16', '2026-11-20');

        $this->expectException(TimeOffRefusedException::class);

        $this->manager()->refuse($request, $manager, 'Trop tard');
    }

    public function testCancellingApprovedTimeOffBeforeItStartsTellsTheManager(): void
    {
        $manager = $this->createUser(Role::Lead);
        $requester = $this->createUser()->setManager($manager);
        $request = $this->createTimeOff($requester, '2026-10-13', '2026-10-16');

        $this->manager()->cancel($request, $requester);

        self::assertSame(TimeOffStatus::Cancelled, $request->getStatus());
        self::assertSame([$manager->getEmail()], $this->recipients());
    }

    public function testThePersonCannotCancelTimeOffThatHasStarted(): void
    {
        $requester = $this->createUser()->setManager($this->createUser(Role::Lead));
        $request = $this->createTimeOff($requester, '2026-10-12', '2026-10-16');

        $this->expectException(TimeOffRefusedException::class);

        $this->manager()->cancel($request, $requester);
    }

    public function testTheDirectionCancellingTimeOffThatHasStartedTellsThePerson(): void
    {
        $requester = $this->createUser()->setManager($this->createUser(Role::Lead));
        $request = $this->createTimeOff($requester, '2026-10-05', '2026-10-16');

        $this->manager()->cancel($request, $this->createUser(Role::Direction));

        self::assertSame(TimeOffStatus::Cancelled, $request->getStatus());
        self::assertSame([$requester->getEmail()], $this->recipients());
    }

    public function testTheDirectionCancelsItsOwnTimeOffThatHasStarted(): void
    {
        $director = $this->createUser(Role::Direction);
        $request = $this->createTimeOff($director, '2026-10-05', '2026-10-16');

        $this->manager()->cancel($request, $director);

        self::assertSame(TimeOffStatus::Cancelled, $request->getStatus());
    }

    public function testCancellingAPendingRequestTellsNobody(): void
    {
        $requester = $this->createUser()->setManager($this->createUser(Role::Lead));
        $request = $this->createTimeOff($requester, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);

        $this->manager()->cancel($request, $requester);

        self::assertSame(TimeOffStatus::Cancelled, $request->getStatus());
        self::assertSame([], $this->sentEmails());
    }

    public function testTheDeactivationOfAPersonCancelsTheirPendingRequestsOnly(): void
    {
        $user = $this->createUser();
        $pending = $this->createTimeOff($user, '2026-11-16', '2026-11-20', TimeOffStatus::Pending);
        $approved = $this->createTimeOff($user, '2026-12-21', '2026-12-24');

        $this->manager()->cancelPendingOf($user);
        $this->entityManager()->flush();

        self::assertSame(TimeOffStatus::Cancelled, $pending->getStatus());
        self::assertSame(TimeOffStatus::Approved, $approved->getStatus());
        self::assertSame([], $this->sentEmails());
    }

    private function input(User $requester, string $start, string $end, ?string $comment = null): TimeOffRequestInput
    {
        $input = new TimeOffRequestInput($requester);
        $input->type = TimeOffType::PaidLeave;
        $input->startDay = new \DateTimeImmutable($start);
        $input->startHalf = HalfDay::Morning;
        $input->endDay = new \DateTimeImmutable($end);
        $input->endHalf = HalfDay::Afternoon;
        $input->comment = $comment;

        return $input;
    }

    /**
     * @return list<Email>
     */
    private function sentEmails(): array
    {
        $emails = [];
        foreach (self::getMailerEvents() as $event) {
            $message = $event->getMessage();
            if (!$event->isQueued() && $message instanceof Email) {
                $emails[] = $message;
            }
        }

        return $emails;
    }

    /**
     * @return list<string>
     */
    private function recipients(): array
    {
        return array_map(static fn (Email $email): string => $email->getTo()[0]->getAddress(), $this->sentEmails());
    }

    private function manager(): TimeOffManager
    {
        $manager = static::getContainer()->get(TimeOffManager::class);
        \assert($manager instanceof TimeOffManager);

        return $manager;
    }
}
