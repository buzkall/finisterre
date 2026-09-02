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
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
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
        'priority'         => $task->priority->getLabel(),
        'priorityColor'    => $task->priority->getColor(),
        'tagNames'         => collect(),
        'mediaCount'       => 0,
        'commentsCount'    => 0,
        'subtasksCount'    => 0,
        'subtasksDone'     => 0,
        'viewUrl'          => $viewUrl,
        'updatedAt'        => null,
        'hasChanges'       => false,
    ])->render();

    expect($html)->toContain('href="' . $viewUrl . '"')
        ->and($viewUrl)->toEndWith('/admin/finisterre-tasks/' . $task->getKey());
});
