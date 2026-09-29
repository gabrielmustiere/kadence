<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Lot;
use App\Entity\Project;
use App\Repository\LotRepository;
use App\Service\LeafFinder;
use PHPUnit\Framework\TestCase;

final class LeafFinderTest extends TestCase
{
    public function testMatchesEveryWordAcrossProjectLotAndSubLotWithAccentsAndCaseFolded(): void
    {
        $kadence = new Project()->setTitle('Kadence');
        $screens = new Lot($kadence)->setTitle('Écrans');
        $entry = new Lot($kadence, $screens)->setTitle('Saisie');
        $roadmap = new Lot($kadence)->setTitle('Roadmap');

        $finder = $this->finder([$entry, $roadmap]);

        self::assertSame([$entry], $finder->search('ecrans'));
        self::assertSame([$entry], $finder->search('KAD  saisie'));
        self::assertSame([$entry, $roadmap], $finder->search('kadence'));
        self::assertSame([], $finder->search('kadence facturation'));
    }

    public function testShortQueriesFindNothing(): void
    {
        $lot = new Lot(new Project()->setTitle('Kadence'))->setTitle('Roadmap');

        self::assertSame([], $this->finder([$lot])->search(' k '));
    }

    public function testResultsAreLimitedAndSortedByProject(): void
    {
        $leaves = [];
        foreach (['Zêta', 'alpha', 'Beta'] as $title) {
            for ($i = 0; $i < 4; ++$i) {
                $leaves[] = new Lot(new Project()->setTitle($title . ' commun'))->setTitle('Lot ' . $i);
            }
        }

        $results = $this->finder($leaves)->search('commun');

        self::assertCount(LeafFinder::LIMIT, $results);
        self::assertSame('alpha commun', $results[0]->getProject()->getTitle());
        self::assertSame('Zêta commun', $results[9]->getProject()->getTitle());
    }

    /**
     * @param list<Lot> $leaves
     */
    private function finder(array $leaves): LeafFinder
    {
        $repository = $this->createStub(LotRepository::class);
        $repository->method('findLeavesWithAncestors')->willReturn($leaves);

        return new LeafFinder($repository);
    }
}
