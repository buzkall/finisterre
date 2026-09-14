<?php

use Arzcode\Finisterre\Controllers\FilamentRouteController;
use Arzcode\Finisterre\Filament\Livewire\FinisterreCommentsComponent;
use Arzcode\Finisterre\Filament\Resources\FinisterreTask\Pages\ViewFinisterreTask;
use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\EditorFiles;
use Filament\Forms\Components\RichEditor;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportFileUploads\FileUploadConfiguration;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\Livewire;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Workbench\App\Models\User;

// The test panel uses the public disk, so the provider registered no routes at
// boot. The private-disk tests switch disks the way a host does and register them.

function attachmentRouteCount(): int
{
    return collect(Route::getRoutes()->getRoutes())
        ->filter(fn($route) => str_starts_with($route->uri(), FilamentRouteController::URL_PATH))
        ->count();
}

function privateTask(User $creator): FinisterreTask
{
    return FinisterreTask::factory()->create([
        'creator_id'  => $creator->id,
        'assignee_id' => $creator->id,
    ]);
}

function privateAttachment(FinisterreTask $task, string $fileName = 'report.pdf'): Media
{
    return $task->addMediaFromString('contents')
        ->usingFileName($fileName)
        ->toMediaCollection('tasks', 'finisterre');
}

/**
 * An image uploaded through a rich editor in the current session, as EditorFiles::store() leaves it.
 */
function pastedImage(string $file): void
{
    Storage::disk('finisterre')->put($file, 'image');
    session()->push(EditorFiles::SESSION_KEY, $file);
}

function attachmentUrl(Media $media, string $conversion = ''): string
{
    return '/' . FilamentRouteController::URL_PATH . '/' . $media->getPathRelativeToRoot($conversion);
}

it('registers no routes while attachments stay on the public disk', function() {
    FilamentRouteController::registerForPrivateDisk();

    expect(attachmentRouteCount())->toBe(0);
});

it('registers the routes once however many times it is asked', function() {
    config()->set('finisterre.attachments_disk', 'finisterre');

    FilamentRouteController::registerForPrivateDisk();
    (new FilamentRouteController)();

    expect(attachmentRouteCount())->toBe(2);
});

describe('on a private disk', function() {
    beforeEach(function() {
        config()->set('filesystems.disks.finisterre', [
            'driver' => 'local',
            'root'   => storage_path('app/finisterre-files'),
        ]);
        config()->set('finisterre.attachments_disk', 'finisterre');
        Storage::fake('finisterre');
        Notification::fake();

        FilamentRouteController::registerForPrivateDisk();

        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);
    });

    it('refuses guests', function() {
        $media = privateAttachment(privateTask($this->owner));
        Storage::disk('finisterre')->put('pasted.png', 'image');

        app('auth')->forgetGuards();

        $this->get(attachmentUrl($media))->assertForbidden();
        $this->get('/' . FilamentRouteController::URL_PATH . '/pasted.png')->assertForbidden();
    });

    it('serves an attachment to a user who can see every task', function() {
        $media = privateAttachment(privateTask(User::factory()->create()));

        $this->get(attachmentUrl($media))->assertOk();
    });

    it('does not decide access with the assignee filter', function() {
        // It narrows down who can be assigned. It used to gate these files too,
        // which refused everybody once the column was left empty.
        config()->set('finisterre.authenticatable_filter_column', 'role');
        config()->set('finisterre.authenticatable_filter_value', 'admin');

        $media = privateAttachment(privateTask($this->owner));

        $this->get(attachmentUrl($media))->assertOk();
    });

    it('serves a user restricted to their own tasks only the attachments of those', function() {
        FinisterrePlugin::get()
            ->userCanViewAllTasks(fn(): bool => false)
            ->userCanViewOnlyTheirTasks(fn(): bool => true);

        $own = privateAttachment(privateTask($this->owner), 'own.pdf');
        $other = privateAttachment(privateTask(User::factory()->create()), 'other.pdf');

        $this->get(attachmentUrl($own))->assertOk();
        $this->get(attachmentUrl($other))->assertForbidden();
    });

    it('refuses a user who has no access to tasks at all', function() {
        FinisterrePlugin::get()
            ->userCanViewAllTasks(fn(): bool => false)
            ->userCanViewOnlyTheirTasks(fn(): bool => false);

        $media = privateAttachment(privateTask($this->owner));

        $this->get(attachmentUrl($media))->assertForbidden();
    });

    it('refuses when the task policy does', function() {
        Gate::before(fn($user, string $ability) => $ability === 'view' ? false : null);

        $media = privateAttachment(privateTask($this->owner));

        $this->get(attachmentUrl($media))->assertForbidden();
    });

    it('serves the card thumbnail under the same rules as the original', function() {
        FinisterrePlugin::get()
            ->userCanViewAllTasks(fn(): bool => false)
            ->userCanViewOnlyTheirTasks(fn(): bool => true);

        $own = privateAttachment(privateTask($this->owner), 'own.png');
        $other = privateAttachment(privateTask(User::factory()->create()), 'other.png');

        foreach ([$own, $other] as $media) {
            $media->markAsConversionGenerated(FinisterreTask::COVER_CONVERSION);
            Storage::disk('finisterre')->put($media->getPathRelativeToRoot(FinisterreTask::COVER_CONVERSION), 'thumbnail');
        }

        $this->get(attachmentUrl($own, FinisterreTask::COVER_CONVERSION))->assertOk();
        $this->get(attachmentUrl($other, FinisterreTask::COVER_CONVERSION))->assertForbidden();
    });

    it('serves nothing but the file its media row names', function() {
        $media = privateAttachment(privateTask($this->owner));
        Storage::disk('finisterre')->put($media->getKey() . '/stray.pdf', 'contents');

        $base = '/' . FilamentRouteController::URL_PATH . '/';

        $this->get($base . $media->getKey() . '/stray.pdf')->assertNotFound();
        $this->get($base . ($media->getKey() + 1) . '/' . $media->file_name)->assertNotFound();
        $this->get($base . $media->getKey() . '/..')->assertNotFound();
        $this->get($base . $media->getKey() . '/conversions/' . $media->file_name)->assertNotFound();
    });

    it('serves a rich editor image to whoever can see the task it was pasted into', function() {
        FinisterrePlugin::get()
            ->userCanViewAllTasks(fn(): bool => false)
            ->userCanViewOnlyTheirTasks(fn(): bool => true);

        $base = '/' . FilamentRouteController::URL_PATH . '/';
        $other = User::factory()->create();

        pastedImage('in-description.png');
        pastedImage('in-comment.png');
        // On the disk, but never uploaded through an editor.
        Storage::disk('finisterre')->put('unused.png', 'image');

        FinisterreTask::factory()->create([
            'creator_id'  => $this->owner->id,
            'assignee_id' => $this->owner->id,
            'description' => '<p><img src="' . $base . 'in-description.png"></p>',
        ]);

        $task = privateTask($this->owner);
        FinisterreTaskComment::create(['task_id' => $task->id, 'comment' => '<img src="' . $base . 'in-comment.png">']);

        $this->flushSession();
        $this->actingAs($other);
        pastedImage('elsewhere.png');
        $otherTask = privateTask($other);
        FinisterreTaskComment::create(['task_id' => $otherTask->id, 'comment' => '<img src="' . $base . 'elsewhere.png">']);

        $this->flushSession();
        $this->actingAs($this->owner);

        $this->get($base . 'in-description.png')->assertOk();
        $this->get($base . 'in-comment.png')->assertOk();
        $this->get($base . 'elsewhere.png')->assertForbidden();
        $this->get($base . 'unused.png')->assertNotFound();
        // Only the whole file name counts.
        $this->get($base . 'in-comment.pn')->assertNotFound();
    });

    it('does not serve a rich editor image to a task it was not pasted into', function() {
        FinisterrePlugin::get()
            ->userCanViewAllTasks(fn(): bool => false)
            ->userCanViewOnlyTheirTasks(fn(): bool => true);

        $base = '/' . FilamentRouteController::URL_PATH . '/';
        $other = User::factory()->create();

        $this->actingAs($other);
        pastedImage('secret.png');
        $otherTask = FinisterreTask::factory()->create([
            'creator_id'  => $other->id,
            'assignee_id' => $other->id,
            'description' => '<img src="' . $base . 'secret.png">',
        ]);

        $this->flushSession();
        $this->actingAs($this->owner);

        // The owner writes the file's URL into a task and a comment of their own.
        $own = FinisterreTask::factory()->create([
            'creator_id'  => $this->owner->id,
            'assignee_id' => $this->owner->id,
            'description' => '<img src="' . $base . 'secret.png">',
        ]);
        FinisterreTaskComment::create(['task_id' => $own->id, 'comment' => '<img src="' . $base . 'secret.png">']);

        $this->get($base . 'secret.png')->assertForbidden();

        expect($otherTask->refresh()->editor_files)->toBe(['secret.png'])
            ->and($own->refresh()->editor_files)->toBeNull();
    });

    it('serves an image nobody has saved yet only to the session that uploaded it', function() {
        $base = '/' . FilamentRouteController::URL_PATH . '/';

        pastedImage('draft.png');

        $this->get($base . 'draft.png')->assertOk();

        // Somebody else who learns the name cannot take it by saving it first.
        $other = User::factory()->create();
        $this->flushSession();
        $this->actingAs($other);

        $task = FinisterreTask::factory()->create([
            'creator_id'  => $other->id,
            'assignee_id' => $other->id,
            'description' => '<img src="' . $base . 'draft.png">',
        ]);

        $this->get($base . 'draft.png')->assertNotFound();

        expect($task->refresh()->editor_files)->toBeNull();
    });

    it('remembers a rich editor upload in the session', function() {
        $upload = UploadedFile::fake()->image('pasted.png');
        $name = $upload->hashName();
        FileUploadConfiguration::storage()->putFileAs('/' . FileUploadConfiguration::path(), $upload, $name);

        // The comment editor as the page mounts it: Filament reads its settings through the component.
        $editor = collect(Livewire::test(FinisterreCommentsComponent::class, ['record' => privateTask($this->owner)])
            ->instance()->form->getFlatComponents())
            ->first(fn($component) => $component instanceof RichEditor);

        $path = EditorFiles::store(TemporaryUploadedFile::createFromLivewire($name), $editor);

        expect(Storage::disk('finisterre')->exists($path))->toBeTrue()
            ->and(session(EditorFiles::SESSION_KEY))->toBe([$path]);
    });

    it('reads the pasted images off the disk in use', function() {
        $html = '<img src="/storage/finisterre-files/private.png"><img src="http://localhost/storage/public.png">';

        expect(EditorFiles::imagesOnDisk($html))->toBe(['private.png']);

        config()->set('finisterre.attachments_disk', 'public');

        expect(EditorFiles::imagesOnDisk($html))->toBe(['public.png']);
    });

    it('uses only a pasted image the task owns as its card image', function() {
        $base = '/' . FilamentRouteController::URL_PATH . '/';
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');

        Storage::disk('finisterre')->put('owned.png', $png);
        Storage::disk('finisterre')->put('borrowed.png', $png);

        // Both are loaded by the description, but only one was claimed by the task.
        $task = FinisterreTask::factory()->create([
            'creator_id'  => $this->owner->id,
            'assignee_id' => $this->owner->id,
            'description' => '<img src="' . $base . 'owned.png"><img src="' . $base . 'borrowed.png">',
        ]);
        EditorFiles::grant(['owned.png'], $task->id);

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->call('setCoverFromEditorImage', 'borrowed.png')
            ->assertNotFound();

        expect($task->refresh()->cover_media_id)->toBeNull();

        Livewire::test(ViewFinisterreTask::class, ['record' => $task->getKey()])
            ->call('setCoverFromEditorImage', 'owned.png')
            ->assertOk();

        $media = $task->refresh()->coverMedia;

        // Copied onto the private disk, and served through the checked route.
        expect($media->disk)->toBe('finisterre')
            ->and($task->coverSourceFile())->toBe('owned.png');

        $this->get(attachmentUrl($media))->assertOk();
    });
});
