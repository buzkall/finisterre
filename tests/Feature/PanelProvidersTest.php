<?php

use Arzcode\Finisterre\Support\PanelProviders;

/**
 * The install and uninstall commands patch the host's panel providers through
 * PanelProviders, so the patching is covered here without touching any file.
 */
function panelProvider(string $chain, bool $withImport = true): string
{
    $import = $withImport ? "use Arzcode\\Finisterre\\FinisterrePlugin;\n" : '';

    return "<?php\n\nnamespace App\\Providers\\Filament;\n\nuse Filament\\Panel;\n{$import}\nclass AdminPanelProvider\n{\n    public function panel(Panel \$panel): Panel\n    {\n        return \$panel\n{$chain};\n    }\n}\n";
}

it('adds the plugin to an existing plugins array', function() {
    $patched = PanelProviders::addPlugin(panelProvider("            ->plugins([\n                A::make(),\n            ])", withImport: false));

    expect($patched)->toBe(panelProvider("            ->plugins([\n                A::make(),\n                FinisterrePlugin::make(),\n            ])"))
        ->and(PanelProviders::parses($patched))->toBeTrue();
});

it('adds a plugins call to a panel provider that has none', function() {
    $patched = PanelProviders::addPlugin(panelProvider("            ->id('admin')", withImport: false));

    expect($patched)->toBe(panelProvider("            ->id('admin')\n            ->plugins([\n                FinisterrePlugin::make(),\n            ])"));
});

it('ignores brackets and quotes inside comments when adding the plugin', function() {
    $chain = "            /* don't close ]; here */\n            # nor ']' here\n            ->plugins([\n                A::make(), // ]\n            ])";

    $patched = PanelProviders::addPlugin(panelProvider($chain, withImport: false));

    expect(PanelProviders::parses($patched))->toBeTrue()
        ->and($patched)->toContain("A::make(), // ]\n                FinisterrePlugin::make(),\n            ])");
});

it('removes only the plugin entry, keeping the other plugins', function(string $before, string $after) {
    $removed = PanelProviders::removePlugin(panelProvider($before));

    expect($removed)->toBe(panelProvider($after, withImport: false))
        ->and(PanelProviders::parses($removed))->toBeTrue();
})->with([
    'single line, last'  => ['            ->plugins([A::make(), FinisterrePlugin::make()])', '            ->plugins([A::make()])'],
    'single line, first' => ['            ->plugins([FinisterrePlugin::make(), A::make()])', '            ->plugins([A::make()])'],
    'own line, chained'  => [
        "            ->plugins([\n                A::make(),\n                FinisterrePlugin::make()\n                    ->foo(['x']),\n                B::make(),\n            ])",
        "            ->plugins([\n                A::make(),\n                B::make(),\n            ])",
    ],
    'only plugin'         => ["            ->id('a')\n            ->plugins([\n                FinisterrePlugin::make(),\n            ])", "            ->id('a')"],
    'plugin call'         => ["            ->id('a')\n            ->plugin(FinisterrePlugin::make())\n            ->path('a')", "            ->id('a')\n            ->path('a')"],
    'plugin call, inline' => ["            ->id('a')->plugin(FinisterrePlugin::make()->foo())->path('a')", "            ->id('a')->path('a')"],
]);

it('tells a broken file apart from a valid one', function() {
    expect(PanelProviders::parses(panelProvider("            ->id('a')")))->toBeTrue()
        ->and(PanelProviders::parses(panelProvider("            ->id('a'")))->toBeFalse();
});
