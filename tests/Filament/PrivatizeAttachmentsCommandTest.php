<?php

use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Support\AttachmentsDisk;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Workbench\App\Models\User;

beforeEach(function() {
    config()->set('filesystems.disks.finisterre', [
        'driver' => 'local',
        'root'   => storage_path('app/finisterre-files'),
    ]);
    config()->set('finisterre.attachments_disk', 'finisterre');
    Storage::fake('public');
    Storage::fake('finisterre');
    Notification::fake();

    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

function legacyTask(string $html): FinisterreTask
{
    return FinisterreTask::factory()->create([
        'creator_id'  => auth()->id(),
        'assignee_id' => auth()->id(),
        'description' => $html,
    ]);
}

function legacyComment(FinisterreTask $task, string $html): int
{
    return DB::table('finisterre_task_comments')->insertGetId([
        'task_id'    => $task->id,
        'creator_id' => $task->creator_id,
        'comment'    => $html,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

it('changes nothing without --force', function() {
    Storage::disk('public')->put('old.png', 'image');
    $task = legacyTask('<p><img src="https://app.test/storage/old.png"></p>');

    $this->artisan('finisterre:privatize-attachments')->assertSuccessful();

    expect(Storage::disk('public')->exists('old.png'))->toBeTrue()
        ->and(Storage::disk('finisterre')->exists('old.png'))->toBeFalse()
        ->and($task->refresh()->description)->toBe('<p><img src="https://app.test/storage/old.png"></p>');
});

it('moves the images and points descriptions and comments at the private route', function() {
    Storage::disk('public')->put('old.png', 'image');
    Storage::disk('public')->put('shared.jpg', 'image');
    $task = legacyTask('<p><img src="https://app.test/storage/old.png"><img src="/storage/shared.jpg"></p>');
    $commentId = legacyComment($task, '<img src="/storage/shared.jpg">');

    $this->artisan('finisterre:privatize-attachments', ['--force' => true])->assertSuccessful();

    expect($task->refresh()->description)
        ->toBe('<p><img src="/storage/finisterre-files/old.png"><img src="/storage/finisterre-files/shared.jpg"></p>')
        ->and(DB::table('finisterre_task_comments')->where('id', $commentId)->value('comment'))
        ->toBe('<img src="/storage/finisterre-files/shared.jpg">')
        ->and(Storage::disk('finisterre')->get('old.png'))->toBe('image')
        ->and(Storage::disk('finisterre')->exists('shared.jpg'))->toBeTrue()
        ->and(Storage::disk('public')->exists('old.png'))->toBeFalse()
        ->and(Storage::disk('public')->exists('shared.jpg'))->toBeFalse()
        // The route serves them to the task that loads them.
        ->and($task->refresh()->editor_files)->toBe(['old.png', 'shared.jpg']);
});

it('refuses to run before the editor_files column exists', function() {
    Schema::table('finisterre_tasks', fn(Blueprint $table) => $table->dropColumn('editor_files'));
    Storage::disk('public')->put('old.png', 'image');
    $task = legacyTask('<img src="/storage/old.png">');

    $this->artisan('finisterre:privatize-attachments', ['--force' => true])->assertFailed();

    expect($task->refresh()->description)->toBe('<img src="/storage/old.png">')
        ->and(Storage::disk('public')->exists('old.png'))->toBeTrue();
});

it('removes what it copied of an attachment when one of its files cannot be copied', function() {
    $task = legacyTask('');
    $media = $task->addMediaFromString('contents')->usingFileName('report.pdf')->toMediaCollection('tasks', 'public');
    Storage::disk('public')->put($media->getKey() . '/conversions/report-finisterre-card.jpg', 'thumbnail');

    // The original copies; its conversion does not.
    $fake = Storage::disk('finisterre');
    Storage::set('finisterre', new class($fake->getDriver(), $fake->getAdapter(), $fake->getConfig()) extends FilesystemAdapter
    {
        public function writeStream($path, $resource, array $options = [])
        {
            return str_contains($path, 'conversions/') ? false : parent::writeStream($path, $resource, $options);
        }
    });

    $this->artisan('finisterre:privatize-attachments', ['--force' => true])->assertSuccessful();

    expect($media->refresh()->disk)->toBe('public')
        ->and(Storage::disk('finisterre')->allFiles())->toBe([])
        ->and(Storage::disk('public')->exists($media->getKey() . '/report.pdf'))->toBeTrue()
        ->and(Storage::disk('public')->exists($media->getKey() . '/conversions/report-finisterre-card.jpg'))->toBeTrue();
});

it('leaves an image alone when its file is on neither disk', function() {
    $task = legacyTask('<img src="/storage/gone.png">');

    $this->artisan('finisterre:privatize-attachments', ['--force' => true])->assertSuccessful();

    expect($task->refresh()->description)->toBe('<img src="/storage/gone.png">');
});

it('leaves images that are already private and files in subdirectories alone', function() {
    Storage::disk('public')->put('avatars/me.png', 'image');
    $html = '<img src="/storage/finisterre-files/new.png"><img src="/storage/avatars/me.png">';
    $task = legacyTask($html);

    $this->artisan('finisterre:privatize-attachments', ['--force' => true])->assertSuccessful();

    expect($task->refresh()->description)->toBe($html)
        ->and(Storage::disk('public')->exists('avatars/me.png'))->toBeTrue();
});

it('copies instead of moving with --keep-originals', function() {
    Storage::disk('public')->put('old.png', 'image');
    $task = legacyTask('<img src="/storage/old.png">');

    $this->artisan('finisterre:privatize-attachments', ['--force' => true, '--keep-originals' => true])->assertSuccessful();

    expect($task->refresh()->description)->toBe('<img src="/storage/finisterre-files/old.png">')
        ->and(Storage::disk('finisterre')->exists('old.png'))->toBeTrue()
        ->and(Storage::disk('public')->exists('old.png'))->toBeTrue();
});

it('refuses to run while attachments still go to the public disk', function() {
    config()->set('finisterre.attachments_disk', 'public');
    Storage::disk('public')->put('old.png', 'image');
    $task = legacyTask('<img src="/storage/old.png">');

    $this->artisan('finisterre:privatize-attachments', ['--force' => true])->assertFailed();

    expect($task->refresh()->description)->toBe('<img src="/storage/old.png">');
});

it('moves task attachments with their conversions and keeps their ids', function() {
    $task = legacyTask('');
    $media = $task->addMediaFromString('contents')->usingFileName('report.pdf')->toMediaCollection('tasks', 'public');
    Storage::disk('public')->put($media->getKey() . '/conversions/report-finisterre-card.jpg', 'thumbnail');
    // Another file in the same directory that is not this attachment's.
    Storage::disk('public')->put($media->getKey() . '/conversions/unrelated.jpg', 'other');

    expect(AttachmentsDisk::publicLeftovers())->toBe(['attachments' => 1, 'contents' => 0]);

    $this->artisan('finisterre:privatize-attachments', ['--force' => true])->assertSuccessful();

    $media->refresh();

    expect($media->disk)->toBe('finisterre')
        ->and($media->conversions_disk)->toBe('finisterre')
        ->and(Storage::disk('finisterre')->get($media->getKey() . '/report.pdf'))->toBe('contents')
        ->and(Storage::disk('finisterre')->exists($media->getKey() . '/conversions/report-finisterre-card.jpg'))->toBeTrue()
        ->and(Storage::disk('finisterre')->exists($media->getKey() . '/conversions/unrelated.jpg'))->toBeFalse()
        ->and(Storage::disk('public')->allFiles())->toBe([$media->getKey() . '/conversions/unrelated.jpg'])
        ->and($task->refresh()->getFirstMedia('tasks')?->getKey())->toBe($media->getKey())
        ->and(AttachmentsDisk::publicLeftovers())->toBe(['attachments' => 0, 'contents' => 0]);
});

it('leaves task attachments where they are without --force', function() {
    $task = legacyTask('');
    $media = $task->addMediaFromString('contents')->usingFileName('report.pdf')->toMediaCollection('tasks', 'public');

    $this->artisan('finisterre:privatize-attachments')->assertSuccessful();

    expect($media->refresh()->disk)->toBe('public')
        ->and(Storage::disk('public')->exists($media->getKey() . '/report.pdf'))->toBeTrue()
        ->and(Storage::disk('finisterre')->allFiles())->toBe([]);
});

it('counts only the pasted images whose file is still on the public disk as left behind', function() {
    Storage::disk('public')->put('old.png', 'image');
    legacyTask('<img src="/storage/old.png">');
    legacyTask('<img src="/storage/gone.png">');

    expect(AttachmentsDisk::publicLeftovers())->toBe(['attachments' => 0, 'contents' => 1]);
});
