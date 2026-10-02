<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

final class SecurityController extends AbstractController
{
    #[Route(
        path: '/login',
        name: 'app_login'
    )]
    public function login(AuthenticationUtils $authenticationUtils, UserRepository $userRepository): Response
    {
        $error = $authenticationUtils->getLastAuthenticationError();

        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
            'dev_users' => 'dev' === $this->getParameter('kernel.environment') ? $userRepository->findAllForTeamList() : [],
        ]);
    }

    #[Route(
        path: '/logout',
        name: 'app_logout'
    )]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
