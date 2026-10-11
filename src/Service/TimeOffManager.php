<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\TimeOffRequestInput;
use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\Role;
use App\Enum\Type\TimeOffStatus;
use App\Exception\TimeOffRefusedException;
use App\Mailer\TimeOffMailer;
use App\Repository\TimeOffRequestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Clock\ClockInterface;

final readonly class TimeOffManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TimeOffRequestRepository $timeOffRequestRepository,
        private TimeOffApprovers $timeOffApprovers,
        private TimeOffBook $timeOffBook,
        private TimeOffMailer $timeOffMailer,
        private ClockInterface $clock,
    ) {
    }

    /**
     * Records a validated request and sends it to whoever decides on it; with nobody else to decide, it is approved at
     * once.
     */
    public function request(TimeOffRequestInput $input): TimeOffRequest
    {
        $comment = trim($input->comment ?? '');
        $request = new TimeOffRequest(
            $input->requester,
            $input->type ?? throw new \LogicException('A validated time off input has a type.'),
            $input->startDay ?? throw new \LogicException('A validated time off input has a start day.'),
            $input->startHalf ?? throw new \LogicException('A validated time off input has a start half-day.'),
            $input->endDay ?? throw new \LogicException('A validated time off input has an end day.'),
            $input->endHalf ?? throw new \LogicException('A validated time off input has an end half-day.'),
            '' === $comment ? null : $comment,
            $this->clock->now(),
        );

        $approvers = $this->timeOffApprovers->of($input->requester);
        if ([] === $approvers) {
            $request->approve(null, $this->clock->now());
        }

        $this->entityManager->persist($request);
        $this->entityManager->flush();

        if ([] !== $approvers) {
            $this->timeOffMailer->requested($request, $this->timeOffBook->previewOfRequest($request), $approvers);
        }

        return $request;
    }

    /**
     * @throws TimeOffRefusedException
     */
    public function approve(TimeOffRequest $request, User $by): void
    {
        self::assertPending($request);

        $request->approve($by, $this->clock->now());
        $this->entityManager->flush();

        $this->timeOffMailer->decided($request, self::replacedManager($request, $by));
    }

    /**
     * @throws TimeOffRefusedException
     */
    public function refuse(TimeOffRequest $request, User $by, string $reason): void
    {
        $reason = trim($reason);
        if ('' === $reason) {
            throw TimeOffRefusedException::reasonMissing();
        }
        self::assertPending($request);

        $request->refuse($by, $this->clock->now(), $reason);
        $this->entityManager->flush();

        $this->timeOffMailer->decided($request, self::replacedManager($request, $by));
    }

    /**
     * Approved time off is cancelled by its person until its first day, by the direction at any time. Cancelling it
     * tells whoever decides on the requests of its person, or the person when someone else cancels it; cancelling a
     * pending request tells nobody.
     *
     * @throws TimeOffRefusedException
     */
    public function cancel(TimeOffRequest $request, User $by): void
    {
        $status = $request->getStatus();
        if (!$status->isActive()) {
            throw TimeOffRefusedException::alreadyDecided();
        }
        $own = $by->getId() === $request->getUser()->getId();
        $started = $request->getStartDay() <= $this->clock->now()->setTime(0, 0);
        if (TimeOffStatus::Approved === $status && $started && Role::Direction !== $by->getRole()) {
            throw TimeOffRefusedException::notCancellable();
        }

        $request->cancel($by, $this->clock->now());
        $this->entityManager->flush();

        if (TimeOffStatus::Approved === $status) {
            $this->timeOffMailer->cancelled($request, $own ? $this->timeOffApprovers->of($request->getUser()) : [$request->getUser()]);
        }
    }

    /**
     * Cancels, without telling anyone, the pending requests of a person being deactivated; the caller flushes.
     */
    public function cancelPendingOf(User $user): void
    {
        foreach ($this->timeOffRequestRepository->findPendingOfUser($user) as $request) {
            $request->cancel(null, $this->clock->now());
        }
    }

    private static function assertPending(TimeOffRequest $request): void
    {
        if (TimeOffStatus::Pending !== $request->getStatus()) {
            throw TimeOffRefusedException::alreadyDecided();
        }
    }

    private static function replacedManager(TimeOffRequest $request, User $by): ?User
    {
        $manager = $request->getUser()->getManager();

        return null !== $manager && $manager->isActive() && $manager->getId() !== $by->getId() ? $manager : null;
    }
}
