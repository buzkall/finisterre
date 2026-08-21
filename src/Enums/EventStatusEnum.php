<?php

namespace Arzcode\Finisterre\Enums;

use Arzcode\Finisterre\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;

enum EventStatusEnum: string implements HasLabel
{
    use HasEnumFunctions;

    case Draft = 'draft';
    case Scheduling = 'scheduling';
    case PendingConfirmation = 'pending_confirmation';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function getColor(): string
    {
        return match ($this) {
            self::Draft               => 'gray',
            self::Scheduling          => 'info',
            self::PendingConfirmation => 'warning',
            self::Scheduled           => 'primary',
            self::Completed           => 'success',
            self::Cancelled           => 'danger',
        };
    }

    /** Statuses where attendees can still pick their availability. */
    public function acceptsAvailability(): bool
    {
        return in_array($this, [self::Scheduling, self::PendingConfirmation]);
    }
}
