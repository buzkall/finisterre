<?php

namespace Buzkall\Finisterre\Enums;

use Buzkall\Finisterre\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;

enum TaskStateEnum: string implements HasLabel
{
    use HasEnumFunctions;

    case Open = 'open';
    case OnHold = 'on_hold';
    case Doing = 'doing';
    case Done = 'done';
}
