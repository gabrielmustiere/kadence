<?php

declare(strict_types=1);

namespace App\Twig\Components;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Exception\TimeOffRefusedException;
use App\Model\TimeOff\TimeOffPreview;
use App\Repository\TimeOffRequestRepository;
use App\Security\Voter\TimeOffVoter;
use App\Service\TimeOffManager;
use App\Service\TimeOffOverview;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\LiveComponent\DefaultActionTrait;
use Symfony\UX\TwigComponent\Attribute\PostMount;

/**
 * The requests the signed-in person has to decide on, approved or refused in place: the others, their balances and the
 * count of the tab follow at once.
 */
#[AsLiveComponent]
final class TimeOffInbox extends AbstractController
{
    use DefaultActionTrait;
    use NotifiesInPlace;

    /** @var array<int, string> the reason typed in for each refusal, by request id */
    #[LiveProp(writable: true)]
    public array $reasons = [];

    /** @var array<int, string> why a refusal was not recorded, by request id */
    public array $refusalErrors = [];

    /** @var list<array{request: TimeOffRequest, preview: TimeOffPreview, colleagues: list<TimeOffRequest>|null}>|null */
    private ?array $items = null;

    public function __construct(
        private readonly TimeOffOverview $timeOffOverview,
        private readonly TimeOffManager $timeOffManager,
        private readonly TimeOffRequestRepository $timeOffRequestRepository,
    ) {
    }

    #[PostMount(priority: 10)]
    #[PostHydrate]
    public function denyToThoseWithNothingToDecide(): void
    {
        $this->denyAccessUnlessGranted(TimeOffVoter::INBOX);
    }

    /**
     * The browser only binds a model to a key the prop already has.
     */
    #[PostMount]
    #[PostHydrate]
    public function keepAReasonForEachRequest(): void
    {
        foreach ($this->getItems() as $item) {
            $this->reasons[(int) $item['request']->getId()] ??= '';
        }
    }

    /**
     * @return list<array{request: TimeOffRequest, preview: TimeOffPreview, colleagues: list<TimeOffRequest>|null}>
     */
    public function getItems(): array
    {
        return $this->items ??= $this->timeOffOverview->inbox($this->user(), $this->isGranted('ROLE_DIRECTION'));
    }

    #[LiveAction]
    public function approve(#[LiveArg] int $request): void
    {
        $timeOff = $this->decidable($request);
        try {
            $this->timeOffManager->approve($timeOff, $this->user());
            $this->notify('success', \sprintf('Le congé de %s est validé.', $timeOff->getUser()->getFirstName()));
        } catch (TimeOffRefusedException $exception) {
            $this->notify('error', $exception->getMessage());
        }
        $this->items = null;
    }

    #[LiveAction]
    public function refuse(#[LiveArg] int $request): void
    {
        $timeOff = $this->decidable($request);
        try {
            $this->timeOffManager->refuse($timeOff, $this->user(), $this->reasons[$request] ?? '');
            unset($this->reasons[$request]);
            $this->notify('success', \sprintf('La demande de %s est refusée.', $timeOff->getUser()->getFirstName()));
        } catch (TimeOffRefusedException $exception) {
            $this->refusalErrors[$request] = $exception->getMessage();
        }
        $this->items = null;
    }

    private function decidable(int $id): TimeOffRequest
    {
        $timeOff = $this->timeOffRequestRepository->find($id) ?? throw $this->createNotFoundException('Cette demande n\'existe pas.');
        $this->denyAccessUnlessGranted(TimeOffVoter::DECIDE, $timeOff);

        return $timeOff;
    }

    private function user(): User
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Les congés sont réservés aux personnes connectées.');
        }

        return $user;
    }
}
