<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\TeamListFilter;
use App\Dto\TeamMemberInput;
use App\Entity\User;
use App\Entity\WeeklyMax;
use App\Exception\LastActiveDirectorException;
use App\Exception\ManagerWithActiveReportsException;
use App\Form\TeamFilterType;
use App\Form\TeamMemberType;
use App\Model\Week;
use App\Repository\UserRepository;
use App\Repository\WeeklyMaxRepository;
use App\Service\TeamManager;
use App\Service\WeeklyMaxManager;
use Psr\Clock\ClockInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/equipe')]
#[IsGranted('ROLE_DIRECTION')]
final class TeamController extends AbstractController
{
    private const string TEMPORARY_PASSWORD_SESSION_KEY = 'team.temporary_password';

    public function __construct(
        private readonly TeamManager $teamManager,
        private readonly WeeklyMaxManager $weeklyMaxManager,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_team_index', methods: ['GET'])]
    public function index(Request $request, UserRepository $userRepository): Response
    {
        $filter = new TeamListFilter();
        $filterForm = $this->createForm(TeamFilterType::class, $filter);
        $filterForm->handleRequest($request);

        return $this->render('team/index.html.twig', [
            'members' => $userRepository->findForTeamList($filter),
            'filter_form' => $filterForm,
            'filtered' => [] !== $filter->tags() || null !== $filter->manager,
        ]);
    }

    #[Route('/nouveau', name: 'app_team_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $input = TeamMemberInput::forNewMember($this->currentWeek());
        $form = $this->createForm(TeamMemberType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            [$user, $temporaryPassword] = $this->teamManager->register($input);

            return $this->redirectToTemporaryPassword($request->getSession(), $user, $temporaryPassword);
        }

        return $this->render('team/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}/modifier', name: 'app_team_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, WeeklyMaxRepository $weeklyMaxRepository): Response
    {
        $currentWeek = $this->currentWeek();
        $input = TeamMemberInput::fromUser($user, $this->weeklyMaxManager->quartersFor($user, $currentWeek), $currentWeek);
        $form = $this->createForm(TeamMemberType::class, $input);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $this->teamManager->update($user, $input);
                $this->addFlash('success', \sprintf('La fiche de %s a été mise à jour.', $user->getFirstName()));
            } catch (LastActiveDirectorException $exception) {
                $this->addFlash('error', $exception->getMessage());
            }

            return $this->redirectToRoute('app_team_index');
        }

        return $this->render('team/edit.html.twig', [
            'form' => $form,
            'member' => $user,
            'weekly_maxes' => $weeklyMaxRepository->findForUser($user),
        ]);
    }

    #[Route('/{id}/maximum/{weeklyMaxId}/supprimer', name: 'app_team_weekly_max_delete', requirements: ['id' => '\d+', 'weeklyMaxId' => '\d+'], methods: ['POST'])]
    public function deleteWeeklyMax(Request $request, User $user, #[MapEntity(id: 'weeklyMaxId')] WeeklyMax $weeklyMax): Response
    {
        if ($weeklyMax->getUser() !== $user) {
            throw $this->createNotFoundException('Cette valeur n\'appartient pas à cette personne.');
        }
        if (!$this->isCsrfTokenValid('weekly-max-' . $weeklyMax->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->weeklyMaxManager->delete($weeklyMax);
        $this->addFlash('success', \sprintf('La valeur du maximum hebdomadaire de %s est supprimée.', $user->getFirstName()));

        return $this->redirectToRoute('app_team_edit', ['id' => $user->getId()]);
    }

    #[Route('/{id}/desactiver', name: 'app_team_deactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deactivate(Request $request, User $user): Response
    {
        $this->denyUnlessValidCsrfToken($request, $user);

        try {
            $this->teamManager->deactivate($user);
            $this->addFlash('success', \sprintf('Le compte de %s %s est désactivé.', $user->getFirstName(), $user->getLastName()));
        } catch (LastActiveDirectorException|ManagerWithActiveReportsException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_team_index');
    }

    #[Route('/{id}/reactiver', name: 'app_team_reactivate', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reactivate(Request $request, User $user): Response
    {
        $this->denyUnlessValidCsrfToken($request, $user);

        $this->teamManager->reactivate($user);
        $this->addFlash('success', \sprintf('Le compte de %s %s est réactivé.', $user->getFirstName(), $user->getLastName()));

        return $this->redirectToRoute('app_team_index');
    }

    #[Route('/{id}/mot-de-passe-provisoire', name: 'app_team_reset_password', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function resetPassword(Request $request, User $user): Response
    {
        $this->denyUnlessValidCsrfToken($request, $user);

        return $this->redirectToTemporaryPassword($request->getSession(), $user, $this->teamManager->resetPassword($user));
    }

    #[Route('/{id}/mot-de-passe-provisoire', name: 'app_team_temporary_password', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function temporaryPassword(Request $request, User $user): Response
    {
        $temporaryPassword = $this->pullTemporaryPassword($request->getSession(), $user);
        if (null === $temporaryPassword) {
            $this->addFlash('info', 'Ce mot de passe provisoire a déjà été affiché. Générez-en un nouveau si besoin.');

            return $this->redirectToRoute('app_team_index');
        }

        $response = $this->render('team/temporary_password.html.twig', [
            'member' => $user,
            'temporary_password' => $temporaryPassword,
        ]);
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }

    private function redirectToTemporaryPassword(SessionInterface $session, User $user, string $temporaryPassword): Response
    {
        $pending = $this->pendingTemporaryPasswords($session);
        $pending[self::memberId($user)] = $temporaryPassword;
        $session->set(self::TEMPORARY_PASSWORD_SESSION_KEY, $pending);

        return $this->redirectToRoute('app_team_temporary_password', ['id' => $user->getId()]);
    }

    private function pullTemporaryPassword(SessionInterface $session, User $user): ?string
    {
        $pending = $this->pendingTemporaryPasswords($session);
        $temporaryPassword = $pending[self::memberId($user)] ?? null;
        unset($pending[self::memberId($user)]);
        $session->set(self::TEMPORARY_PASSWORD_SESSION_KEY, $pending);

        return $temporaryPassword;
    }

    /**
     * @return array<int, string>
     */
    private function pendingTemporaryPasswords(SessionInterface $session): array
    {
        $pending = $session->get(self::TEMPORARY_PASSWORD_SESSION_KEY);
        if (!\is_array($pending)) {
            return [];
        }

        return array_filter(
            $pending,
            static fn (mixed $password, mixed $id): bool => \is_int($id) && \is_string($password),
            \ARRAY_FILTER_USE_BOTH,
        );
    }

    private function currentWeek(): Week
    {
        return Week::containing($this->clock->now());
    }

    private static function memberId(User $user): int
    {
        return $user->getId() ?? throw new \LogicException('A team member shown in the team screens is persisted.');
    }

    private function denyUnlessValidCsrfToken(Request $request, User $user): void
    {
        if (!$this->isCsrfTokenValid('team-member-' . $user->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
