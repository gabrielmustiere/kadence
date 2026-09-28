<?php

declare(strict_types=1);

namespace App\Service;

use Symfony\Component\String\ByteString;

final class TemporaryPasswordGenerator
{
    public const string ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
    public const int LENGTH = 12;

    public function generate(): string
    {
        return ByteString::fromRandom(self::LENGTH, self::ALPHABET)->toString();
    }
}
