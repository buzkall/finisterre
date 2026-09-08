<?php

namespace Arzcode\Finisterre\Support;

use Filament\Facades\Filament;
use Filament\Panel;
use Throwable;

/**
 * The host application's Filament theme files and the `@source` lines
 * Finisterre needs in them.
 *
 * Following Filament v5 guidance the package ships raw CSS only: the Tailwind
 * utilities its Blade views use — and those of the flowforge kanban board it
 * renders — are compiled by the host's own theme. A panel with no theme at all
 * compiles nothing, so the board renders unstyled however many times
 * `npm run build` is run. The installer therefore has to know which panels
 * lack a theme, not only which theme files lack the lines.
 */
class FilamentThemes
{
    /**
     * The vendor view folders a theme has to scan, keyed by the fragment that
     * identifies each one in an existing `@source` line.
     */
    public const SOURCE_PATHS = [
        'arzcode/finisterre/resources/views'  => 'vendor/arzcode/finisterre/resources/views/**/*.blade.php',
        'relaticle/flowforge/resources/views' => 'vendor/relaticle/flowforge/resources/views/**/*.blade.php',
    ];

    /**
     * Every theme file the host has: the conventional
     * `resources/css/filament/<panel>/theme.css` files plus whatever other
     * path a panel registered with `viteTheme()`.
     *
     * @return list<string> absolute paths
     */
    public static function files(): array
    {
        $files = glob(resource_path('css/filament/*/theme.css')) ?: [];

        foreach (self::panelThemePaths() as $path) {
            if (is_file(base_path($path))) {
                $files[] = base_path($path);
            }
        }

        $files = array_values(array_unique($files));

        sort($files);

        return $files;
    }

    /**
     * The registered panels whose theme file does not exist, keyed by panel id
     * with the path (relative to the project root) the theme is expected at.
     * Empty when no panel is registered — the plain artisan context of a host
     * without Filament booted — as well as when every panel has its theme.
     *
     * @return array<string, string>
     */
    public static function panelsWithoutTheme(): array
    {
        return array_filter(
            self::panelThemePaths(),
            fn(string $path): bool => ! is_file(base_path($path))
        );
    }

    /**
     * The `@source` markers a theme file is still missing.
     *
     * @return list<string>
     */
    public static function missingSources(string $file): array
    {
        $contents = is_file($file) ? (string)file_get_contents($file) : '';

        return array_values(array_filter(
            array_keys(self::SOURCE_PATHS),
            fn(string $marker): bool => ! str_contains($contents, $marker)
        ));
    }

    /**
     * Append the missing `@source` lines to a theme file and return the markers
     * that were added. Idempotent: a file that has them all is left untouched.
     *
     * @return list<string>
     */
    public static function addSources(string $file): array
    {
        $missing = self::missingSources($file);

        if ($missing === []) {
            return [];
        }

        $contents = rtrim((string)file_get_contents($file), "\n");

        foreach ($missing as $marker) {
            $contents .= "\n" . self::sourceLine($file, $marker);
        }

        file_put_contents($file, $contents . "\n");

        return $missing;
    }

    /**
     * The `@source` line for a marker, relative to where the theme file sits.
     * Tailwind resolves `@source` against the stylesheet, so a theme at the
     * conventional depth gets the familiar `../../../../vendor/…` while one
     * somewhere else gets as many `../` as it takes to reach the project root.
     */
    public static function sourceLine(string $file, string $marker): string
    {
        $directory = str_replace('\\', '/', dirname($file));
        $base = rtrim(str_replace('\\', '/', base_path()), '/');

        $relative = str_starts_with($directory, $base . '/')
            ? substr($directory, strlen($base) + 1)
            : 'resources/css/filament/theme';

        $depth = count(array_filter(explode('/', $relative), fn(string $segment): bool => $segment !== ''));

        return sprintf("@source '%s%s';", str_repeat('../', $depth), self::SOURCE_PATHS[$marker]);
    }

    /**
     * The theme path each registered panel compiles, relative to the project
     * root: the `viteTheme()` it declares, or the conventional location when it
     * declares none.
     *
     * @return array<string, string>
     */
    protected static function panelThemePaths(): array
    {
        $paths = [];

        foreach (self::panels() as $panel) {
            $paths[$panel->getId()] = self::themePathFor($panel);
        }

        return $paths;
    }

    protected static function themePathFor(Panel $panel): string
    {
        $viteTheme = $panel->getViteTheme();

        if (is_string($viteTheme) && $viteTheme !== '') {
            return ltrim($viteTheme, '/');
        }

        // A panel may hand Vite several entries (a JS file alongside the theme):
        // the stylesheet among them is the theme.
        foreach (is_array($viteTheme) ? $viteTheme : [] as $entry) {
            if (str_ends_with($entry, '.css')) {
                return ltrim($entry, '/');
            }
        }

        return sprintf('resources/css/filament/%s/theme.css', $panel->getId());
    }

    /**
     * @return list<Panel>
     */
    protected static function panels(): array
    {
        if (! class_exists(Filament::class) || ! app()->bound('filament')) {
            return [];
        }

        try {
            return array_values(Filament::getPanels());
        } catch (Throwable) {
            return [];
        }
    }
}
