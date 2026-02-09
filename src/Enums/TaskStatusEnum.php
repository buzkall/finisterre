<?php

namespace Buzkall\Finisterre\Enums;

use Buzkall\Finisterre\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Collection;

enum TaskStatusEnum: string implements HasLabel
{
    use HasEnumFunctions;

    case Open = 'open';
    case Doing = 'doing';
    case OnHold = 'on_hold';
    case ToDeploy = 'to_deploy';
    case Done = 'done';
    case Rejected = 'rejected';
    case Backlog = 'backlog';

    public function getTitle(): string
    {
        return $this->getLabel();
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Open     => 'gray',
            self::Doing    => 'info',
            self::OnHold   => 'warning',
            self::ToDeploy => 'primary',
            self::Done     => 'success',
            self::Rejected => 'danger',
            self::Backlog  => 'gray',
        };
    }

    public static function filteredCases(): Collection
    {
        return collect(self::cases())
            ->when(
                config('finisterre.hidden_statuses') !== [],
                fn($collection) => $collection
                    ->reject(fn($status) => in_array($status->value, config('finisterre.hidden_statuses')))
                    ->values()
            );
    }

    public static function options(): array
    {
        return self::filteredCases()
            ->mapWithKeys(fn($item) => [$item->value => $item->getLabel()])
            ->toArray();
    }
}
