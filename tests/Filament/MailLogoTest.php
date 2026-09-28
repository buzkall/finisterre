<?php

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Filament\Facades\Filament;
use Illuminate\Support\HtmlString;
use Workbench\App\Models\User;

function renderedTaskMail(): string
{
    $task = FinisterreTask::factory()->create(['title' => 'Logo task']);

    return (string)(new TaskNotification($task))->toMail(User::factory()->create())->render();
}

it('shows the panel brand logo in the mail header', function() {
    Filament::getPanel('admin')->brandLogo('/img/logo.png');

    expect(renderedTaskMail())->toContain('<img src="' . url('/img/logo.png') . '"');
});

it('falls back to the app name when the panel has no logo', function() {
    Filament::getPanel('admin')->brandLogo(null);

    expect(renderedTaskMail())
        ->not->toContain('/img/logo.png')
        ->toContain(config('app.name'));
});

it('skips an inline svg logo, which mail clients do not render', function() {
    Filament::getPanel('admin')->brandLogo(new HtmlString('<svg id="brand"></svg>'));

    expect(renderedTaskMail())->not->toContain('<svg id="brand">');
});
