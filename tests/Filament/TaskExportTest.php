<?php

use Arzcode\Finisterre\Enums\TaskPriorityEnum;
use Arzcode\Finisterre\Enums\TaskStatusEnum;
use Arzcode\Finisterre\Filament\Pages\ManageFinisterreSettings;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTag;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Settings\FinisterreSettings;
use Arzcode\Finisterre\Support\ColumnExists;
use Arzcode\Finisterre\Support\TaskExport;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Workbench\App\Models\User;

/**
 * @return list<array<string, string>>
 */
function exportedTasks(): array
{
    $handle = fopen('php://memory', 'w+');
    (new TaskExport)->write($handle);
    rewind($handle);

    $header = fgetcsv($handle, escape: '');
    $rows = [];

    while (($row = fgetcsv($handle, escape: '')) !== false) {
        $rows[] = array_combine($header, $row);
    }

    fclose($handle);

    return $rows;
}

beforeEach(function() {
    config()->set('app.env', 'local');

    $this->user = User::factory()->create(['name' => 'Ana']);
    $this->actingAs($this->user);

    if (! Schema::hasTable('settings')) {
        (include __DIR__ . '/../../vendor/spatie/laravel-settings/database/migrations/create_settings_table.php.stub')->up();
    }

    (include __DIR__ . '/../../database/settings/2026_06_09_000000_create_finisterre_settings.php')->up();
    app()->forgetInstance(FinisterreSettings::class);
});

it('exports every task with the columns the report reads', function() {
    $task = FinisterreTask::factory()->create([
        'title'        => 'Fix the "login", again',
        'status'       => TaskStatusEnum::Done,
        'priority'     => TaskPriorityEnum::Urgent,
        'archived'     => true,
        'assignee_id'  => $this->user->id,
        'completed_at' => '2026-10-02 19:57:47',
        'due_at'       => null,
    ]);
    $task->attachTag(FinisterreTag::findOrCreateFromString('Backend', 'tasks'));
    $task->attachTag(FinisterreTag::findOrCreateFromString('Login', 'tasks'));
    FinisterreTaskComment::factory()->count(2)->create(['task_id' => $task->id]);

    $rows = exportedTasks();

    expect(array_keys($rows[0]))->toBe(TaskExport::COLUMNS)
        ->and($rows)->toHaveCount(1)
        ->and($rows[0])->toMatchArray([
            'id'           => (string)$task->id,
            'title'        => 'Fix the "login", again',
            'status'       => 'done',
            'priority'     => 'urgent',
            'archived'     => '1',
            'assignee'     => 'Ana',
            'completed_at' => '2026-10-02 19:57:47',
            'due_at'       => '',
            'comments'     => '2',
            'tags'         => 'Backend|Login',
        ]);
});

it('exports the tasks the downloading user cannot see on the board', function() {
    FinisterrePlugin::get()->userCanViewAllTasks(fn(): bool => false);

    // Without an assignee the task would fall back to user 1, the one signed in.
    $other = User::factory()->create();
    FinisterreTask::factory()->create(['creator_id' => $other->id, 'assignee_id' => $other->id]);

    expect(FinisterreTask::query()->count())->toBe(0)
        ->and(exportedTasks())->toHaveCount(1);
});

it('exports the tasks as not archived on installs without the archived column', function() {
    FinisterreTask::factory()->create();
    Schema::table('finisterre_tasks', fn(Blueprint $table) => $table->dropColumn('archived'));
    ColumnExists::forget();

    expect(exportedTasks()[0]['archived'])->toBe('0');
});

it('downloads the export from the settings page', function() {
    FinisterreTask::factory()->create();

    Livewire::test(ManageFinisterreSettings::class)
        ->assertActionVisible('exportTasks')
        ->callAction('exportTasks')
        ->assertFileDownloaded((new TaskExport)->filename());
});

it('does not let users who cannot configure finisterre download it', function() {
    $page = Livewire::test(ManageFinisterreSettings::class)->assertActionVisible('exportTasks');

    // Access taken away while the page is open: the action refuses to run.
    FinisterrePlugin::get()->userCanConfigureFinisterre(false);

    $page->assertActionHidden('exportTasks');

    $this->get(ManageFinisterreSettings::getUrl())->assertForbidden();
});
