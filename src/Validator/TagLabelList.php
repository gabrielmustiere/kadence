<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Comma-separated tag labels, each short enough to be a tag.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY)]
final class TagLabelList extends Constraint
{
    public const int MAX_LENGTH = 60;

    public string $message = '« {{ label }} » dépasse {{ limit }} caractères : raccourcissez-le ou séparez les tags par des virgules.';
}
