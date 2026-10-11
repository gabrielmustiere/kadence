<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use App\Model\TimeOff\Balance;
use Symfony\Component\Validator\Constraints as Assert;

final class TimeOffEntitlementsInput
{
    /** @var array<int, float|null> the entitlements in days, by user id */
    #[Assert\All([
        new Assert\NotNull(message: 'Indiquez les droits, 0 s\'il n\'y en a pas.'),
        new Assert\PositiveOrZero(message: 'Les droits ne sont jamais négatifs.'),
        new Assert\DivisibleBy(0.5, message: 'Les droits se déclarent au demi-jour près.'),
    ])]
    public array $days = [];

    /**
     * @param array<int, Balance> $balances by user id
     */
    public static function fromBalances(array $balances): self
    {
        $input = new self();
        $input->days = array_map(static fn (Balance $balance): float => $balance->entitledHalfDays / 2, $balances);

        return $input;
    }

    /**
     * @param list<User> $people
     *
     * @return list<array{User, int<0, max>}> each person with their entitlements in half-days
     */
    public function halfDaysOf(array $people): array
    {
        return array_map(fn (User $person): array => [$person, max(0, (int) round(($this->days[(int) $person->getId()] ?? 0) * 2))], $people);
    }
}
