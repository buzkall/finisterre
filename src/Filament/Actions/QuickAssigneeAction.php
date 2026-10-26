<?php

namespace Arzcode\Finisterre\Filament\Actions;

use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas\UserPicker;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Support\UserModel;
use Closure;
use Filament\Actions\Action;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;

/**
 * The assignee badge of the task page: a modal with the people who can be assigned
 * (see UserPicker), rather than a dropdown with an action built per user.
 */
class QuickAssigneeAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'quick_assignee';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->badge()
            ->color('gray')
            ->icon(Heroicon::OutlinedUser)
            ->label(fn(FinisterreTask $record) => $record->assigneeName() ?? __('finisterre::finisterre.unassigned'))
            ->tooltip(__('finisterre::finisterre.assignee_id'))
            ->modalHeading(__('finisterre::finisterre.assignee_id'))
            ->modalWidth(Width::Small)
            ->modalSubmitActionLabel(__('finisterre::finisterre.save'))
            ->fillForm(fn(FinisterreTask $record) => ['assignee_id' => $record->assignee_id])
            ->schema([
                UserPicker::make('assignee_id', fn() => UserModel::assignableQuery(), icon: Heroicon::OutlinedUser)
                    ->hiddenLabel()
                    ->required()
                    // The current assignee may stay even when no longer assignable (made
                    // inactive, say); anybody else has to be somebody who can be assigned.
                    ->rule(fn(FinisterreTask $record): Closure => function(string $attribute, mixed $value, Closure $fail) use ($record): void {
                        if (Typed::nullableInt($value) !== $record->assignee_id && ! UserModel::assignableQuery()->whereKey($value)->exists()) {
                            $fail(__('finisterre::finisterre.assignee_not_assignable'));
                        }
                    }),
            ])
            ->action(function(array $data, FinisterreTask $record, ViewFinisterreTask $livewire): void {
                // The rule above has already turned away anybody who cannot be assigned.
                // Keeping the current assignee changes nothing.
                $assigneeId = Typed::nullableInt($data['assignee_id'] ?? null);

                if ($assigneeId === null || $assigneeId === $record->assignee_id) {
                    return;
                }

                $livewire->applyQuickUpdate(fn() => $record->update(['assignee_id' => $assigneeId]));
            });
    }
}
