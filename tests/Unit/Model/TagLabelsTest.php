<?php

declare(strict_types=1);

namespace App\Tests\Unit\Model;

use App\Model\TagLabels;
use PHPUnit\Framework\TestCase;

final class TagLabelsTest extends TestCase
{
    public function testSplitTrimsLabelsAndDropsBlanksAndDuplicatesIgnoringCase(): void
    {
        self::assertSame(['Symfony', 'react'], TagLabels::split(' Symfony, , react ,symfony,React '));
        self::assertSame([], TagLabels::split(null));
        self::assertSame([], TagLabels::split(' , '));
    }
}
