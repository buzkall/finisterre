<?php

namespace Buzkall\Finisterre\Enums;

use Buzkall\Finisterre\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;

enum TaskPriorityEnum: string implements HasLabel
{
    use HasEnumFunctions;

    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Urgent = 'urgent';

    public function color(): string
    {
        return match ($this) {
            self::Low    => 'bg-gray-200',
            self::Medium => 'bg-green-300',
            self::High   => 'bg-blue-300',
            self::Urgent => 'bg-red-300',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Low    => 'gray',
            self::Medium => 'success',
            self::High   => 'info',
            self::Urgent => 'danger',
        };
    }
}
