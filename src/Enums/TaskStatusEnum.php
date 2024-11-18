<?php

namespace Buzkall\Finisterre\Enums;

use Buzkall\Finisterre\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Collection;
use Mokhosh\FilamentKanban\Concerns\IsKanbanStatus;
use Override;

enum TaskStatusEnum: string implements HasLabel
{
    use HasEnumFunctions;
    use IsKanbanStatus;

    case Open = 'open';
    case Doing = 'doing';
    case OnHold = 'on_hold';
    case Done = 'done';
    case Rejected = 'rejected';
    case Backlog = 'backlog';

    public function getTitle(): string
    {
        return $this->getLabel();
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

    public static function statuses(): Collection
    {
        // override the method from IsKanbanStatus trait to filter statuses
        return self::filteredCases()
            ->map(fn(self $item) => [
                'id'    => $item->value,
                'title' => $item->getTitle(),
            ]);
    }
}
