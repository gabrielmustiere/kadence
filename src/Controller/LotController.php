<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\LotInput;
use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use App\Exception\LotHasTimeEntriesException;
use App\Exception\LotProgressRefusedException;
use App\Form\LotFormFactory;
use App\Model\Quarters;
use App\Model\Roadmap\RoadmapView;
use App\Repository\TimeEntryRepository;
use App\Security\Voter\LotVoter;
use App\Service\LotProgressManager;
use App\Service\ProjectManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class LotController extends AbstractController
{
    public const string ADD_ANOTHER = 'add_another';

    public function __construct(
        private readonly ProjectManager $projectManager,
        private readonly LotFormFactory $lotFormFactory,
    ) {
    }

    #[Route('/projets/{id}/lots/nouveau', name: 'app_lot_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LEAD')]
    public function new(Request $request, Project $project): Response
    {
        $form = $this->lotFormFactory->forNewLot($project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $lot = $this->projectManager->addLot($project, self::inputOf($form));
            $this->addFlash('success', \sprintf('Le lot « %s » est ajouté.', $lot->getTitle()));

            return $request->request->has(self::ADD_ANOTHER)
                ? $this->redirectToRoute('app_lot_new', ['id' => $project->getId()])
                : $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('lot/new.html.twig', ['form' => $form, 'project' => $project, 'parent' => null]);
    }

    #[Route('/lots/{id}/sous-lots/nouveau', name: 'app_lot_new_sub_lot', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_LEAD')]
    public function newSubLot(Request $request, Lot $parent): Response
    {
        if ($parent->isSubLot()) {
            throw $this->createNotFoundException('Un sous-lot ne peut pas être découpé.');
        }

        $takesOver = $parent->isLeaf();
        $form = $this->lotFormFactory->forNewSubLot($parent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $subLot = $this->projectManager->addSubLot($parent, self::inputOf($form));
            $this->addFlash('success', \sprintf('Le sous-lot « %s » est ajouté.', $subLot->getTitle()));

            return $request->request->has(self::ADD_ANOTHER)
                ? $this->redirectToRoute('app_lot_new_sub_lot', ['id' => $parent->getId()])
                : $this->redirectToRoute('app_project_show', ['id' => $parent->getProject()->getId()]);
        }

        return $this->render('lot/new.html.twig', [
            'form' => $form,
            'project' => $parent->getProject(),
            'parent' => $parent,
            'takes_over' => $takesOver,
        ]);
    }

    #[Route('/lots/{id}/modifier', name: 'app_lot_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Lot $lot, TimeEntryRepository $timeEntryRepository, #[MapQueryParameter] ?string $roadmap = null, #[MapQueryParameter] ?string $signal = null): Response
    {
        $this->denyAccessUnlessGranted(LotVoter::EDIT, $lot);
        $back = RoadmapView::tryFrom($roadmap, $signal, $this->isGranted('ROLE_LEAD'));
        $backPath = null === $back
            ? $this->generateUrl('app_project_show', ['id' => $lot->getProject()->getId()])
            : $this->generateUrl('app_roadmap_week', $back->routeParams());

        $form = $this->lotFormFactory->forLot($lot);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->projectManager->updateLot($lot, self::inputOf($form));
            $this->addFlash('success', \sprintf('« %s » est mis à jour.', $lot->getTitle()));

            return $this->redirect($backPath);
        }

        $consumedQuarters = $timeEntryRepository->sumQuartersForLotId((int) $lot->getId());

        return $this->render('lot/edit.html.twig', [
            'form' => $form,
            'lot' => $lot,
            'consumed_quarters' => $consumedQuarters,
            'minimum_estimate_days' => Quarters::daysRoundedUp($consumedQuarters),
            'back_path' => $backPath,
        ]);
    }

    #[Route('/lots/{id}/supprimer', name: 'app_lot_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted('ROLE_LEAD')]
    public function delete(Request $request, Lot $lot): Response
    {
        if (!$this->isCsrfTokenValid('lot-' . $lot->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $title = $lot->getTitle();
        $projectId = $lot->getProject()->getId();
        try {
            $this->projectManager->deleteLot($lot);
            $this->addFlash('success', \sprintf('« %s » est supprimé.', $title));
        } catch (LotHasTimeEntriesException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_project_show', ['id' => $projectId, ...$this->backParams($request)]);
    }

    #[Route('/lots/{id}/avancement', name: 'app_lot_progress', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function progress(Request $request, Lot $lot, #[CurrentUser] User $user, LotProgressManager $lotProgressManager): Response
    {
        $this->denyAccessUnlessGranted(LotVoter::PROGRESS, $lot);
        $payload = $request->getPayload();
        if (!$this->isCsrfTokenValid('lot-progress-' . $lot->getId(), $payload->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $declaration = $lotProgressManager->declare($lot, $payload->getInt('percent'), $user);
            $this->addFlash('success', \sprintf('Avancement de « %s » : %d %%.', $lot->getTitle(), $declaration->getPercent()));
        } catch (LotProgressRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_project_show', ['id' => $lot->getProject()->getId(), ...$this->backParams($request)]);
    }

    /**
     * The view of the roadmap the page of the project was opened from, which an action made on it keeps.
     *
     * @return array{roadmap?: string, signal?: string}
     */
    private function backParams(Request $request): array
    {
        $payload = $request->getPayload();
        $back = RoadmapView::tryFrom($payload->getString('roadmap'), $payload->getString('signal'), $this->isGranted('ROLE_LEAD'));

        return null === $back ? [] : ['roadmap' => $back->week->iso(), ...$back->signalParams()];
    }

    private static function inputOf(FormInterface $form): LotInput
    {
        $input = $form->getData();
        \assert($input instanceof LotInput);

        return $input;
    }
}
