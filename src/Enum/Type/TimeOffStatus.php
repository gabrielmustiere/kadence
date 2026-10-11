<?php

declare(strict_types=1);

namespace App\Enum\Type;

enum TimeOffStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Refused = 'refused';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'En attente',
            self::Approved => 'Validé',
            self::Refused => 'Refusé',
            self::Cancelled => 'Annulé',
        };
    }

    /**
     * Pending or approved: its days are locked in the timesheet and no other request may cover them.
     */
    public function isActive(): bool
    {
        return self::Pending === $this || self::Approved === $this;
    }
}
