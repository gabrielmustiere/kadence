<?php

declare(strict_types=1);

namespace App\Model;

/**
 * Case-insensitive comparison done in PHP: SQLite's LOWER() only folds ASCII letters (« Été » ≠ « été »).
 */
final class TitleComparison
{
    /**
     * @param list<string> $existingTitles
     */
    public static function isTaken(string $title, array $existingTitles): bool
    {
        $needle = self::normalize($title);

        foreach ($existingTitles as $existingTitle) {
            if (self::normalize($existingTitle) === $needle) {
                return true;
            }
        }

        return false;
    }

    public static function normalize(string $title): string
    {
        return mb_strtolower(trim($title));
    }
}
