<?php

namespace Arzcode\Finisterre\Filament\Actions;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas\FollowersSelect;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\TaskFollowers;
use Arzcode\Finisterre\Support\Typed;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;

/**
 * The followers of the task page as a stack of avatars, next to the assignee. One
 * modal for both directions: the field starts with the task's followers, and what
 * it holds when saved is who follows from then on.
 */
class QuickFollowersAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'quick_followers';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->badge()
            ->color('gray')
            ->icon(Heroicon::OutlinedUserPlus)
            ->label(fn(FinisterreTask $record): HtmlString => new HtmlString(
                view('finisterre::tasks.followers', ['followers' => TaskFollowers::users($record)])->render()
            ))
            ->tooltip(__('finisterre::finisterre.followers.manage'))
            ->visible(fn(): bool => TaskFollowers::available())
            ->modalHeading(__('finisterre::finisterre.followers.label'))
            ->modalDescription(__('finisterre::finisterre.followers.help'))
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('finisterre::finisterre.save'))
            ->fillForm(fn(FinisterreTask $record) => ['followers' => TaskFollowers::ids($record)])
            ->schema([
                FollowersSelect::make(),
            ])
            ->action(fn(array $data, FinisterreTask $record, ViewFinisterreTask $livewire) => $livewire->applyQuickUpdate(
                fn() => FollowersSelect::persist($record, Typed::array($data['followers'] ?? []))
            ));
    }
}
