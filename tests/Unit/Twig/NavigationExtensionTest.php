<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Twig\NavigationExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class NavigationExtensionTest extends TestCase
{
    #[DataProvider('routeProvider')]
    public function testEachRouteLightsItsMenuEntry(?string $route, ?string $section): void
    {
        self::assertSame($section, new NavigationExtension()->navSection($route));
    }

    /**
     * @return \Generator<string, array{string|null, string|null}>
     */
    public static function routeProvider(): \Generator
    {
        yield 'dashboard' => ['app_page', 'dashboard'];
        yield 'current week' => ['app_timesheet', 'timesheet'];
        yield 'another week' => ['app_timesheet_week', 'timesheet'];
        yield 'roadmap' => ['app_roadmap', 'roadmap'];
        yield 'roadmap of another week' => ['app_roadmap_week', 'roadmap'];
        yield 'project list' => ['app_project_index', 'projects'];
        yield 'project page' => ['app_project_show', 'projects'];
        yield 'new lot' => ['app_lot_new', 'projects'];
        yield 'new sub-lot' => ['app_lot_new_sub_lot', 'projects'];
        yield 'lot edit' => ['app_lot_edit', 'projects'];
        yield 'team list' => ['app_team_index', 'team'];
        yield 'member edit' => ['app_team_edit', 'team'];
        yield 'current year' => ['app_holiday_index', 'holidays'];
        yield 'another year' => ['app_holiday_year', 'holidays'];
        yield 'tag list' => ['app_tag_index', 'tags'];
        yield 'tag rename' => ['app_tag_edit', 'tags'];
        yield 'account' => ['app_account_password', null];
        yield 'login' => ['app_login', null];
        yield 'no route' => [null, null];
    }
}
