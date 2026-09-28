<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Service\TemporaryPasswordGenerator;
use PHPUnit\Framework\TestCase;

final class TemporaryPasswordGeneratorTest extends TestCase
{
    public function testGeneratesPasswordOfExpectedLengthFromUnambiguousAlphabet(): void
    {
        $password = new TemporaryPasswordGenerator()->generate();

        self::assertSame(TemporaryPasswordGenerator::LENGTH, \strlen($password));
        self::assertMatchesRegularExpression('/^[' . TemporaryPasswordGenerator::ALPHABET . ']+$/', $password);
        self::assertDoesNotMatchRegularExpression('/[0O1lI]/', $password);
    }

    public function testGeneratesDifferentPasswords(): void
    {
        $generator = new TemporaryPasswordGenerator();

        self::assertNotSame($generator->generate(), $generator->generate());
    }
}
