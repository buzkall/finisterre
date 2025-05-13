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
        if (trans()->has('finisterre::finisterre.' . $this->name)) {
            __('finisterre::finisterre.' . $this->name);
        }

        return __($this->name);
    }

    public function getPluralLabel(): ?string
    {
        if (trans()->has('finisterre::finisterre.' . str($this->name)->plural()->value())) {
            return __('finisterre::finisterre.' . str($this->name)->plural()->value());
        }

        return __(str($this->name)->plural()->value());
    }

    public static function options(): array
    {
        return collect(static::cases())
            ->mapWithKeys(fn($item) => [$item->value => __($item->name)])
            ->toArray();
    }
}
