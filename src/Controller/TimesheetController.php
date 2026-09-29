<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Week;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/saisie')]
final class TimesheetController extends AbstractController
{
    public function __construct(
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_timesheet', methods: ['GET'])]
    public function current(): Response
    {
        return $this->renderWeek(Week::containing($this->clock->now()));
    }

    #[Route('/{week}', name: 'app_timesheet_week', requirements: ['week' => '\d{4}-W\d{2}'], methods: ['GET'])]
    public function week(string $week): Response
    {
        try {
            return $this->renderWeek(Week::fromIso($week));
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }
    }

    private function renderWeek(Week $week): Response
    {
        return $this->render('timesheet/index.html.twig', [
            'week' => $week,
            'current_week' => Week::containing($this->clock->now()),
        ]);
    }
}
