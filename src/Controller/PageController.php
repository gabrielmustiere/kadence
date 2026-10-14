<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\TimeOffVoter;
use App\Service\HomeAgendaBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

final class PageController extends AbstractController
{
    #[Route(
        path: '/',
        name: 'app_page'
    )]
    public function index(#[CurrentUser] User $user, HomeAgendaBuilder $homeAgendaBuilder): Response
    {
        return $this->render('page/index.html.twig', [
            'agenda' => $homeAgendaBuilder->build($user, $this->isGranted(TimeOffVoter::INBOX), $this->isGranted('ROLE_DIRECTION'), $this->isGranted('ROLE_LEAD')),
        ]);
    }
}
