<?php

namespace Buzkall\Finisterre\Traits;

trait HasEnumFunctions
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function getLabel(): ?string
    {
        return __('finisterre::finisterre.' . $this->name);
    }

    public function getPluralLabel(): ?string
    {
        return __('finisterre::finisterre.' . str($this->name)->plural()->value());
    }

    public static function options(): array
    {
        return collect(static::cases())
            ->mapWithKeys(fn($item) => [$item->value => __($item->name)])
            ->toArray();
    }
}
