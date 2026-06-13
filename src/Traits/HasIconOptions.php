<?php

namespace Buzkall\Finisterre\Traits;

use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use ReflectionEnum;
use ReflectionEnumBackedCase;

trait HasIconOptions
{
    /** @var array<string, string>|null */
    protected static ?array $iconOptionsCache = null;

    /**
     * Heroicon (outline) options for an allowHtml select, keyed by the blade-icons
     * identifier (e.g. "heroicon-o-trash") and labelled with an inline SVG preview.
     * Static — so a searchable select preloads them without a search callback.
     *
     * @return array<string, string>
     */
    protected static function getIconOptions(): array
    {
        return self::$iconOptionsCache ??= collect((new ReflectionEnum(Heroicon::class))->getCases())
            ->filter(fn(ReflectionEnumBackedCase $case): bool => str_starts_with($case->name, 'Outlined'))
            ->mapWithKeys(function(ReflectionEnumBackedCase $case): array {
                $value = (string)$case->getBackingValue(); // e.g. "o-trash"
                $label = Str::headline(str_replace('Outlined', '', $case->name));

                return ['heroicon-' . $value => self::renderIconOption($value, $label)];
            })
            ->all();
    }

    /**
     * Render the label for a stored icon, tolerating any heroicon style (solid,
     * outline, mini) so legacy values still display in the select.
     */
    protected static function iconOptionLabel(?string $icon): ?string
    {
        if (! $icon) {
            return null;
        }

        $value = (string)Str::after($icon, 'heroicon-');

        return self::renderIconOption($value, Str::headline(Str::after($value, '-')));
    }

    protected static function renderIconOption(string $value, string $label): string
    {
        $path = base_path('vendor/blade-ui-kit/blade-heroicons/resources/svg/' . $value . '.svg');

        $svg = is_file($path)
            ? (string)preg_replace(
                '/<svg /',
                '<svg style="width:1.25rem;height:1.25rem;display:inline-block;vertical-align:middle;margin-right:0.5rem;" ',
                (string)file_get_contents($path)
            )
            : '';

        return $svg . '<span style="vertical-align:middle;">' . e($label) . '</span>';
    }
}
