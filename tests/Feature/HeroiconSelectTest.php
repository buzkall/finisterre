<?php

use Buzkall\Finisterre\Filament\Pages\ManageFinisterreSettings;

function invokeHeroiconMethod(string $method, mixed ...$args): mixed
{
    $page = (new ReflectionClass(ManageFinisterreSettings::class))->newInstanceWithoutConstructor();
    $reflection = new ReflectionMethod($page, $method);
    $reflection->setAccessible(true);

    return $reflection->invoke($page, ...$args);
}

it('builds outline heroicon options keyed by blade-icons identifier with a readable label', function() {
    $options = invokeHeroiconMethod('getIconOptions');

    // The inline SVG preview depends on the blade-heroicons files resolving under
    // base_path(), which differs under testbench — so assert structure, not markup.
    expect($options)->toHaveKey('heroicon-o-trash')
        ->toHaveKey('heroicon-o-chat-bubble-left-right')
        ->and($options)->not->toHaveKey('heroicon-s-trash')
        ->and($options['heroicon-o-trash'])->toContain('Trash')
        ->and(collect($options)->keys()->every(fn(string $id): bool => str_starts_with($id, 'heroicon-o-')))->toBeTrue();
});

it('renders a label for any stored heroicon style, including legacy solid values', function() {
    expect(invokeHeroiconMethod('iconOptionLabel', 'heroicon-s-trash'))->toContain('Trash')
        ->and(invokeHeroiconMethod('iconOptionLabel', null))->toBeNull();
});
