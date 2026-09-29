<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ProjectInput;
use App\Entity\Project;
use App\Entity\User;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Service\ProjectManager;
use App\Service\ProjectRollup;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projets')]
final class ProjectController extends AbstractController
{
    public function __construct(
        private readonly ProjectManager $projectManager,
        private readonly ProjectRollup $projectRollup,
    ) {
    }

    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(
        ProjectRepository $projectRepository,
        #[CurrentUser] User $user,
        #[MapQueryParameter(name: 'mes-responsabilites')] bool $mine = false,
    ): Response {
        return $this->render('project/index.html.twig', [
            'projects' => array_map($this->projectRollup->summarize(...), $projectRepository->findAllForList($mine ? $user : null)),
            'mine' => $mine,
        ]);
    }

    #[Route('/nouveau', name: 'app_project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LEAD')]
    public function new(Request $request): Response
    {
        $input = new ProjectInput();
        $form = $this->createForm(ProjectType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $project = $this->projectManager->createProject($input);
            $this->addFlash('success', \sprintf('Le projet « %s » est créé. Découpez-le en lots.', $project->getTitle()));

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_project_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(#[MapEntity(expr: 'repository.findOneForDetail(id)')] Project $project): Response
    {
        return $this->render('project/show.html.twig', [
            'summary' => $this->projectRollup->summarize($project),
        ]);
    }

    #[Route('/{id}/modifier', name: 'app_project_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LEAD')]
    public function edit(Request $request, Project $project): Response
    {
        $input = ProjectInput::fromProject($project);
        $form = $this->createForm(ProjectType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->projectManager->updateProject($project, $input);
            $this->addFlash('success', 'Le projet est mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', ['form' => $form, 'project' => $project]);
    }

    #[Route('/{id}/supprimer', name: 'app_project_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_LEAD')]
    public function delete(Request $request, Project $project): Response
    {
        if (!$this->isCsrfTokenValid('project-' . $project->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $title = $project->getTitle();
        $this->projectManager->deleteProject($project);
        $this->addFlash('success', \sprintf('Le projet « %s » est supprimé.', $title));

        return $this->redirectToRoute('app_project_index');
    }
}
