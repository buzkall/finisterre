<?php

namespace Arzcode\Finisterre\Filament\Actions;

use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\TaskAccess;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

class DeleteCommentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'deleteComment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        // A comment is deleted for good and its notifications are already out, so a
        // stray click next to the edit button must not be enough.
        $this->iconButton()
            ->icon('heroicon-s-trash')
            ->color('danger')
            ->tooltip(__('finisterre::finisterre.comments.delete'))
            ->requiresConfirmation()
            ->modalHeading(__('finisterre::finisterre.comments.delete'))
            ->modalDescription(__('finisterre::finisterre.comments.delete_confirmation'))
            ->modalSubmitActionLabel(__('finisterre::finisterre.comments.delete'))
            ->action(function(array $arguments, FinisterreCommentsComponent $livewire) {
                // The id comes from the browser: only a comment of the task on screen
                // can be found, whatever id is sent.
                $comment = FinisterreTaskComment::query()
                    ->whereKey($arguments['comment_id'] ?? null)
                    ->where('task_id', $livewire->record?->getKey())
                    ->first();

                if (! $comment || ! TaskAccess::user()?->can('delete', $comment)) {
                    return;
                }

                $comment->delete();

                Notification::make()
                    ->title(__('finisterre::finisterre.comments.notifications.deleted'))
                    ->success()
                    ->send();
            });
    }
}
