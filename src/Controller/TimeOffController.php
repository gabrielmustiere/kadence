<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\TimeOffEntitlementsInput;
use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\TimeOffType;
use App\Exception\TimeOffRefusedException;
use App\Form\TimeOffEntitlementsType;
use App\Model\TimeOff\TimeOffPeriod;
use App\Repository\TimeOffRequestRepository;
use App\Repository\UserRepository;
use App\Security\Voter\TimeOffVoter;
use App\Service\TimeOffBook;
use App\Service\TimeOffEntitlementManager;
use App\Service\TimeOffManager;
use App\Service\TimeOffOverview;
use Psr\Clock\ClockInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

#[Route('/conges')]
final class TimeOffController extends AbstractController
{
    public function __construct(
        private readonly TimeOffBook $timeOffBook,
        private readonly TimeOffManager $timeOffManager,
        private readonly TimeOffOverview $timeOffOverview,
        private readonly TimeOffRequestRepository $timeOffRequestRepository,
        private readonly ClockInterface $clock,
    ) {
    }

    #[Route('', name: 'app_time_off_index', methods: ['GET'])]
    public function index(#[CurrentUser] User $user): Response
    {
        return $this->page($user, 'time_off/index.html.twig', [
            'balances' => $this->timeOffBook->balancesOf($user, $this->clock->now()),
            'requests' => $this->timeOffBook->historyOf($user),
        ]);
    }

    #[Route('/a-valider', name: 'app_time_off_inbox', methods: ['GET'])]
    public function inbox(#[CurrentUser] User $user): Response
    {
        $this->denyAccessUnlessGranted(TimeOffVoter::INBOX);

        return $this->page($user, 'time_off/inbox.html.twig', [
            'items' => $this->timeOffOverview->inbox($user, $this->isGranted('ROLE_DIRECTION')),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_time_off_approve', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function approve(Request $request, TimeOffRequest $timeOff, #[CurrentUser] User $user): RedirectResponse
    {
        $this->assertDecidable($request, $timeOff);

        try {
            $this->timeOffManager->approve($timeOff, $user);
            $this->addFlash('success', \sprintf('Le congé de %s est validé.', $timeOff->getUser()->getFirstName()));
        } catch (TimeOffRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_time_off_inbox');
    }

    #[Route('/{id}/refuser', name: 'app_time_off_refuse', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function refuse(Request $request, TimeOffRequest $timeOff, #[CurrentUser] User $user): RedirectResponse
    {
        $this->assertDecidable($request, $timeOff);

        try {
            $this->timeOffManager->refuse($timeOff, $user, $request->getPayload()->getString('reason'));
            $this->addFlash('success', \sprintf('La demande de %s est refusée.', $timeOff->getUser()->getFirstName()));
        } catch (TimeOffRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->redirectToRoute('app_time_off_inbox');
    }

    #[Route('/absences', name: 'app_time_off_absences', methods: ['GET'])]
    #[Route('/absences/{month}', name: 'app_time_off_absences_month', requirements: ['month' => '[1-9]\d{3}-(0[1-9]|1[0-2])'], methods: ['GET'])]
    public function absences(UserRepository $userRepository, #[CurrentUser] User $user, ?string $month = null): Response
    {
        $day = null === $month ? $this->clock->now() : new \DateTimeImmutable($month . '-01');

        return $this->page($user, 'time_off/absences.html.twig', [
            'month' => $this->timeOffOverview->month($user, $day, $userRepository->findActiveWithManager()),
        ]);
    }

    #[Route('/compteurs', name: 'app_time_off_entitlements', methods: ['GET', 'POST'])]
    #[Route('/compteurs/{type}/{year}', name: 'app_time_off_entitlements_period', requirements: ['type' => 'cp|rtt', 'year' => '[1-9]\d{3}'], methods: ['GET', 'POST'])]
    public function entitlements(Request $request, UserRepository $userRepository, TimeOffEntitlementManager $timeOffEntitlementManager, #[CurrentUser] User $user, string $type = 'cp', ?int $year = null): Response
    {
        $this->denyAccessUnlessGranted('ROLE_DIRECTION');
        $timeOffType = TimeOffType::from($type);
        $period = null === $year ? TimeOffPeriod::containing($timeOffType, $this->clock->now()) : TimeOffPeriod::of($timeOffType, $year);
        $people = $userRepository->findActiveWithManager();
        $balances = $this->timeOffOverview->balancesOver($period, $people);

        $input = TimeOffEntitlementsInput::fromBalances($balances);
        $form = $this->createForm(TimeOffEntitlementsType::class, $input, [
            'action' => $this->generateUrl('app_time_off_entitlements_period', ['type' => $timeOffType->value, 'year' => $period->year]),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $timeOffEntitlementManager->declare($period, $input->halfDaysOf($people));
            $this->addFlash('success', \sprintf('Les droits %s %s sont enregistrés.', $timeOffType->label(), $period->label()));

            return $this->redirectToRoute('app_time_off_entitlements_period', ['type' => $timeOffType->value, 'year' => $period->year]);
        }

        return $this->page($user, 'time_off/entitlements.html.twig', [
            'form' => $form,
            'period' => $period,
            'people' => $people,
            'balances' => $balances,
        ]);
    }

    #[Route('/{id}/annuler', name: 'app_time_off_cancel', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function cancel(Request $request, TimeOffRequest $timeOff, #[CurrentUser] User $user): RedirectResponse
    {
        $this->denyAccessUnlessGranted(TimeOffVoter::CANCEL, $timeOff);
        if (!$this->isCsrfTokenValid('time-off-cancel-' . $timeOff->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $this->timeOffManager->cancel($timeOff, $user);
            $this->addFlash('success', 'Le congé est annulé : ses jours sont rendus.');
        } catch (TimeOffRefusedException $exception) {
            $this->addFlash('error', $exception->getMessage());
        }

        return $this->back($request);
    }

    private function assertDecidable(Request $request, TimeOffRequest $timeOff): void
    {
        $this->denyAccessUnlessGranted(TimeOffVoter::DECIDE, $timeOff);
        if (!$this->isCsrfTokenValid('time-off-decide-' . $timeOff->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }

    /**
     * Renders a page of the time off tabs, which tell how many requests the signed-in person has to decide on.
     *
     * @param array<string, mixed> $context
     */
    private function page(User $user, string $template, array $context): Response
    {
        $inbox = $this->isGranted(TimeOffVoter::INBOX);

        return $this->render($template, [
            ...$context,
            'inbox_count' => $inbox ? $this->timeOffRequestRepository->countPendingDecidableBy($user, $this->isGranted('ROLE_DIRECTION')) : null,
        ]);
    }

    /**
     * Back to the page the form was posted from, when it is a path of this site: a browser reads « /\host » as
     * « //host ».
     */
    private function back(Request $request): RedirectResponse
    {
        $back = $request->getPayload()->getString('back');

        return 1 === preg_match('#^/(?![/\\\\])#', $back)
            ? $this->redirect($back)
            : $this->redirectToRoute('app_time_off_index');
    }
}
