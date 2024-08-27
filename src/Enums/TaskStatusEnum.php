<?php

namespace Buzkall\Finisterre\Enums;

use Buzkall\Finisterre\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;
use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;

enum TaskStatusEnum: string implements HasLabel
{
    use HasEnumFunctions;
    use IsKanbanStatus;

    case Open = 'open';
    case OnHold = 'on_hold';
    case Doing = 'doing';
    case Done = 'done';

    public function getTitle(): string
    {
        return $this->getLabel();
    }
}
