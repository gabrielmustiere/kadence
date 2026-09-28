<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PageController extends AbstractController
{
    #[Route(
        path: '/',
        name: 'app_page'
    )]
    public function index(): Response
    {
        return $this->render('page/index.html.twig');
    }
}
