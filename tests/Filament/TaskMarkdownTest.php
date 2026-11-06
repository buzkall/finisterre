<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\EditFinisterreTask;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreSubtask;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\TaskMarkdown;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Workbench\App\Models\User;

beforeEach(function() {
    Notification::fake();

    $this->user = User::factory()->create(['name' => 'Ana']);
    $this->actingAs($this->user);
});

function markdownTask(array $attributes = []): FinisterreTask
{
    return FinisterreTask::factory()->create([
        'title'        => 'Login fails',
        'description'  => '<p>The <strong>login</strong> form <a href="https://example.test/login">here</a> fails.</p><ul><li>Steps</li></ul>',
        'status'       => TaskStatusEnum::Doing,
        'priority'     => TaskPriorityEnum::Urgent,
        'due_at'       => null,
        'completed_at' => null,
        'creator_id'   => auth()->id(),
        'assignee_id'  => auth()->id(),
        ...$attributes,
    ]);
}

it('writes the task, its subtasks and its comments as markdown', function() {
    $task = markdownTask();
    $task->attachTag(FinisterreTag::findOrCreateFromString('Backend', 'tasks'));
    FinisterreSubtask::factory()->create(['task_id' => $task->id, 'title' => 'Reproduce', 'completed' => true]);
    FinisterreSubtask::factory()->create(['task_id' => $task->id, 'title' => 'Fix', 'completed' => false]);

    FinisterreTaskComment::factory()->create([
        'task_id'    => $task->id,
        'comment'    => '<p>First <em>comment</em></p>',
        'creator_id' => $this->user->id,
        'created_at' => '2026-10-02 19:57:47',
    ]);
    FinisterreTaskComment::factory()->create([
        'task_id'    => $task->id,
        'comment'    => '<p>Second comment</p>',
        'creator_id' => $this->user->id,
        'created_at' => '2026-10-03 08:00:00',
    ]);

    $markdown = (new TaskMarkdown($task->fresh()))->toMarkdown();

    expect($markdown)
        ->toStartWith("# Login fails\n\n")
        ->toContain('- **Enlace:** ' . $task->viewUrl())
        ->toContain('- **Estado:** ' . TaskStatusEnum::Doing->getLabel())
        ->toContain('- **Prioridad:** ' . TaskPriorityEnum::Urgent->getLabel())
        ->toContain('- **Asignada a:** Ana')
        ->toContain('- **Etiquetas:** Backend')
        ->not->toContain('**Vence:**')
        ->toContain("## Descripción\n\nThe **login** form [here](https://example.test/login) fails.")
        ->toContain('- Steps')
        ->toContain("## Subtareas\n\n- [x] Reproduce\n- [ ] Fix")
        ->toContain("### Ana · 02/10/2026 19:57:47\n\nFirst *comment*")
        ->not->toContain('## Adjuntos')
        ->and(strpos($markdown, 'First'))->toBeLessThan(strpos($markdown, 'Second comment'));
});

it('leaves out the scheduled comments not sent yet, unless the user wrote them', function() {
    $task = markdownTask();
    FinisterreTaskComment::factory()->create([
        'task_id'       => $task->id,
        'comment'       => '<p>Mine, scheduled</p>',
        'creator_id'    => $this->user->id,
        'scheduled_for' => now()->addDay(),
    ]);
    FinisterreTaskComment::factory()->create([
        'task_id'       => $task->id,
        'comment'       => '<p>Somebody else, scheduled</p>',
        'creator_id'    => User::factory()->create()->id,
        'scheduled_for' => now()->addDay(),
    ]);

    expect((new TaskMarkdown($task))->toMarkdown())
        ->toContain('Mine, scheduled')
        ->not->toContain('Somebody else, scheduled');
});

it('lists the names of the attachments', function() {
    Storage::fake('public');
    $task = markdownTask();
    $task->addMediaFromString('log line')->usingFileName('error.log')->toMediaCollection('tasks', 'public');

    expect((new TaskMarkdown($task->fresh()))->toMarkdown())->toContain("## Adjuntos\n\n- error.log");
});

it('leaves out the sections a task does not have', function() {
    $markdown = (new TaskMarkdown(markdownTask(['description' => null])))->toMarkdown();

    expect($markdown)
        ->not->toContain('## Descripción')
        ->not->toContain('## Subtareas')
        ->not->toContain('## Comentarios');
});

it('copies the task as markdown from the task and edit pages for whoever configures finisterre', function(string $page) {
    $task = markdownTask(['title' => 'Login fails']);

    $component = Livewire::test($page, ['record' => $task->getKey()])
        ->assertActionVisible('copyMarkdown')
        ->assertSeeHtml('$wire.taskMarkdown()')
        // Built on click only: the page does not carry the description.
        ->assertDontSeeHtml('## ');

    expect($component->instance()->taskMarkdown())->toStartWith("# Login fails\n");
})->with([
    'task page' => ViewFinisterreTask::class,
    'edit page' => EditFinisterreTask::class,
]);

it('hides the copy from users who cannot configure finisterre and refuses to build it', function() {
    FinisterrePlugin::get()->userCanConfigureFinisterre(false);

    Livewire::test(ViewFinisterreTask::class, ['record' => markdownTask()->getKey()])
        ->assertActionHidden('copyMarkdown')
        ->call('taskMarkdown')
        ->assertForbidden();
});
