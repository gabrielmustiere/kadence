<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Security\Voter\PersonVoter;
use App\Service\PersonRoadmapBuilder;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PersonController extends AbstractController
{
    #[Route('/personnes/{id}', name: 'app_person', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(User $person, PersonRoadmapBuilder $personRoadmapBuilder): Response
    {
        return $this->render('person/show.html.twig', [
            'person' => $person,
            'page' => $personRoadmapBuilder->build($person, $this->isGranted('ROLE_LEAD'), $this->isGranted(PersonVoter::PLANNING, $person)),
        ]);
    }
}
