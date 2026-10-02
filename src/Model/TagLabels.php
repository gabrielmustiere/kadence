<?php

declare(strict_types=1);

namespace App\Model;

final class TagLabels
{
    /**
     * @return list<non-empty-string> the comma-separated labels, trimmed, without blanks nor duplicates (case ignored)
     */
    public static function split(?string $labels): array
    {
        $split = [];
        foreach (explode(',', $labels ?? '') as $label) {
            $label = trim($label);
            if ('' !== $label) {
                $split[TitleComparison::normalize($label)] ??= $label;
            }
        }

        return array_values($split);
    }
}
