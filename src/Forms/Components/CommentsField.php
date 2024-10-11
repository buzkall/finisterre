<?php

namespace Buzkall\Finisterre\Forms\Components;

use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Filament\Forms\Components\Field;
use Filament\Notifications\Notification;

class CommentsField extends Field
{
    protected string $view = 'finisterre::forms.components.comments-field';
    protected array $comments = [];

    public function getComments()
    {
        $record = $this->getRecord();

        return $record?->comments()->with('creator')->get() ?? [];
    }

    public function delete(int $id): void
    {
        $comment = FinisterreTaskComment::find($id);

        if (! $comment) {
            return;
        }

        if (! auth()->user()->can('delete', $comment)) {
            return;
        }

        $comment->delete();

        Notification::make()
            ->title(__('finisterre::finisterre.comments.notifications.deleted'))
            ->success()
            ->send();
    }
}
