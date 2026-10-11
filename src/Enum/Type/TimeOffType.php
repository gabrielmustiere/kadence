<?php

declare(strict_types=1);

namespace App\Enum\Type;

enum TimeOffType: string
{
    case PaidLeave = 'cp';
    case Rtt = 'rtt';
    case Unpaid = 'unpaid';
    case FamilyEvent = 'family';

    public function label(): string
    {
        return match ($this) {
            self::PaidLeave => 'CP',
            self::Rtt => 'RTT',
            self::Unpaid => 'Congé sans solde',
            self::FamilyEvent => 'Événement familial',
        };
    }

    /**
     * Whether its days are counted against entitlements declared for a period.
     */
    public function hasBalance(): bool
    {
        return self::PaidLeave === $this || self::Rtt === $this;
    }

    /**
     * @return list<self>
     */
    public static function withBalance(): array
    {
        return array_values(array_filter(self::cases(), static fn (self $type): bool => $type->hasBalance()));
    }
}
