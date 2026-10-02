<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Lot;
use App\Entity\Project;
use PHPUnit\Framework\TestCase;

final class ProjectTest extends TestCase
{
    public function testTopLevelLotsLeaveTheSubLotsAside(): void
    {
        $project = new Project();
        $split = new Lot($project);
        new Lot($project, $split);
        $leaf = new Lot($project);

        self::assertSame([$split, $leaf], $project->topLevelLots());
        self::assertCount(3, $project->getLots());
    }
}
