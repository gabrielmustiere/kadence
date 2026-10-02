<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\LotProgressRefusedException;
use App\Repository\LotProgressRepository;
use App\Repository\TimeEntryRepository;
use App\Service\LotProgressManager;
use Doctrine\DBAL\Driver\AbstractException;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;

final class LotProgressManagerTest extends TestCase
{
    public function testADeclarationMadeMeanwhileTheSameDayIsRefusedWithAMessage(): void
    {
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('flush')->willThrowException(new UniqueConstraintViolationException(new class('UNIQUE constraint failed') extends AbstractException {}, null));
        $leaf = new Lot(new Project()->setTitle('Kadence'))->setTitle('Synchronisation')->setEstimateDays(10);

        $manager = new LotProgressManager($entityManager, $this->createStub(LotProgressRepository::class), $this->createStub(TimeEntryRepository::class), new MockClock('2026-10-02 10:00'));

        try {
            $manager->declare($leaf, 40, new User());
            self::fail('A declaration made meanwhile is refused.');
        } catch (LotProgressRefusedException $exception) {
            self::assertSame('Un avancement vient d\'être déclaré sur « Synchronisation » : rechargez la page avant de le modifier.', $exception->getMessage());
        }
    }
}
