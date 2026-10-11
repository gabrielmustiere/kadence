<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\TimeOffEntitlement;
use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\HalfDay;
use App\Enum\Type\TimeOffStatus;
use App\Enum\Type\TimeOffType;
use Doctrine\ORM\EntityManagerInterface;

trait CreatesTimeOff
{
    abstract private function entityManager(): EntityManagerInterface;

    private function createTimeOff(User $user, string $start, string $end, TimeOffStatus $status = TimeOffStatus::Approved, TimeOffType $type = TimeOffType::PaidLeave, HalfDay $startHalf = HalfDay::Morning, HalfDay $endHalf = HalfDay::Afternoon): TimeOffRequest
    {
        $request = new TimeOffRequest($user, $type, new \DateTimeImmutable($start), $startHalf, new \DateTimeImmutable($end), $endHalf, null, new \DateTimeImmutable());
        match ($status) {
            TimeOffStatus::Pending => null,
            TimeOffStatus::Approved => $request->approve(null, new \DateTimeImmutable()),
            TimeOffStatus::Refused => $request->refuse($user, new \DateTimeImmutable(), 'Refus de test'),
            TimeOffStatus::Cancelled => $request->cancel($user, new \DateTimeImmutable()),
        };

        $entityManager = $this->entityManager();
        $entityManager->persist($request);
        $entityManager->flush();

        return $request;
    }

    /**
     * @param int<0, max> $halfDays
     */
    private function createEntitlement(User $user, TimeOffType $type, int $year, int $halfDays): TimeOffEntitlement
    {
        $entitlement = new TimeOffEntitlement($user, $type, $year, $halfDays);

        $entityManager = $this->entityManager();
        $entityManager->persist($entitlement);
        $entityManager->flush();

        return $entitlement;
    }
}
