<?php

use Arzcode\Finisterre\Controllers\SharedFileController;
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
use Illuminate\Support\Facades\URL;
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

/**
 * The first signed link to a shared file in the markdown.
 */
function sharedLink(string $markdown): string
{
    preg_match('~https?://[^)\s]*/' . SharedFileController::PATH . '/[^)\s]+~', $markdown, $match);

    return $match[0] ?? '';
}

it('links the attachments through a signed url that opens them for ten minutes without a login', function() {
    Storage::fake('public');
    $task = markdownTask();
    $task->addMediaFromString('log line')->usingFileName('error.log')->toMediaCollection('tasks', 'public');

    $markdown = (new TaskMarkdown($task->fresh()))->toMarkdown();
    $link = sharedLink($markdown);

    expect($markdown)->toContain("## Adjuntos\n\n- [error.log](" . $link . ')');

    app('auth')->forgetGuards();
    auth()->logout();

    expect($this->get($link)->assertOk()->baseResponse->getFile()->getContent())->toBe('log line');

    // Tampered or expired, the link opens nothing.
    $this->get(str_replace('signature=', 'signature=0', $link))->assertForbidden();
    $this->travel(SharedFileController::MINUTES + 1)->minutes();
    $this->get($link)->assertForbidden();
});

it('points the images on the public disk at a signed url', function() {
    Storage::fake('public');
    Storage::disk('public')->put('shot.png', 'image');
    $task = markdownTask(['description' => '<p>Look</p><p><img src="https://app.test/storage/shot.png" alt="shot"></p>']);

    $markdown = (new TaskMarkdown($task))->toMarkdown();

    expect($markdown)->toContain('![shot](' . sharedLink($markdown) . ')')
        ->not->toContain('/storage/shot.png');

    $this->get(sharedLink($markdown))->assertOk();
});

it('leaves an image from elsewhere as it is', function() {
    $task = markdownTask(['description' => '<p><img src="https://example.test/logo.png" alt="logo"></p>']);

    expect((new TaskMarkdown($task))->toMarkdown())
        ->toContain('![logo](https://example.test/logo.png)')
        ->not->toContain(SharedFileController::PATH);
});

it('signs only the images of the task itself on a private disk', function() {
    config()->set('filesystems.disks.finisterre', ['driver' => 'local', 'root' => storage_path('app/finisterre-files')]);
    config()->set('finisterre.attachments_disk', 'finisterre');
    Storage::fake('finisterre');
    Storage::disk('finisterre')->put('own.png', 'own image');
    Storage::disk('finisterre')->put('other.png', 'other image');

    $task = markdownTask(['description' => '<p><img src="/storage/finisterre-files/own.png" alt="own"><img src="/storage/finisterre-files/other.png" alt="other"></p>']);
    $task->forceFill(['editor_files' => ['own.png']])->saveQuietly();

    FinisterreTaskComment::factory()->create([
        'task_id'    => $task->id,
        'comment'    => '<p><img src="/storage/finisterre-files/own.png" alt="again"></p>',
        'creator_id' => $this->user->id,
    ]);

    $markdown = (new TaskMarkdown($task->fresh()))->toMarkdown();
    $link = sharedLink($markdown);

    expect($markdown)
        ->toContain('![own](' . $link . ')')
        ->toContain('![again](' . $link . ')')
        // Loaded by the description but owned by another task: no link unlocks it.
        ->toContain('![other](/storage/finisterre-files/other.png)');

    app('auth')->forgetGuards();
    auth()->logout();

    expect($this->get($link)->assertOk()->baseResponse->getFile()->getContent())->toBe('own image');
});

it('opens nothing for a signed link to a disk that does not exist or a missing file', function() {
    $this->get(URL::temporarySignedRoute('finisterre.shared-file', now()->addMinute(), ['disk' => 'nope', 'path' => 'a.png']))->assertNotFound();

    Storage::fake('public');
    $this->get(SharedFileController::url('public', 'missing.png'))->assertNotFound();
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
        ->assertSeeHtml('se abren durante ' . SharedFileController::MINUTES . ' minutos')
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
