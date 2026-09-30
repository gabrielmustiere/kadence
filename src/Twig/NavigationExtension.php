<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Attribute\AsTwigFunction;

final class NavigationExtension
{
    /** Route name prefixes lighting each menu entry: a new route lights its entry when it follows the prefix. */
    private const array SECTIONS = [
        'dashboard' => ['app_page'],
        'timesheet' => ['app_timesheet'],
        'projects' => ['app_project_', 'app_lot_'],
        'team' => ['app_team_'],
        'holidays' => ['app_holiday_'],
    ];

    #[AsTwigFunction('nav_section')]
    public function navSection(?string $route): ?string
    {
        if (null === $route) {
            return null;
        }

        foreach (self::SECTIONS as $section => $prefixes) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($route, $prefix)) {
                    return $section;
                }
            }
        }

        return null;
    }
}
