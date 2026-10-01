<?php

namespace Arzcode\Finisterre\Enums;

use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Traits\HasEnumFunctions;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;
use Illuminate\Support\Collection;

enum TaskStatusEnum: string implements HasColor, HasLabel
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

    /** @return Collection<int, self::*> */
    public static function filteredCases(): Collection
    {
        $hidden = Typed::array(config('finisterre.hidden_statuses'));

        return collect(self::cases())
            ->when(
                $hidden !== [],
                fn(Collection $collection) => $collection
                    ->reject(fn(self $status) => in_array($status->value, $hidden))
                    ->values()
            );
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::filteredCases()
            ->mapWithKeys(fn(self $item) => [$item->value => $item->getLabel()])
            ->all();
    }
}
