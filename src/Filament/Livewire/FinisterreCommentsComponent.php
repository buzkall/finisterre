<?php

namespace Buzkall\Finisterre\Filament\Livewire;

use Buzkall\Finisterre\Filament\Pages\TasksKanbanBoard;
use Buzkall\Finisterre\Models\FinisterreTask;
use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Buzkall\Finisterre\Notifications\TaskCommentNotification;
use Filament\Actions\Action;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FinisterreCommentsComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public ?FinisterreTask $record = null;

    public function mount(): void
    {
        $options = $this->getNotifyOptions();

        $this->form->fill([
            'notify' => $options->count() === 1 ? $options->keys()->toArray() : [],
        ]);
    }

    private function getNotifyOptions()
    {
        $options = config('finisterre.authenticatable')::query()
            ->where('id', '!=', auth()->id())
            ->when(
                config('finisterre.authenticatable_filter_column'),
                fn($query) => $query->where(config('finisterre.authenticatable_filter_column'), config('finisterre.authenticatable_filter_value'))
            )
            ->when(
                DatabaseSchema::hasColumn(config('finisterre.authenticatable_table_name'), 'active'),
                fn($query) => $query->where('active', true)
            )
            ->pluck(config('finisterre.authenticatable_attribute'), 'id');

        // Append task creator if not the authenticated user and not already in options
        if ($this->record->creator->getKey() !== auth()->id() &&
            ! $options->has($this->record->creator->getKey())) {
            $options->put($this->record->creator->getKey(), $this->record->creatorName());
        }

        return $options;
    }

    public function form(Schema $schema): Schema
    {
        if (! auth()->user()->can('create', FinisterreTaskComment::class)) {
            return $schema;
        }

        if (config('finisterre.comments.editor') === 'markdown') {
            $editor = Forms\Components\MarkdownEditor::make('comment');
        } else {
            // images pasted are private because handled in the finisterre-files folder and route
            $editor = Forms\Components\RichEditor::make('comment')
                ->fileAttachmentsDisk(config('finisterre.attachments_disk') ?? 'public')
                ->extraInputAttributes(['style' => 'min-height: 6rem']);
        }

        $editor
            ->hiddenLabel()
            ->required()
            ->placeholder(__('finisterre::finisterre.comments.placeholder'))
            ->toolbarButtons(config('finisterre.comments.toolbar_buttons'));

        return $schema->components([
            $editor,

            Forms\Components\Select::make('notify')
                ->multiple()
                ->label(__('finisterre::finisterre.comments.notify'))
                ->hint(__('finisterre::finisterre.comments.notify_hint'))
                ->options(fn() => $this->getNotifyOptions())
        ])->statePath('data');
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

        $notified = collect();
        $notifiedUserIds = collect();

        if ($data['notify']) {
            foreach (config('finisterre.authenticatable')::findMany($data['notify']) as $user) {
                $this->notifyUser($user);
                $notified->push($user->{config('finisterre.authenticatable_attribute')});
                $notifiedUserIds->push($user->getKey());
            }
        } else {
            $this->notifyUser($this->record->assignee);
            $notified->push($this->record->assignee->{config('finisterre.authenticatable_attribute')});
            $notifiedUserIds->push($this->record->assignee->getKey());
        }

        // Create task change records for notified users
        foreach ($notifiedUserIds as $userId) {
            if ($userId !== auth()->id()) {
                $this->record->taskChanges()->firstOrCreate(['user_id' => $userId]);
            }
        }

        Notification::make()
            ->title($notified->isEmpty() ?
                __('finisterre::finisterre.comments.notifications.created') :
                __('finisterre::finisterre.comments.notifications.created_and_notified', ['notified' => $notified->implode(', ')]))
            ->success()
            ->send();

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
            ->actions([
                Action::make('view')
                    ->label(__('finisterre::finisterre.comment_notification.cta'))
                    ->button()
                    ->url(route('filament.' . config('finisterre.panel_slug') . '.resources.finisterre-tasks.edit', $this->record)),
            ])
            ->sendToDatabase($user);
    }

    public function delete(int $id): void
    {
        $comment = FinisterreTaskComment::find($id);

        if (! $comment || ! auth()->guard(config('finisterre.guard'))->user()->can('delete', $comment)) {
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
