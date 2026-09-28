<?php

declare(strict_types=1);

namespace App\Tests\Unit\Enum\Type;

use App\Enum\Type\Role;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class RoleTest extends TestCase
{
    #[DataProvider('roleProvider')]
    public function testSecurityRole(Role $role, string $expected): void
    {
        self::assertSame($expected, $role->securityRole());
    }

    /**
     * @return \Generator<array{Role, string}>
     */
    public static function roleProvider(): \Generator
    {
        yield [Role::Direction, 'ROLE_DIRECTION'];
        yield [Role::Lead, 'ROLE_LEAD'];
        yield [Role::Prod, 'ROLE_PROD'];
    }
}
