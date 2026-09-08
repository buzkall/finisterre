<?php

use Arzcode\Finisterre\Support\FilamentThemes;
use Illuminate\Support\Facades\File;

beforeEach(function() {
    $this->themesPath = resource_path('css/filament');

    File::deleteDirectory($this->themesPath);
});

afterEach(function() {
    File::deleteDirectory($this->themesPath);
});

function writeTheme(string $panel, string $contents = "@import '../../../../vendor/filament/filament/resources/css/theme.css';\n"): string
{
    $file = resource_path("css/filament/{$panel}/theme.css");

    File::ensureDirectoryExists(dirname($file));
    file_put_contents($file, $contents);

    return $file;
}

it('finds every theme file under resources/css/filament', function() {
    $admin = writeTheme('admin');
    $client = writeTheme('client');

    expect(FilamentThemes::files())->toBe([$admin, $client]);
});

it('reports both @source markers as missing on a fresh theme', function() {
    $file = writeTheme('admin');

    expect(FilamentThemes::missingSources($file))->toBe([
        'arzcode/finisterre/resources/views',
        'relaticle/flowforge/resources/views',
    ]);
});

it('appends the @source lines relative to where the theme sits', function() {
    $file = writeTheme('admin');

    expect(FilamentThemes::addSources($file))->toBe([
        'arzcode/finisterre/resources/views',
        'relaticle/flowforge/resources/views',
    ]);

    $contents = file_get_contents($file);

    expect($contents)
        ->toContain("@source '../../../../vendor/arzcode/finisterre/resources/views/**/*.blade.php';")
        ->toContain("@source '../../../../vendor/relaticle/flowforge/resources/views/**/*.blade.php';")
        ->toEndWith("\n")
        ->and(FilamentThemes::missingSources($file))->toBe([]);
});

it('leaves a theme alone once it has the lines', function() {
    $file = writeTheme('admin');
    FilamentThemes::addSources($file);
    $before = file_get_contents($file);

    expect(FilamentThemes::addSources($file))->toBe([])
        ->and(file_get_contents($file))->toBe($before);
});

it('only adds the marker a theme is missing', function() {
    $file = writeTheme('admin', "@source '../../../../vendor/arzcode/finisterre/resources/views/**/*.blade.php';\n");

    expect(FilamentThemes::addSources($file))->toBe(['relaticle/flowforge/resources/views'])
        ->and(substr_count((string)file_get_contents($file), '@source'))->toBe(2);
});

it('climbs as many directories as the theme is deep', function() {
    expect(FilamentThemes::sourceLine(resource_path('css/theme.css'), 'arzcode/finisterre/resources/views'))
        ->toBe("@source '../../vendor/arzcode/finisterre/resources/views/**/*.blade.php';");
});

it('knows no panel is missing a theme when Filament has no panels registered', function() {
    expect(FilamentThemes::panelsWithoutTheme())->toBe([]);
});
