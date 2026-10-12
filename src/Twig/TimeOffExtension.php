<?php

declare(strict_types=1);

namespace App\Twig;

use App\Entity\User;
use App\Repository\TimeOffRequestRepository;
use App\Security\Voter\TimeOffVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Attribute\AsTwigFunction;

final readonly class TimeOffExtension
{
    public function __construct(
        private Security $security,
        private TimeOffRequestRepository $timeOffRequestRepository,
    ) {
    }

    /**
     * The requests the signed-in person has to decide on, 0 when they have none to decide.
     */
    #[AsTwigFunction('time_off_inbox_count')]
    public function inboxCount(): int
    {
        $user = $this->security->getUser();
        if (!$user instanceof User || !$this->security->isGranted(TimeOffVoter::INBOX)) {
            return 0;
        }

        return $this->timeOffRequestRepository->countPendingDecidableBy($user, $this->security->isGranted('ROLE_DIRECTION'));
    }
}
