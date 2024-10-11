<?php

namespace Buzkall\Finisterre\Filament\Livewire;

use Buzkall\Finisterre\Models\FinisterreTaskComment;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Component;

class FinisterreCommentsComponent extends Component implements HasForms
{
    use InteractsWithForms;

    public ?array $data = [];
    public ?Model $record = null;

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
                ->extraInputAttributes(['style' => 'min-height: 6rem']);
        }

        $editor
            ->hiddenLabel()
            ->required()
            ->placeholder(__('finisterre::finisterre.comments.placeholder'))
            ->toolbarButtons(config('finisterre.comments.toolbar_buttons'));

        return $form
            ->schema([$editor])
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

        $this->form->fill();
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
        return view('finisterre::comments');
    }
}
