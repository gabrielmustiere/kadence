<?php

declare(strict_types=1);

namespace App\Controller;

use App\Model\Roadmap\RoadmapWindow;
use App\Model\Week;
use App\Service\RoadmapBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/roadmap')]
final class RoadmapController extends AbstractController
{
    public function __construct(
        private readonly RoadmapBuilder $roadmapBuilder,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_roadmap', methods: ['GET'])]
    public function current(): Response
    {
        return $this->renderAround(Week::containing($this->clock->now()));
    }

    #[Route('/{week}', name: 'app_roadmap_week', requirements: ['week' => '\d{4}-W\d{2}'], methods: ['GET'])]
    public function week(string $week): Response
    {
        try {
            return $this->renderAround(Week::fromIso($week));
        } catch (\InvalidArgumentException $exception) {
            throw $this->createNotFoundException($exception->getMessage(), $exception);
        }
    }

    private function renderAround(Week $anchor): Response
    {
        return $this->render('roadmap/index.html.twig', [
            'roadmap' => $this->roadmapBuilder->build(RoadmapWindow::around($anchor), $this->isGranted('ROLE_LEAD')),
            'current_week' => Week::containing($this->clock->now()),
        ]);
    }
}
