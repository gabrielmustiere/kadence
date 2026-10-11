<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Dto\TimeOffRequestInput;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Mailer\TimeOffMailer;
use App\Repository\HolidayAdjustmentRepository;
use App\Repository\TimeOffEntitlementRepository;
use App\Repository\TimeOffRequestRepository;
use App\Repository\UserRepository;
use App\Service\HolidayManager;
use App\Service\LegalHolidays;
use App\Service\TimeOffApprovers;
use App\Service\TimeOffBook;
use App\Service\TimeOffManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final class TimeOffManagerTest extends TestCase
{
    public function testTheRequestOfTheOnlyActiveDirectorWithoutManagerIsApprovedAtOnceWithoutEmail(): void
    {
        $director = new User()->setFirstName('Alice')->setLastName('Martin')->setEmail('alice@example.com')->setRole(Role::Direction);
        new \ReflectionProperty(User::class, 'id')->setValue($director, 1);
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findActiveDirectors')->willReturn([$director]);
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $timeOffRequestRepository = $this->createStub(TimeOffRequestRepository::class);

        $manager = new TimeOffManager(
            $entityManager,
            $timeOffRequestRepository,
            new TimeOffApprovers($userRepository),
            new TimeOffBook($timeOffRequestRepository, $this->createStub(TimeOffEntitlementRepository::class), new HolidayManager($entityManager, $this->createStub(HolidayAdjustmentRepository::class), new LegalHolidays())),
            new TimeOffMailer($mailer, $this->createStub(UrlGeneratorInterface::class), 'kadence@example.com'),
            new MockClock('2026-10-12 10:00'),
        );
        $input = new TimeOffRequestInput($director);
        $input->startDay = new \DateTimeImmutable('2026-11-16');
        $input->endDay = new \DateTimeImmutable('2026-11-20');

        $request = $manager->request($input);

        self::assertSame(TimeOffStatus::Approved, $request->getStatus());
        self::assertNull($request->getDecidedBy());
    }
}
