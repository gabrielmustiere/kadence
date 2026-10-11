<?php

declare(strict_types=1);

namespace App\Mailer;

use App\Entity\TimeOffRequest;
use App\Entity\User;
use App\Enum\Type\TimeOffStatus;
use App\Model\TimeOff\TimeOffPreview;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * The context holds only scalars: the email is serialized when it is queued.
 */
final readonly class TimeOffMailer
{
    public function __construct(
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(env: 'MAILER_SENDER')]
        private string $sender,
    ) {
    }

    /**
     * @param list<User> $approvers
     */
    public function requested(TimeOffRequest $request, TimeOffPreview $preview, array $approvers): void
    {
        foreach ($approvers as $approver) {
            $this->send($approver, \sprintf('Demande de congé de %s', self::name($request->getUser())), 'emails/time_off/requested.html.twig', [
                ...self::describe($request),
                'half_days' => $preview->halfDays,
                'overrun' => $preview->isOverrun(),
                'url' => $this->urlGenerator->generate('app_time_off_inbox', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }
    }

    /**
     * @param User|null $replacedManager the manager of the person, told when the direction decided in their stead
     */
    public function decided(TimeOffRequest $request, ?User $replacedManager): void
    {
        $approved = TimeOffStatus::Approved === $request->getStatus();
        $context = [
            ...self::describe($request),
            'approved' => $approved,
            'reason' => $request->getRefusalReason(),
            'decided_by' => null === $request->getDecidedBy() ? null : self::name($request->getDecidedBy()),
        ];

        $this->send($request->getUser(), $approved ? 'Votre congé est validé' : 'Votre demande de congé est refusée', 'emails/time_off/decided.html.twig', [
            ...$context,
            'url' => $this->urlGenerator->generate('app_time_off_index', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);

        if (null !== $replacedManager) {
            $this->send($replacedManager, \sprintf('Congé de %s %s par la direction', self::name($request->getUser()), $approved ? 'validé' : 'refusé'), 'emails/time_off/decided.html.twig', [
                ...$context,
                'for_manager' => true,
                'url' => $this->urlGenerator->generate('app_time_off_absences', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }
    }

    /**
     * @param list<User> $recipients
     */
    public function cancelled(TimeOffRequest $request, array $recipients): void
    {
        foreach ($recipients as $recipient) {
            $this->send($recipient, \sprintf('Congé de %s annulé', self::name($request->getUser())), 'emails/time_off/cancelled.html.twig', [
                ...self::describe($request),
                'cancelled_by' => null === $request->getCancelledBy() ? null : self::name($request->getCancelledBy()),
                'own' => $recipient->getId() === $request->getUser()->getId(),
                'url' => $this->urlGenerator->generate('app_time_off_absences', [], UrlGeneratorInterface::ABSOLUTE_URL),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $context
     */
    private function send(User $recipient, string $subject, string $template, array $context): void
    {
        $this->mailer->send(new TemplatedEmail()
            ->from(new Address($this->sender, 'Kadence'))
            ->to(new Address((string) $recipient->getEmail(), self::name($recipient)))
            ->subject('[Kadence] ' . $subject)
            ->htmlTemplate($template)
            ->context($context));
    }

    /**
     * @return array<string, string|null>
     */
    private static function describe(TimeOffRequest $request): array
    {
        return [
            'person' => self::name($request->getUser()),
            'type' => $request->getType()->label(),
            'start' => \sprintf('%s %s', $request->getStartDay()->format('d/m/Y'), $request->getStartHalf()->label()),
            'end' => \sprintf('%s %s', $request->getEndDay()->format('d/m/Y'), $request->getEndHalf()->label()),
            'comment' => $request->getComment(),
        ];
    }

    private static function name(User $user): string
    {
        return \sprintf('%s %s', $user->getFirstName(), $user->getLastName());
    }
}
