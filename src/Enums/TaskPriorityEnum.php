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

    public function color(): string
    {
        return match ($this) {
            self::Low    => 'bg-gray-200',
            self::Medium => 'bg-blue-300',
            self::High   => 'bg-red-300',
        };
    }
}
