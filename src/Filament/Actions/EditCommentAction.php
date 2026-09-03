<?php

namespace Arzcode\Finisterre\Filament\Actions;

use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Notifications\Notification;

class EditCommentAction extends Action
{
    public static function getDefaultName(): ?string
    {
        return 'editComment';
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->iconButton()
            ->icon('heroicon-s-pencil')
            ->color('warning')
            ->modalHeading(__('finisterre::finisterre.comments.edit_heading'))
            ->fillForm(function(array $arguments): array {
                $comment = FinisterreTaskComment::find($arguments['comment_id']);

                return [
                    'comment'       => $comment?->comment,
                    'scheduled_for' => $comment?->scheduled_for,
                ];
            })
            ->schema([
                Forms\Components\RichEditor::make('comment')
                    ->hiddenLabel()
                    ->fileAttachmentsDisk(config('finisterre.attachments_disk') ?? 'public')
                    ->extraInputAttributes(['style' => 'min-height: 6rem'])
                    ->required(),

                Forms\Components\DateTimePicker::make('scheduled_for')
                    ->visible(fn() => FinisterrePlugin::get()->canScheduleComments())
                    ->native(false)
                    ->suffixIcon('heroicon-o-calendar')
                    ->displayFormat('d/m/y H:i')
                    ->label(__('finisterre::finisterre.comments.scheduled_for'))
                    ->seconds(false)
                    ->minDate(today()),
            ])
            ->action(function(array $arguments, array $data) {
                $comment = FinisterreTaskComment::find($arguments['comment_id']);

                if (! $comment || ! auth()->user()->can('update', $comment)) {
                    return;
                }

                $comment->update([
                    'comment'       => $data['comment'],
                    'scheduled_for' => $data['scheduled_for'],
                ]);

                Notification::make()
                    ->title(__('finisterre::finisterre.comments.notifications.updated'))
                    ->success()
                    ->send();
            });
    }
}
