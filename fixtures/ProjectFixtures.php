<?php

declare(strict_types=1);

namespace DataFixtures;

use App\Entity\Lot;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $lead = $this->getReference(AppFixtures::LEAD, User::class);
        $prod = $this->getReference(AppFixtures::PROD, User::class);
        $former = $this->getReference(AppFixtures::FORMER, User::class);

        $kadence = $this->project('Kadence', 'Outil interne de pilotage de production.');
        $this->lot($kadence, 'Socle et accès', 10, $lead);
        $split = $this->lot($kadence, 'Projets et lots', null, null);
        $this->lot($kadence, 'Modèle et règles', 5, $prod, $split);
        $this->lot($kadence, 'Écrans', 8, $lead, $split);
        $this->lot($kadence, 'Saisie des temps', null, $prod);
        $this->lot($kadence, 'Roadmap', 12, null);
        $this->lot($kadence, 'Rappels de saisie', 3, $former);

        $support = $this->project('Support et maintenance', 'Temps de support et de maintenance corrective, estimé par période.');
        $this->lot($support, 'Support', 20, $lead);

        $portal = $this->project('Évolution du portail', null);

        foreach ([$kadence, $support, $portal] as $project) {
            $manager->persist($project);
            foreach ($project->getLots() as $lot) {
                $manager->persist($lot);
            }
        }
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }

    /** @param non-empty-string $title */
    private function project(string $title, ?string $description): Project
    {
        return new Project()->setTitle($title)->setDescription($description);
    }

    /**
     * @param non-empty-string  $title
     * @param positive-int|null $estimateDays
     */
    private function lot(Project $project, string $title, ?int $estimateDays, ?User $owner, ?Lot $parent = null): Lot
    {
        return new Lot($project, $parent)->setTitle($title)->setEstimateDays($estimateDays)->setOwner($owner);
    }
}
