<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Project;
use App\Model\Roadmap\RoadmapWindow;
use App\Model\Week;
use App\Service\RoadmapBuilder;
use Psr\Clock\ClockInterface;
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
        Project $project,
        #[MapQueryParameter] ?string $roadmap = null,
    ): Response {
        $back = null === $roadmap ? null : Week::tryFromIso($roadmap);

        return $this->redirectToRoute('app_project_show', ['id' => $project->getId(), 'roadmap' => $back?->iso()], Response::HTTP_MOVED_PERMANENTLY);
    }

    private function renderAround(Week $anchor): Response
    {
        return $this->render('roadmap/index.html.twig', [
            'roadmap' => $this->roadmapBuilder->build(RoadmapWindow::around($anchor), $this->isGranted('ROLE_LEAD')),
            'current_week' => Week::containing($this->clock->now()),
        ]);
    }
}
