<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Filament\Pages\TasksKanbanBoard;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\CreateFinisterreTask;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Notifications\TaskNotification;
use Arzcode\Finisterre\Notifications\TaskSmsNotification;
use Arzcode\Finisterre\Support\TaskFollowers;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    Notification::fake();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function selectorColumns(Testable $component): ?int
{
    // The selector row is the only Group in the form.
    return $component->instance()->form
        ->getComponent(fn($component): bool => $component instanceof Group, withHidden: true)
        ?->getColumns('lg');
}

it('lays the create form out like the task page', function() {
    $component = Livewire::test(CreateFinisterreTask::class)
        ->assertFormFieldVisible('title')
        ->assertFormFieldVisible('priority')
        ->assertFormFieldVisible('assignee_id')
        ->assertFormFieldVisible('tags')
        ->assertFormFieldVisible('followers')
        ->assertFormFieldVisible('description')
        ->assertFormFieldVisible('attachments')
        // The due date is set from the task page, next to the badge showing it.
        ->assertFormFieldDoesNotExist('due_at');

    // Four selectors make two rows of two.
    expect(selectorColumns($component))->toBe(2);
});

it('offers the priority as coloured buttons instead of a dropdown', function() {
    Livewire::test(CreateFinisterreTask::class)
        ->assertFormFieldExists('priority', fn(ToggleButtons $field): bool => $field->isInline()
            && $field->getColor(TaskPriorityEnum::Urgent->value) === 'danger');
});

it('creates a task from the badge selectors', function() {
    $tag = FinisterreTag::findOrCreateFromString('Boiler', 'tasks');

    Livewire::test(CreateFinisterreTask::class)
        ->fillForm([
            'title'       => 'Fix the boiler',
            'priority'    => TaskPriorityEnum::High->value,
            'assignee_id' => $this->user->getKey(),
            'tags'        => [$tag->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = FinisterreTask::where('title', 'Fix the boiler')->firstOrFail();

    expect($task->priority)->toBe(TaskPriorityEnum::High)
        ->and($task->assignee_id)->toBe($this->user->getKey())
        ->and($task->tags->pluck('id')->all())->toBe([$tag->getKey()]);
});

it('hides the fields a reporter may not set', function() {
    FinisterrePlugin::get()->userCanViewOnlyTheirTasks(fn(): bool => true);

    $component = Livewire::test(CreateFinisterreTask::class)
        ->assertFormFieldVisible('priority')
        ->assertFormFieldVisible('tags')
        ->assertFormFieldHidden('assignee_id')
        ->assertFormSet(['priority' => TaskPriorityEnum::Urgent]);

    // Without the assignee, priority, tags and followers share one row.
    expect(selectorColumns($component))->toBe(3);
});

it('leaves the followers out until their table is migrated', function() {
    Schema::drop(TaskFollowers::table());
    TaskFollowers::forgetTable();

    $component = Livewire::test(CreateFinisterreTask::class)
        ->assertFormFieldHidden('followers');

    // One column per selector, so the row fills the width.
    expect(selectorColumns($component))->toBe(3);

    TaskFollowers::forgetTable();
});

it('makes the people picked followers of the new task and tells them about it', function() {
    $this->withoutDefer();
    $assignee = User::factory()->create();
    $follower = User::factory()->create();

    Livewire::test(CreateFinisterreTask::class)
        ->fillForm([
            'title'       => 'Paint the hall',
            'priority'    => TaskPriorityEnum::Low->value,
            'assignee_id' => $assignee->getKey(),
            'followers'   => [$follower->getKey(), $assignee->getKey(), $this->user->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $task = FinisterreTask::where('title', 'Paint the hall')->firstOrFail();

    expect(TaskFollowers::ids($task))->toEqualCanonicalizing([$this->user->getKey(), $assignee->getKey(), $follower->getKey()])
        ->and($task->taskChanges()->where('user_id', $follower->getKey())->exists())->toBeTrue();

    // The text message goes to one fixed number, so it is sent once, with the assignee's notification.
    Notification::assertSentTo($follower, TaskNotification::class);
    Notification::assertNotSentTo($follower, TaskSmsNotification::class);
    Notification::assertSentToTimes($assignee, TaskNotification::class, 1);
    Notification::assertSentToTimes($assignee, TaskSmsNotification::class, 1);
    Notification::assertNotSentTo($this->user, TaskNotification::class);
});

it('does not tell the creator about the task they follow', function() {
    $this->withoutDefer();

    Livewire::test(CreateFinisterreTask::class)
        ->fillForm([
            'title'       => 'Water the plants',
            'priority'    => TaskPriorityEnum::Low->value,
            'assignee_id' => $this->user->getKey(),
            'followers'   => [$this->user->getKey()],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    Notification::assertNothingSent();
});

it('ignores a follower the host does not let be added', function() {
    $this->withoutDefer();
    $outsider = User::factory()->create();
    FinisterrePlugin::get()->modifyFollowersQueryUsing(fn($query) => $query->whereKeyNot($outsider->getKey()));

    Livewire::test(CreateFinisterreTask::class)
        ->fillForm([
            'title'       => 'Fix the gate',
            'priority'    => TaskPriorityEnum::Low->value,
            'assignee_id' => $this->user->getKey(),
            'followers'   => [$outsider->getKey()],
        ])
        ->call('create');

    $task = FinisterreTask::where('title', 'Fix the gate')->firstOrFail();

    expect($task->isFollowedBy($outsider))->toBeFalse();
    Notification::assertNotSentTo($outsider, TaskNotification::class);
});

it('sends the breadcrumb back to the board, not to the tasks table', function() {
    $breadcrumbs = Livewire::test(CreateFinisterreTask::class)
        ->instance()
        ->getBreadcrumbs();

    expect(array_key_first($breadcrumbs))->toBe(TasksKanbanBoard::getUrl())
        ->and($breadcrumbs)->toBe([
            TasksKanbanBoard::getUrl() => __('finisterre::finisterre.tasks'),
            ''                         => __('finisterre::finisterre.create_task'),
        ]);
});

it('sends the breadcrumb to the tasks table for a user who may not see every task', function() {
    FinisterrePlugin::get()->userCanViewAllTasks(fn(): bool => false);

    $breadcrumbs = Livewire::test(CreateFinisterreTask::class)
        ->instance()
        ->getBreadcrumbs();

    expect($breadcrumbs)->not->toHaveKey(TasksKanbanBoard::getUrl());
});
