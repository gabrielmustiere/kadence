<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Week;
use App\Repository\TimeEntryRepository;
use App\Service\ProjectRollup;
use App\Service\RoadmapBuilder;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
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

    #[Route('/projets/{id}', name: 'app_roadmap_project', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function project(
        #[MapEntity(expr: 'repository.findOneForDetail(id)')] Project $project,
        ProjectRollup $projectRollup,
        TimeEntryRepository $timeEntryRepository,
        #[MapQueryParameter] ?string $roadmap = null,
    ): Response {
        return $this->render('roadmap/project.html.twig', [
            'page' => $this->roadmapBuilder->buildProject($project, $this->isGranted('ROLE_LEAD')),
            'summary' => $projectRollup->summarize($project, $timeEntryRepository->sumQuartersByLot($project)),
            'back' => null === $roadmap ? null : Week::tryFromIso($roadmap),
        ]);
    }

    private function renderAround(Week $anchor): Response
    {
        return $this->render('roadmap/index.html.twig', [
            'roadmap' => $this->roadmapBuilder->build(RoadmapWindow::around($anchor), $this->isGranted('ROLE_LEAD')),
            'current_week' => Week::containing($this->clock->now()),
        ]);
    }
}
