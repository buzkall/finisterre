<?php

namespace Buzkall\Finisterre\Filament\Livewire;

use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Buzkall\Finisterre\Notifications\TaskCommentNotification;
use Filament\Forms;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * @property ComponentContainer $form
 */
class FinisterreCommentsComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public ?FinisterreTask $record = null;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Form $form): Form
    {
        if (! auth()->user()->can('create', FinisterreTaskComment::class)) {
            return $form;
        }

        if (config('finisterre.comments.editor') === 'markdown') {
            $editor = Forms\Components\MarkdownEditor::make('comment');
        } else {
            $editor = Forms\Components\RichEditor::make('comment')
                ->fileAttachmentsVisibility('private')
                ->extraInputAttributes(['style' => 'min-height: 6rem']);
        }

        $editor
            ->hiddenLabel()
            ->autofocus() // this solves the problem of losing focus when clicking on the editor
            ->required()
            ->placeholder(__('finisterre::finisterre.comments.placeholder'))
            ->toolbarButtons(config('finisterre.comments.toolbar_buttons'));

        return $form
            ->schema([
                $editor,

                Forms\Components\Select::make('notify')
                    ->multiple()
                    ->label(__('finisterre::finisterre.comments.notify'))
                    ->hint(__('finisterre::finisterre.comments.notify_hint'))
                    ->options(
                        fn() => config('finisterre.authenticatable')::query()
                            ->where('id', '!=', auth()->id())
                            ->when(
                                config('finisterre.authenticatable_filter_column'),
                                fn($query) => $query->where(config('finisterre.authenticatable_filter_column'), config('finisterre.authenticatable_filter_value'))
                            )->pluck('name', 'id')
                    )
                ])
            ->statePath('data');
    }

    public function create(): void
    {
        if (! auth()->user()->can('create', FinisterreTaskComment::class)) {
            return;
        }

        $this->form->validate();

        $data = $this->form->getState();

        $this->record->comments()->create([
            'comment'    => $data['comment'],
            'creator_id' => auth()->id(),
        ]);

        Notification::make()
            ->title(__('finisterre::finisterre.comments.notifications.created'))
            ->success()
            ->send();

        if ($data['notify']) {
            foreach (config('finisterre.authenticatable')::findMany($data['notify']) as $user) {
                $this->notifyUser($user);
            }
        } else {
            $this->notifyUser($this->record->assignee);
        }

        $this->form->fill();

        $this->dispatch('commentCreated')->to(TasksKanbanBoard::class);
    }

    private function notifyUser($user): void
    {
        $user->notify(new TaskCommentNotification($this->record));

        $latestComment = $this->record->comments->last();
        $body = (new HtmlString($latestComment->comment));

        Notification::make()
            ->title(__(
                'finisterre::finisterre.comment_notification.subject',
                ['title' => $this->record->title]
            ))
            ->body($body)
           /* ->actions([
                Action::make('view')
                    ->button()
                    ->markAsRead(),
            ])*/
            ->sendToDatabase($user);
    }

    public function delete(int $id): void
    {
        $comment = FinisterreTaskComment::find($id);

        if (! $comment || ! auth()->user()->can('delete', $comment)) {
            return;
        }

        $comment->delete();

        Notification::make()
            ->title(__('finisterre::finisterre.comments.notifications.deleted'))
            ->success()
            ->send();
    }

    #[Computed]
    public function comments()
    {
        // record will be empty on the kanban load. We'll get the value from the view
        // when the modal is opened
        return $this->record?->comments()->with('creator')->latest()->get() ?? collect();
    }

    public function render(): View
    {
        return view('finisterre::comments.comments');
    }
}
