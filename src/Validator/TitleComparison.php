<?php

declare(strict_types=1);

namespace App\Validator;

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
        $needle = mb_strtolower(trim($title));

        foreach ($existingTitles as $existingTitle) {
            if (mb_strtolower(trim($existingTitle)) === $needle) {
                return true;
            }
        }

        return false;
    }
}
