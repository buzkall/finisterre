<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\CreateFinisterreTask;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Group;
use Illuminate\Support\Facades\Notification;
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
        ->assertFormFieldVisible('description')
        ->assertFormFieldVisible('attachments')
        // The due date is set from the task page, next to the badge showing it.
        ->assertFormFieldDoesNotExist('due_at');

    // One column per selector, so the row fills the width.
    expect(selectorColumns($component))->toBe(3);
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

    // Without the assignee the row would leave a third of it empty.
    expect(selectorColumns($component))->toBe(2);
});
