<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Dto\TimeOffRequestInput;
use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\TimeEntry;
use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffType;
use App\Repository\HolidayAdjustmentRepository;
use App\Repository\TimeEntryRepository;
use App\Repository\TimeOffEntitlementRepository;
use App\Repository\TimeOffRequestRepository;
use App\Service\HolidayManager;
use App\Service\LegalHolidays;
use App\Service\TimeOffBook;
use App\Validator\RequestableTimeOff;
use App\Validator\RequestableTimeOffValidator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Validator\Test\ConstraintValidatorTestCase;

/**
 * @extends ConstraintValidatorTestCase<RequestableTimeOffValidator>
 */
final class RequestableTimeOffValidatorTest extends ConstraintValidatorTestCase
{
    private User $user;

    /** @var list<TimeOffRequest> */
    private array $requests = [];

    /** @var list<TimeEntry> */
    private array $entries = [];

    protected function setUp(): void
    {
        $this->user = new User()->setFirstName('Paula')->setLastName('Durand')->setEmail('paula@example.com');

        parent::setUp();
    }

    public function testAnEndBeforeTheStartIsRefused(): void
    {
        $this->validator->validate($this->input('2026-11-18', HalfDay::Afternoon, '2026-11-18', HalfDay::Morning), new RequestableTimeOff());

        $this->buildViolation('La fin du congé précède son début.')->atPath('property.path.endDay')->assertRaised();
    }

    public function testDaysWithoutWorkingDayAreRefused(): void
    {
        $this->validator->validate($this->input('2026-11-14', HalfDay::Morning, '2026-11-15', HalfDay::Afternoon), new RequestableTimeOff());

        $this->buildViolation('Ces dates ne couvrent aucun jour ouvré : week-ends et jours fériés ne se posent pas.')->atPath('property.path.startDay')->assertRaised();
    }

    public function testAHolidayAloneIsRefused(): void
    {
        $this->validator->validate($this->input('2026-11-11', HalfDay::Morning, '2026-11-11', HalfDay::Afternoon), new RequestableTimeOff());

        $this->buildViolation('Ces dates ne couvrent aucun jour ouvré : week-ends et jours fériés ne se posent pas.')->atPath('property.path.startDay')->assertRaised();
    }

    public function testAHalfDayAlreadyTakenIsRefused(): void
    {
        $this->requests[] = $this->request('2026-11-18', HalfDay::Morning, '2026-11-18', HalfDay::Morning);

        $this->validator->validate($this->input('2026-11-16', HalfDay::Morning, '2026-11-18', HalfDay::Afternoon), new RequestableTimeOff());

        $this->buildViolation('Vous avez déjà une demande en attente ou validée le {{ day }} ({{ half }}).')
            ->setParameters(['{{ day }}' => '18/11', '{{ half }}' => 'matin'])
            ->atPath('property.path.startDay')
            ->assertRaised();
    }

    public function testTheOtherHalfOfADayTakenIsAccepted(): void
    {
        $this->requests[] = $this->request('2026-11-18', HalfDay::Morning, '2026-11-18', HalfDay::Morning);

        $this->validator->validate($this->input('2026-11-18', HalfDay::Afternoon, '2026-11-18', HalfDay::Afternoon), new RequestableTimeOff());

        $this->assertNoViolation();
    }

    public function testAWholeDayWithTimeEnteredIsRefused(): void
    {
        $this->entries[] = $this->entry('2026-11-18', 4);

        $this->validator->validate($this->input('2026-11-18', HalfDay::Morning, '2026-11-18', HalfDay::Afternoon), new RequestableTimeOff());

        $this->buildViolation('Vous avez saisi {{ entered }} le {{ day }} : retirez ce temps de votre semaine avant de poser ce congé.')
            ->setParameters(['{{ entered }}' => '1 j', '{{ day }}' => '18/11'])
            ->atPath('property.path.startDay')
            ->assertRaised();
    }

    public function testAHalfDayLeavesRoomForHalfADayEntered(): void
    {
        $this->entries[] = $this->entry('2026-11-18', 2);

        $this->validator->validate($this->input('2026-11-18', HalfDay::Afternoon, '2026-11-18', HalfDay::Afternoon), new RequestableTimeOff());

        $this->assertNoViolation();
    }

    public function testAHalfDayIsRefusedOverMoreThanHalfADayEntered(): void
    {
        $this->entries[] = $this->entry('2026-11-18', 3);

        $this->validator->validate($this->input('2026-11-18', HalfDay::Afternoon, '2026-11-18', HalfDay::Afternoon), new RequestableTimeOff());

        $this->buildViolation('Vous avez saisi {{ entered }} le {{ day }} : retirez ce temps de votre semaine avant de poser ce congé.')
            ->setParameters(['{{ entered }}' => '0,75 j', '{{ day }}' => '18/11'])
            ->atPath('property.path.startDay')
            ->assertRaised();
    }

    public function testPastDaysWithoutTimeAreAccepted(): void
    {
        $this->validator->validate($this->input('2026-09-07', HalfDay::Morning, '2026-09-11', HalfDay::Afternoon), new RequestableTimeOff());

        $this->assertNoViolation();
    }

    protected function createValidator(): RequestableTimeOffValidator
    {
        $timeOffRequestRepository = $this->createStub(TimeOffRequestRepository::class);
        $timeOffRequestRepository->method('findActiveOfUserBetween')->willReturnCallback(fn (): array => $this->requests);
        $timeEntryRepository = $this->createStub(TimeEntryRepository::class);
        $timeEntryRepository->method('findForUserBetween')->willReturnCallback(fn (): array => $this->entries);
        $holidayManager = new HolidayManager($this->createStub(EntityManagerInterface::class), $this->createStub(HolidayAdjustmentRepository::class), new LegalHolidays());

        return new RequestableTimeOffValidator(new TimeOffBook($timeOffRequestRepository, $this->createStub(TimeOffEntitlementRepository::class), $holidayManager), $timeEntryRepository);
    }

    private function input(string $start, HalfDay $startHalf, string $end, HalfDay $endHalf): TimeOffRequestInput
    {
        $input = new TimeOffRequestInput($this->user);
        $input->startDay = new \DateTimeImmutable($start);
        $input->startHalf = $startHalf;
        $input->endDay = new \DateTimeImmutable($end);
        $input->endHalf = $endHalf;

        return $input;
    }

    private function request(string $start, HalfDay $startHalf, string $end, HalfDay $endHalf): TimeOffRequest
    {
        return new TimeOffRequest($this->user, TimeOffType::PaidLeave, new \DateTimeImmutable($start), $startHalf, new \DateTimeImmutable($end), $endHalf, null, new \DateTimeImmutable('2026-10-01'));
    }

    /**
     * @param int<1, 4> $quarters
     */
    private function entry(string $day, int $quarters): TimeEntry
    {
        return new TimeEntry($this->user, new Lot(new Project()), new \DateTimeImmutable($day), $quarters);
    }
}
