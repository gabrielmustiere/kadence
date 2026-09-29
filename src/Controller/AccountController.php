<?php

declare(strict_types=1);

namespace App\Controller;

use App\Dto\ChangePasswordInput;
use App\Entity\User;
use App\Form\ChangePasswordType;
use App\Service\TeamManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class AccountController extends AbstractController
{
    public function __construct(
        private readonly TeamManager $teamManager,
        private readonly Security $security,
    ) {
    }

    #[Route(
        path: '/mon-compte/mot-de-passe',
        name: 'app_account_password',
        methods: ['GET', 'POST'],
    )]
    public function password(Request $request, #[CurrentUser] User $user): Response
    {
        $forced = $user->mustChangePassword();
        $input = new ChangePasswordInput();
        $form = $this->createForm(ChangePasswordType::class, $input, ['require_current_password' => !$forced]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->teamManager->changePassword($user, $input->newPassword ?? throw new \LogicException('A validated password change has a new password.'));
            // Changing the password hash invalidates the session token on the next request: log the user back in.
            $this->security->login($user, 'form_login', 'main');
            $this->addFlash('success', 'Votre mot de passe a été modifié.');

            return $this->redirectToRoute('app_timesheet');
        }

        return $this->render('account/password.html.twig', [
            'form' => $form,
            'forced' => $forced,
        ]);
    }
}
