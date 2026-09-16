<?php

namespace Arzcode\Finisterre\Filament\Livewire;

use Arzcode\Finisterre\Filament\Actions\EditCommentAction;
use Arzcode\Finisterre\Filament\Actions\PostponeCommentAction;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\AuthenticatableFilter;
use Arzcode\Finisterre\Support\EditorFiles;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema as DatabaseSchema;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property-read Schema $form
 */
class FinisterreCommentsComponent extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    public ?array $data = [];
    public ?FinisterreTask $record = null;

    public function mount(): void
    {
        $this->fillWithDefaults();
    }

    private function fillWithDefaults(): void
    {
        $this->form->fill([
            'notify'        => $this->getDefaultNotifyIds(),
            'scheduled_for' => null,
        ]);
    }

    /**
     * The task creator is notified by default, so whoever opened the task hears
     * back about it without having to remember to pick them.
     */
    private function getDefaultNotifyIds(): array
    {
        $options = $this->getNotifyOptions();

        if ($options->count() === 1) {
            return $options->keys()->toArray();
        }

        $creatorId = $this->record?->creator_id;

        return $creatorId && $options->has($creatorId) ? [$creatorId] : [];
    }

    private function isAllNotifySelected(Get $get): bool
    {
        $selected = $get('notify') ?? [];

        return count($selected) >= $this->getNotifyOptions()->count();
    }

    private function getNotifyOptions()
    {
        $options = config('finisterre.authenticatable')::query()
            ->where('id', '!=', auth()->id())
            ->when(
                config('finisterre.authenticatable_filter_column'),
                fn($query) => $query->whereIn(config('finisterre.authenticatable_filter_column'), AuthenticatableFilter::values())
            )
            ->when(
                DatabaseSchema::hasColumn(config('finisterre.authenticatable_table_name'), 'active'),
                fn($query) => $query->where('active', true)
            )
            ->get()
            ->mapWithKeys(fn($user) => [$user->getKey() => $user->getUserDisplayName()]);

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

        $canSchedule = FinisterrePlugin::get()->canScheduleComments();

        $notifyOptions = $this->getNotifyOptions();

        $notify = Select::make('notify')
            ->multiple()
            ->live()
            ->columnSpan($canSchedule ? 1 : 'full')
            ->label(__('finisterre::finisterre.comments.notify'))
            ->hint(__('finisterre::finisterre.comments.notify_hint'))
            ->options(fn() => $this->getNotifyOptions())
            ->suffixAction(
                Action::make('toggleSelectAll')
                    ->icon(fn(Get $get): string => $this->isAllNotifySelected($get)
                        ? 'heroicon-m-user-minus'
                        : 'heroicon-m-users')
                    ->label(fn(Get $get): string => $this->isAllNotifySelected($get)
                        ? __('finisterre::finisterre.comments.deselect_all')
                        : __('finisterre::finisterre.comments.select_all'))
                    ->tooltip(fn(Get $get): string => $this->isAllNotifySelected($get)
                        ? __('finisterre::finisterre.comments.deselect_all')
                        : __('finisterre::finisterre.comments.select_all'))
                    ->visible($notifyOptions->count() > 1)
                    ->action(fn(Get $get, Set $set) => $set(
                        'notify',
                        $this->isAllNotifySelected($get) ? [] : $this->getNotifyOptions()->keys()->toArray()
                    ))
            );

        $gridComponents = $canSchedule
            ? [
                $notify,
                DateTimePicker::make('scheduled_for')
                    ->native(false)
                    ->suffixIcon('heroicon-o-calendar')
                    ->displayFormat('d/m/y H:i')
                    ->columnSpan(1)
                    ->label(__('finisterre::finisterre.comments.scheduled_for'))
                    ->hint(__('finisterre::finisterre.comments.scheduled_for_hint'))
                    ->seconds(false)
                    ->minDate(today()),
            ]
            : [$notify];

        return $schema->components([
            RichEditor::make('comment')
                ->hiddenLabel()
                ->fileAttachmentsDisk(config('finisterre.attachments_disk') ?? 'public')
                ->saveUploadedFileAttachmentUsing(EditorFiles::store(...))
                ->extraInputAttributes(['style' => 'min-height: 6rem'])
                ->required()
                ->placeholder(__('finisterre::finisterre.comments.placeholder')),

            Grid::make()->columns()->schema($gridComponents),
        ])->statePath('data');
    }

    public function create(): void
    {
        if (! auth()->user()->can('create', FinisterreTaskComment::class)) {
            return;
        }

        $this->form->validate();

        $data = $this->form->getState();

        $canSchedule = FinisterrePlugin::get()->canScheduleComments();
        $scheduledFor = $canSchedule && ! empty($data['scheduled_for']) ? Carbon::parse($data['scheduled_for']) : null;
        $notifyIds = ! empty($data['notify']) ? $data['notify'] : [$this->record->assignee_id];

        $comment = $this->record->comments()->create([
            'comment'         => $data['comment'],
            'creator_id'      => auth()->id(),
            'scheduled_for'   => $scheduledFor,
            'notify_user_ids' => $scheduledFor instanceof Carbon ? $notifyIds : null,
        ]);

        if ($scheduledFor instanceof Carbon) {
            Notification::make()
                ->title(__('finisterre::finisterre.comments.notifications.scheduled', [
                    'time' => $scheduledFor->isoFormat('LLL'),
                ]))
                ->success()
                ->send();
        } else {
            $comment->setRelation('task', $this->record);
            $comment->notify_user_ids = $notifyIds;
            $notified = $comment->deliver();
            $comment->update(['notify_user_ids' => null]);

            $names = $notified->map(fn($user) => $user->getUserDisplayName());

            Notification::make()
                ->title($names->isEmpty() ?
                    __('finisterre::finisterre.comments.notifications.created') :
                    __('finisterre::finisterre.comments.notifications.created_and_notified', ['notified' => $names->implode(', ')]))
                ->success()
                ->send();
        }

        $this->fillWithDefaults();

        $this->dispatch('commentCreated')->to(TasksKanbanBoard::class);
    }

    public function postponeCommentAction(): Action
    {
        return PostponeCommentAction::make();
    }

    public function editCommentAction(): Action
    {
        return EditCommentAction::make();
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
        return $this->record?->comments()
            ->visibleTo(auth()->id())
            // Each comment shows its creator's avatar; hosts that keep avatars in a
            // media library would query that relation once per creator unless it
            // comes along.
            ->with(['creator' => fn(BelongsTo $query) => $query->getRelated() instanceof HasMedia
                ? $query->with('media')
                : $query])
            // A scheduled comment shows its scheduled time, so it takes its place in
            // the timeline by that time rather than by when it was written: replies
            // posted before it was published stay below it.
            ->orderByRaw('coalesce(scheduled_for, created_at) desc')
            ->orderByDesc('id')
            ->get() ?? collect();
    }

    public function render(): View
    {
        return view('finisterre::comments.comments');
    }
}
