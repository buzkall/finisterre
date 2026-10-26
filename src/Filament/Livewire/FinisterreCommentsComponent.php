<?php

namespace Arzcode\Finisterre\Filament\Livewire;

use Arzcode\Finisterre\Filament\Actions\DeleteCommentAction;
use Arzcode\Finisterre\Filament\Actions\EditCommentAction;
use Arzcode\Finisterre\Filament\Actions\PostponeCommentAction;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Schemas\FollowersSelect;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\AttachmentsDisk;
use Arzcode\Finisterre\Support\EditorFiles;
use Arzcode\Finisterre\Support\TaskFollowers;
use Arzcode\Finisterre\Support\Typed;
use Arzcode\Finisterre\Support\UserModel;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\ViewField;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Component;
use Livewire\Features\SupportEvents\Event;
use Spatie\MediaLibrary\HasMedia;

/**
 * @property-read Schema $form
 */
class FinisterreCommentsComponent extends Component implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    /** @var array<string, mixed>|null */
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
     * Every follower of the task hears about a new comment, except the one
     * writing it.
     *
     * @return list<int>
     */
    private function getDefaultNotifyIds(): array
    {
        if (! $this->record instanceof FinisterreTask) {
            return [];
        }

        return array_values(array_diff(TaskFollowers::ids($this->record), [UserModel::authId()]));
    }

    /**
     * Who the comment being written goes to. The list comes back from the browser,
     * so it is read again here: followers stay, and anybody else has to be a user
     * who can be added, picked by somebody who may add them.
     *
     * @return list<int>
     */
    private function notifyIds(): array
    {
        if (! $this->record instanceof FinisterreTask) {
            return [];
        }

        $selected = array_diff(TaskFollowers::normalize(Typed::array($this->data['notify'] ?? [])), [UserModel::authId()]);
        $followers = TaskFollowers::ids($this->record);
        $added = array_values(array_diff($selected, $followers));
        $allowed = [...$followers, ...($this->canAddRecipients() ? TaskFollowers::onlyCandidates($added) : [])];

        return array_values(array_intersect($selected, $allowed));
    }

    /**
     * Notifying somebody new makes them a follower, which shows them the task:
     * that is for whoever may edit it, not for a follower who may only comment.
     */
    private function canAddRecipients(): bool
    {
        return $this->record instanceof FinisterreTask
            && TaskFollowers::available()
            && (auth()->user()?->can('update', $this->record) ?? false);
    }

    /**
     * The people behind the avatars under the comment.
     *
     * @return EloquentCollection<int, Model>
     */
    #[Computed]
    public function notifyUsers(): EloquentCollection
    {
        return TaskFollowers::usersById($this->notifyIds());
    }

    /**
     * Who the plus button can still bring in: nobody already on the comment, and
     * not the person writing it.
     *
     * @return list<int>
     */
    private function notifyExcept(): array
    {
        return [...$this->notifyIds(), ...array_filter([UserModel::authId()])];
    }

    /**
     * The plus after the avatars: a modal to add people to this comment, who then
     * follow the task too. Gone when there is nobody left to add.
     */
    public function addNotifyAction(): Action
    {
        return Action::make('addNotify')
            ->iconButton()
            ->color('gray')
            ->icon(Heroicon::Plus)
            ->label(__('finisterre::finisterre.comments.notify_add'))
            ->tooltip(__('finisterre::finisterre.comments.notify_add'))
            ->modalHeading(__('finisterre::finisterre.comments.notify_add'))
            ->modalDescription(__('finisterre::finisterre.comments.notify_add_help'))
            ->modalWidth(Width::Medium)
            ->modalSubmitActionLabel(__('finisterre::finisterre.add'))
            ->schema([
                FollowersSelect::make('people', fn(): array => $this->notifyExcept())
                    ->hiddenLabel()
                    ->multiple()
                    ->required(),
            ])
            ->action(function(array $data): void {
                $this->data['notify'] = TaskFollowers::normalize([
                    ...Typed::array($this->data['notify'] ?? []),
                    ...Typed::array($data['people'] ?? []),
                ]);

                unset($this->notifyUsers);
            })
            ->visible(fn(): bool => $this->canAddRecipients() && TaskFollowers::hasCandidates($this->notifyExcept()));
    }

    public function removeNotify(int $id): void
    {
        $this->data['notify'] = array_values(array_diff(TaskFollowers::normalize(Typed::array($this->data['notify'] ?? [])), [$id]));

        unset($this->notifyUsers);
    }

    public function form(Schema $schema): Schema
    {
        if (! auth()->user()?->can('create', FinisterreTaskComment::class)) {
            return $schema;
        }

        $canSchedule = FinisterrePlugin::get()->canScheduleComments();

        $notify = ViewField::make('notify')
            ->view('finisterre::comments.notify')
            ->label(__('finisterre::finisterre.comments.notify'))
            ->belowLabel(__('finisterre::finisterre.comments.notify_hint'))
            ->columnSpan($canSchedule ? 1 : 'full');

        $gridComponents = $canSchedule
            ? [
                $notify,
                DateTimePicker::make('scheduled_for')
                    ->native(false)
                    ->suffixIcon('heroicon-o-calendar')
                    ->displayFormat('d/m/y H:i')
                    ->columnSpan(1)
                    ->label(__('finisterre::finisterre.comments.scheduled_for'))
                    ->belowLabel(__('finisterre::finisterre.comments.scheduled_for_hint'))
                    ->seconds(false)
                    ->minDate(today()),
            ]
            : [$notify];

        return $schema->components([
            RichEditor::make('comment')
                ->hiddenLabel()
                ->fileAttachmentsDisk(AttachmentsDisk::name())
                ->saveUploadedFileAttachmentUsing(EditorFiles::store(...))
                ->extraInputAttributes(['style' => 'min-height: 6rem'])
                ->required()
                ->placeholder(__('finisterre::finisterre.comments.placeholder')),

            Grid::make()->columns()->schema($gridComponents),
        ])->statePath('data');
    }

    public function create(): void
    {
        $task = $this->record;

        if (! $task instanceof FinisterreTask || ! auth()->user()?->can('create', FinisterreTaskComment::class)) {
            return;
        }

        $this->form->validate();

        $data = $this->form->getState();

        $canSchedule = FinisterrePlugin::get()->canScheduleComments();
        $scheduledFor = $canSchedule && ! empty($data['scheduled_for']) ? Carbon::parse(Typed::string($data['scheduled_for'])) : null;
        $notifyIds = $this->notifyIds();

        $comment = $task->comments()->create([
            'comment'         => $data['comment'],
            'creator_id'      => auth()->id(),
            'scheduled_for'   => $scheduledFor,
            'notify_user_ids' => $scheduledFor instanceof Carbon ? $notifyIds : null,
        ]);

        // Whoever is told about the comment follows the task from here on, so they
        // can open it and hear about the answers. Only once the comment exists: a
        // comment that failed to save shares the task with nobody.
        $task->follow($notifyIds);

        if ($scheduledFor instanceof Carbon) {
            Notification::make()
                ->title(__('finisterre::finisterre.comments.notifications.scheduled', [
                    'time' => $scheduledFor->isoFormat('LLL'),
                ]))
                ->success()
                ->send();
        } else {
            $comment->setRelation('task', $task);
            $comment->notify_user_ids = $notifyIds;
            $notified = $comment->deliver();
            $comment->update(['notify_user_ids' => null]);

            $names = $notified->map(UserModel::displayName(...));

            Notification::make()
                ->title($names->isEmpty() ?
                    __('finisterre::finisterre.comments.notifications.created') :
                    __('finisterre::finisterre.comments.notifications.created_and_notified', ['notified' => $names->implode(', ')]))
                ->success()
                ->send();
        }

        $this->fillWithDefaults();

        // Drops the draft the browser kept of this comment (see comments.blade.php).
        $this->dispatch('finisterre-comment-saved', taskId: $task->getKey());

        $event = $this->dispatch('commentCreated');

        if ($event instanceof Event) {
            $event->to(TasksKanbanBoard::class);
        }
    }

    /**
     * A comment of the task on screen that the user can see and the policy lets them
     * update. The id comes from the browser: any other id finds nothing.
     */
    public function findEditableComment(mixed $id): ?FinisterreTaskComment
    {
        if (! $this->record instanceof FinisterreTask || ! is_int($id) && ! is_string($id)) {
            return null;
        }

        $comment = $this->record->comments()
            ->visibleTo(UserModel::authId())
            ->whereKey($id)
            ->first();

        $user = auth()->guard(Typed::nullableString(config('finisterre.guard')))->user();

        return $comment && $user?->can('update', $comment) ? $comment : null;
    }

    public function postponeCommentAction(): Action
    {
        return PostponeCommentAction::make();
    }

    public function editCommentAction(): Action
    {
        return EditCommentAction::make();
    }

    public function deleteCommentAction(): Action
    {
        return DeleteCommentAction::make();
    }

    /** @return Collection<int, FinisterreTaskComment> */
    #[Computed]
    public function comments(): Collection
    {
        // record will be empty on the kanban load. We'll get the value from the view
        // when the modal is opened
        if (! $this->record instanceof FinisterreTask) {
            return new Collection;
        }

        return $this->record->comments()
            ->visibleTo(UserModel::authId())
            // Each comment shows its creator's avatar; hosts that keep avatars in a
            // media library would query that relation once per creator unless it
            // comes along.
            ->with(['creator' => fn(Relation $query) => $query->getRelated() instanceof HasMedia
                ? $query->with('media')
                : $query])
            // A scheduled comment shows its scheduled time, so it takes its place in
            // the timeline by that time rather than by when it was written: replies
            // posted before it was published stay below it.
            ->orderByRaw('coalesce(scheduled_for, created_at) desc')
            ->orderByDesc('id')
            ->get();
    }

    public function render(): View
    {
        return view('finisterre::comments.comments');
    }
}
