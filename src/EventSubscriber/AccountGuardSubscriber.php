<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

final readonly class AccountGuardSubscriber implements EventSubscriberInterface
{
    private const string PASSWORD_CHANGE_ROUTE = 'app_account_password';
    private const array ROUTES_ALLOWED_WITH_TEMPORARY_PASSWORD = [self::PASSWORD_CHANGE_ROUTE, 'app_logout'];

    public function __construct(
        private Security $security,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [KernelEvents::REQUEST => 'onKernelRequest'];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return;
        }

        if (!$user->isActive()) {
            $response = $this->security->logout(false);
            if (null !== $response) {
                $event->setResponse($response);
            }

            return;
        }

        if ($user->mustChangePassword()
            && !\in_array($event->getRequest()->attributes->get('_route'), self::ROUTES_ALLOWED_WITH_TEMPORARY_PASSWORD, true)) {
            $event->setResponse(new RedirectResponse($this->urlGenerator->generate(self::PASSWORD_CHANGE_ROUTE)));
        }
    }
}
