<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\CreateFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\EditFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTaskResource;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Policies\FinisterreTaskPolicy;
use Filament\Actions\Testing\TestAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Workbench\App\Models\User;

class ReadOnlyTaskPolicy extends FinisterreTaskPolicy
{
    public function update($user, FinisterreTask $finisterreTask): bool
    {
        return false;
    }
}

class NoViewTaskPolicy extends FinisterreTaskPolicy
{
    public function view($user, FinisterreTask $finisterreTask): bool
    {
        return false;
    }
}

beforeEach(function() {
    Notification::fake();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function pageTask(array $attributes = []): FinisterreTask
{
    return FinisterreTask::factory()->create([
        'status'       => TaskStatusEnum::Open,
        'priority'     => TaskPriorityEnum::Low,
        'due_at'       => null,
        'completed_at' => null,
        'creator_id'   => auth()->id(),
        'assignee_id'  => auth()->id(),
        ...$attributes,
    ]);
}

function quickAction(string $name): TestAction
{
    return TestAction::make($name)->schemaComponent('quick_actions');
}

it('renders the task with its badges', function() {
    $task = pageTask(['title' => 'Fix the report']);

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->assertOk()
        ->assertSee('Fix the report')
        ->assertSee(TaskStatusEnum::Open->getLabel())
        ->assertSee(TaskPriorityEnum::Low->getLabel())
        ->assertSchemaComponentVisible('quick_actions')
        ->assertSchemaComponentHidden('read_only_strip');
});

it('forbids users the policy does not allow to view', function() {
    Gate::policy(FinisterreTask::class, NoViewTaskPolicy::class);

    Livewire::test(ViewFinisterreTask::class, ['record' => pageTask()->getKey()])
        ->assertForbidden();
});

it('clears the change indicator for the user opening the task', function() {
    $task = pageTask();
    $task->taskChanges()->create(['user_id' => $this->user->id]);
    $task->taskChanges()->create(['user_id' => User::factory()->create()->id]);

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])->assertOk();

    expect($task->taskChanges()->where('user_id', $this->user->id)->exists())->toBeFalse()
        ->and($task->taskChanges()->count())->toBe(1);
});

it('changes the status from the dropdown', function() {
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->callAction(quickAction('status_doing'))
        ->assertNotified()
        ->assertSee(TaskStatusEnum::Doing->getLabel());

    expect($task->refresh()->status)->toBe(TaskStatusEnum::Doing);
});

it('stamps completed_at when set to done', function() {
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->callAction(quickAction('status_done'));

    expect($task->refresh())
        ->status->toBe(TaskStatusEnum::Done)
        ->completed_at->not->toBeNull();
});

it('changes the priority from the dropdown', function() {
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->callAction(quickAction('priority_urgent'));

    expect($task->refresh()->priority)->toBe(TaskPriorityEnum::Urgent);
});

it('reassigns the task from the dropdown', function() {
    $task = pageTask();
    $other = User::factory()->create();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->callAction(quickAction('assignee_' . $other->getKey()))
        ->assertSee($other->getUserDisplayName());

    expect($task->refresh()->assignee_id)->toBe($other->getKey());
});

it('sets the due date from the modal', function() {
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->callAction(quickAction('quick_due_at'), ['due_at' => '2026-10-01'])
        ->assertHasNoFormErrors()
        ->assertSee('01/10/26');

    expect($task->refresh()->due_at?->toDateString())->toBe('2026-10-01');
});

it('syncs the tags from the modal and touches the task', function() {
    $task = pageTask();
    $task->forceFill(['updated_at' => now()->subDay()])->saveQuietly();
    $tag = FinisterreTag::findOrCreateFromString('Backend', 'tasks');

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->callAction(quickAction('quick_tags'), ['tags' => [$tag->getKey()]])
        ->assertHasNoFormErrors()
        ->assertSee('#Backend');

    expect($task->refresh()->tags->pluck('id')->all())->toBe([$tag->getKey()])
        ->and($task->updated_at->isToday())->toBeTrue();
});

it('uploads an attachment from the modal and keeps the ones already there', function() {
    Storage::fake('public');
    $task = pageTask();
    $task->addMediaFromString('first')->usingFileName('first.png')->toMediaCollection('tasks');

    $component = Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->mountAction(quickAction('quick_attachments'));

    // The modal comes up with the media the task already has, which is what the
    // action's fillForm() is for. Picking a file in the browser appends to that
    // state, so the test has to append too: passing the new file on its own would
    // be the user removing the existing attachment before adding this one.
    $existing = (array)data_get($component->get('mountedActions'), '0.data.attachments', []);

    expect($existing)->toHaveCount(1);

    $component
        ->setActionData(['attachments' => [...$existing, UploadedFile::fake()->image('second.png')]])
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotified();

    // Livewire renames the temporary upload, so assert on the count and on the
    // attachment that was already there.
    $media = $task->refresh()->getMedia('tasks');

    expect($media)->toHaveCount(2)
        ->and($media->pluck('file_name'))->toContain('first.png');
});

it('changes and clears the card image from the attachments list', function() {
    Storage::fake('public');
    $task = pageTask();

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $first = $task->addMediaFromString($png)->usingFileName('first.png')->toMediaCollection('tasks', 'public');
    $second = $task->addMediaFromString($png)->usingFileName('second.png')->toMediaCollection('tasks', 'public');

    // The first image was promoted automatically when it was attached.
    expect($task->refresh()->cover_media_id)->toBe($first->getKey());

    $component = Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()]);

    $component->call('setCoverMedia', $second->getKey())->assertOk()->assertNotified();
    expect($task->refresh()->cover_media_id)->toBe($second->getKey());

    // A task is allowed to have no card image at all.
    $component->call('setCoverMedia', null)->assertOk()->assertNotified();
    expect($task->refresh()->cover_media_id)->toBeNull();
});

it('saves where the card image was dragged to on its own media row', function() {
    Storage::fake('public');
    $task = pageTask();

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $media = $task->addMediaFromString($png)->usingFileName('shot.png')->toMediaCollection('tasks', 'public');

    $component = Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()]);

    $component->call('setCoverPosition', 32.5)->assertOk()->assertNotified();

    expect($media->refresh()->getCustomProperty(FinisterreTask::COVER_POSITION_PROPERTY))->toBe(32.5)
        ->and($task->refresh()->coverPosition())->toBe(32.5);

    // The value comes from the browser, so it is kept inside the frame.
    $component->call('setCoverPosition', 140);

    expect($task->refresh()->coverPosition())->toBe(100.0);
});

it('has no position to save on a task without a card image', function() {
    Livewire::test(ViewFinisterreTask::class, ['record' => pageTask()->getKey()])
        ->call('setCoverPosition', 20)
        ->assertNotFound();
});

it('forbids a user who may not update the task from repositioning the card image', function() {
    Gate::policy(FinisterreTask::class, ReadOnlyTaskPolicy::class);

    Livewire::test(ViewFinisterreTask::class, ['record' => pageTask()->getKey()])
        ->call('setCoverPosition', 20)
        ->assertForbidden();
});

it('refuses to set a card image from another task', function() {
    Storage::fake('public');
    $task = pageTask();
    $other = pageTask(['title' => 'Somebody else']);

    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    $foreign = $other->addMediaFromString($png)->usingFileName('theirs.png')->toMediaCollection('tasks', 'public');

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->call('setCoverMedia', $foreign->getKey())
        ->assertNotFound();

    expect($task->refresh()->cover_media_id)->toBeNull();
});

it('forbids a user who may not update the task from changing the card image', function() {
    Gate::policy(FinisterreTask::class, ReadOnlyTaskPolicy::class);

    Livewire::test(ViewFinisterreTask::class, ['record' => pageTask()->getKey()])
        ->call('setCoverMedia', null)
        ->assertForbidden();
});

/**
 * An image the rich editor left at the root of the public disk, loaded the way the editor's HTML loads it.
 */
function pastedPublicImage(string $file = 'pasted.png'): string
{
    Storage::disk('public')->put($file, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg=='));

    return '<p><img src="http://localhost/storage/' . $file . '"></p>';
}

it('copies an image pasted into a comment into the attachments and makes it the card image', function() {
    Storage::fake('public');
    $task = pageTask();
    $task->comments()->create(['comment' => pastedPublicImage(), 'creator_id' => auth()->id()]);

    $component = Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()]);

    $component->call('setCoverFromEditorImage', 'pasted.png')->assertOk()->assertNotified();

    $media = $task->refresh()->getMedia('tasks');

    expect($media)->toHaveCount(1)
        ->and($media->first()->getCustomProperty(FinisterreTask::COVER_SOURCE_PROPERTY))->toBe('pasted.png')
        ->and($task->cover_media_id)->toBe($media->first()->getKey())
        ->and($task->coverSourceFile())->toBe('pasted.png')
        // The comment still loads the pasted file, so it stays where it was.
        ->and(Storage::disk('public')->exists('pasted.png'))->toBeTrue();

    // Picking it again, after clearing the card image, reuses the copy.
    $component->call('setCoverMedia', null);
    $component->call('setCoverFromEditorImage', 'pasted.png')->assertOk();

    expect($task->refresh()->getMedia('tasks'))->toHaveCount(1)
        ->and($task->cover_media_id)->toBe($media->first()->getKey());
});

it('makes an image pasted into the description the card image', function() {
    Storage::fake('public');
    $task = pageTask(['description' => pastedPublicImage('described.png')]);

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->call('setCoverFromEditorImage', 'described.png')
        ->assertOk();

    expect($task->refresh()->coverSourceFile())->toBe('described.png');
});

it('refuses an image the task does not load', function(string $file) {
    Storage::fake('public');
    $task = pageTask();
    pastedPublicImage('stray.png');

    // Loaded by a comment, but on somebody else's task.
    pageTask(['title' => 'Somebody else'])->comments()->create(['comment' => pastedPublicImage('theirs.png'), 'creator_id' => auth()->id()]);

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->call('setCoverFromEditorImage', $file)
        ->assertNotFound();

    expect($task->refresh()->cover_media_id)->toBeNull()
        ->and($task->getMedia('tasks'))->toBeEmpty();
})->with(['stray.png', 'theirs.png', '../stray.png', 'nested/stray.png']);

it('refuses a pasted file that is not an image and leaves no attachment behind', function() {
    Storage::fake('public');
    Storage::disk('public')->put('notes.txt', 'not a picture');
    $task = pageTask(['description' => '<p><img src="/storage/notes.txt"></p>']);

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->call('setCoverFromEditorImage', 'notes.txt')
        ->assertNotFound();

    expect($task->refresh()->getMedia('tasks'))->toBeEmpty()
        ->and($task->cover_media_id)->toBeNull();
});

it('offers the pasted image star only to users who may update the task', function() {
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->assertSeeHtml('data-finisterre-cover-star');

    Gate::policy(FinisterreTask::class, ReadOnlyTaskPolicy::class);

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->assertDontSeeHtml('data-finisterre-cover-star');
});

it('forbids a user who may not update the task from using a pasted image as the card image', function() {
    Gate::policy(FinisterreTask::class, ReadOnlyTaskPolicy::class);
    Storage::fake('public');
    $task = pageTask(['description' => pastedPublicImage()]);

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->call('setCoverFromEditorImage', 'pasted.png')
        ->assertForbidden();
});

it('shows a read-only strip to users who may not update', function() {
    Gate::policy(FinisterreTask::class, ReadOnlyTaskPolicy::class);
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->assertOk()
        ->assertSchemaComponentHidden('quick_actions')
        ->assertSchemaComponentVisible('read_only_strip')
        ->assertSee(TaskStatusEnum::Open->getLabel());
});

it('links the edit action to the edit page', function() {
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->assertActionExists('edit')
        ->assertActionHasUrl('edit', FinisterreTaskResource::getUrl('edit', ['record' => $task]));
});

it('offers archive on an open task and unarchive on an archived one', function() {
    $task = pageTask();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->assertActionVisible('archive')
        ->assertActionHidden('unarchive')
        ->callAction('archive');

    expect($task->refresh()->archived)->toBeTrue();

    Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
        ->assertActionHidden('archive')
        ->assertActionVisible('unarchive');
});

it('redirects to the task page after creating', function() {
    Livewire::test(CreateFinisterreTask::class)
        ->fillForm([
            'title'       => 'Brand new',
            'priority'    => TaskPriorityEnum::Medium->value,
            'assignee_id' => $this->user->getKey(),
        ])
        ->call('create')
        ->assertHasNoFormErrors()
        ->assertRedirect(FinisterreTaskResource::getUrl('view', [
            'record' => FinisterreTask::where('title', 'Brand new')->firstOrFail(),
        ]));
});

it('redirects to the task page after saving the edit form', function() {
    $task = pageTask();

    Livewire::test(EditFinisterreTask::class, ['record' => $task->getKey()])
        ->assertFormFieldExists('title')
        ->assertFormFieldExists('attachments')
        ->assertFormFieldHidden('priority')
        ->assertFormFieldHidden('assignee_id')
        ->assertFormFieldHidden('tags')
        ->fillForm(['title' => 'Renamed'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertRedirect(FinisterreTaskResource::getUrl('view', ['record' => $task]));

    expect($task->refresh()->title)->toBe('Renamed');
});

it('links the board card to the task page', function() {
    $task = pageTask();
    $viewUrl = FinisterreTaskResource::getUrl('view', ['record' => $task]);

    // The board loads its cards lazily, so render the card view the way the
    // board's card schema does.
    $html = view('finisterre::tasks.task-card-info', [
        'assignee'         => 'Alicia',
        'assigneeInitials' => 'A',
        'assigneeAvatar'   => null,
        'creator'          => null,
        'creatorInitials'  => null,
        'creatorAvatar'    => null,
        'priority'         => $task->priority->getLabel(),
        'priorityColor'    => $task->priority->getColor(),
        'tagNames'         => collect(),
        'mediaCount'       => 0,
        'commentsCount'    => 0,
        'subtasksCount'    => 0,
        'subtasksDone'     => 0,
        'viewUrl'          => $viewUrl,
        'createdAt'        => '12 sep',
        'createdAtFull'    => '12/09/2026 10:14',
        'updatedAt'        => null,
        'updatedAtFull'    => null,
        'hasChanges'       => false,
    ])->render();

    expect($html)->toContain('href="' . $viewUrl . '"')
        ->and($viewUrl)->toEndWith('/admin/finisterre-tasks/' . $task->getKey());
});

it('shows the creation and update dates on the board card', function() {
    $task = pageTask();

    $render = fn(?string $updatedAt, ?string $updatedAtFull) => view('finisterre::tasks.task-card-info', [
        'assignee'         => null,
        'assigneeInitials' => null,
        'assigneeAvatar'   => null,
        'creator'          => null,
        'creatorInitials'  => null,
        'creatorAvatar'    => null,
        'priority'         => $task->priority->getLabel(),
        'priorityColor'    => $task->priority->getColor(),
        'tagNames'         => collect(),
        'mediaCount'       => 0,
        'commentsCount'    => 0,
        'subtasksCount'    => 0,
        'subtasksDone'     => 0,
        'viewUrl'          => FinisterreTaskResource::getUrl('view', ['record' => $task]),
        'createdAt'        => '12 sep',
        'createdAtFull'    => '12/09/2026 10:14',
        'updatedAt'        => $updatedAt,
        'updatedAtFull'    => $updatedAtFull,
        'hasChanges'       => false,
    ])->render();

    expect($render('3h', '14/09/2026 08:02'))
        ->toContain('12 sep')
        ->toContain('3h')
        ->toContain(__('finisterre::finisterre.created_at') . ' 12/09/2026 10:14 · ' . __('finisterre::finisterre.updated_at') . ' 14/09/2026 08:02');

    expect($render(null, null))
        ->toContain('12 sep')
        ->toContain('title="' . __('finisterre::finisterre.created_at') . ' 12/09/2026 10:14"')
        ->not->toContain('14/09/2026 08:02');
});
