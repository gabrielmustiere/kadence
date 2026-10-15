<?php

declare(strict_types=1);

namespace DataFixtures;

use App\Entity\Lot;
use App\Entity\LotMember;
use App\Entity\Project;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class ProjectFixtures extends Fixture implements DependentFixtureInterface
{
    public const string SUPPORT = 'lot-support';

    public function load(ObjectManager $manager): void
    {
        $lead = $this->getReference(AppFixtures::LEAD, User::class);

        $project = new Project()->setTitle('Support et maintenance')->setDescription('Temps de support et de maintenance corrective, estimé par période.');
        $support = new Lot($project)->setTitle('Support')->setEstimateDays(20)->setOwner($lead);
        new LotMember($support, $lead, 100);
        $this->addReference(self::SUPPORT, $support);

        $manager->persist($project);
        $manager->persist($support);
        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [AppFixtures::class];
    }
}
