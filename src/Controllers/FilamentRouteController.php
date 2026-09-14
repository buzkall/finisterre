<?php

namespace Arzcode\Finisterre\Controllers;

use Arzcode\Finisterre\FinisterrePlugin;
use Arzcode\Finisterre\Models\FinisterreTask;
use Arzcode\Finisterre\Models\FinisterreTaskComment;
use Arzcode\Finisterre\Support\EditorFiles;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves the files of a private attachments disk to the users allowed to see the
 * task they belong to.
 *
 * Such a disk lives outside public/, so every attachment, card image and rich
 * editor image reaches the browser through the two routes below: media library
 * files (and their conversions) under {id}/, and editor images at the root.
 */
class FilamentRouteController extends Controller
{
    /** The path both routes are served under, and the one rich editor images are rewritten to. */
    public const URL_PATH = 'storage/finisterre-files';

    /**
     * Kept for hosts that still register the routes from bootstrap/app.php, as the
     * package asked before it registered them itself.
     */
    public function __invoke(): void
    {
        static::register();
    }

    /**
     * The public disk is served by the web server itself, so the routes are only
     * worth having once attachments live somewhere it cannot reach.
     */
    public static function registerForPrivateDisk(): void
    {
        if ((config('finisterre.attachments_disk') ?? 'public') === 'public') {
            return;
        }

        static::register();
    }

    public static function register(): void
    {
        // The service provider and a host's bootstrap/app.php can both get here. The
        // flag lives in the container rather than a static, so a fresh application
        // (every test gets one) starts without it.
        if (app()->bound('finisterre.attachment-routes') || app()->routesAreCached()) {
            return;
        }

        app()->instance('finisterre.attachment-routes', true);

        // The web middleware gives us the logged-in user. The auth middleware would
        // redirect to a login page instead of refusing, so the controller checks.
        Route::middleware('web')->group(function() {
            Route::get(self::URL_PATH . '/{id}/{file}', [static::class, 'media'])
                // Media library keeps a conversion one directory deeper, as
                // {id}/conversions/{name}-finisterre-card.jpg, and a route parameter
                // stops at the first slash. Naming the one extra segment rather than
                // allowing `.*` keeps `..` out of the path.
                ->where('file', '(conversions/)?[^/]+');

            Route::get(self::URL_PATH . '/{file}', [static::class, 'editorFile']);
        });
    }

    /**
     * An attachment, or one of its conversions: the media row says which task owns it.
     */
    public function media(string $id, string $file): BinaryFileResponse
    {
        $user = $this->authenticate();
        $path = $id . '/' . $file;

        /** @var class-string<Media> $model */
        $model = config('media-library.media_model') ?? Media::class;
        $media = $model::query()->find($id);

        abort_unless($media instanceof Media && $this->mediaServes($media, $path), 404);

        // A host may map tasks to a morph alias, which media library then stores.
        $class = Relation::getMorphedModel($media->model_type) ?? $media->model_type;

        abort_unless(is_a($class, FinisterreTask::class, true), 404);

        $task = FinisterreTask::withoutGlobalScopes()->find($media->model_id);

        abort_unless($task instanceof FinisterreTask, 404);
        abort_unless($this->canViewTask($user, $task), 403);

        return $this->serve($path);
    }

    /**
     * An image pasted into a description or a comment. It belongs to the task it was
     * first saved in (see EditorFiles) and is served while a description or comment
     * of that task still loads it. Loading it from another task unlocks nothing.
     * Before it is saved anywhere, only the session that uploaded it gets it, so the
     * uploader sees it in the editor.
     */
    public function editorFile(string $file): BinaryFileResponse
    {
        $user = $this->authenticate();

        if (EditorFiles::uploadedInSession($file)) {
            return $this->serve($file);
        }

        abort_unless(EditorFiles::columnExists(), 404);

        // LIKE narrows it down cheaply; the regular expression then insists on the
        // whole file name, so `a.png` is not unlocked by a task that loads `a.png.bak`
        // and an `_` in a name does not match any character.
        $like = '%finisterre-files/' . $file . '%';

        $taskIds = FinisterreTask::withoutGlobalScopes()
            ->where('description', 'like', $like)
            ->get(['id', 'description'])
            ->filter(fn(FinisterreTask $task) => $this->loads($task->description, $file))
            ->pluck('id')
            ->merge(FinisterreTaskComment::query()
                // A comment still waiting to be sent is only visible to its author.
                ->visibleTo($user->getAuthIdentifier())
                ->where('comment', 'like', $like)
                ->get(['task_id', 'comment'])
                ->filter(fn(FinisterreTaskComment $comment) => $this->loads($comment->comment, $file))
                ->pluck('task_id'))
            ->unique();

        $owners = FinisterreTask::withoutGlobalScopes()
            ->whereKey($taskIds)
            ->get()
            ->filter(fn(FinisterreTask $task) => EditorFiles::belongsTo($task, $file));

        abort_if($owners->isEmpty(), 404);
        abort_unless($owners->contains(fn(FinisterreTask $task) => $this->canViewTask($user, $task)), 403);

        return $this->serve($file);
    }

    protected function authenticate(): Authenticatable
    {
        $guard = config('finisterre.guard');
        $user = auth()->guard($guard)->user();

        abort_if($user === null, 403);

        // These routes are not panel routes, but what decides access is written for
        // one: the plugin's callbacks and the policies call auth()->user() and look
        // the plugin up on the current panel.
        Auth::shouldUse($guard);
        rescue(fn() => Filament::setCurrentPanel(Filament::getPanel(config('finisterre.panel_slug'))), report: false);

        return $user;
    }

    /**
     * The same rules the panel applies before it shows a task: the task policy, and
     * for users restricted to their own tasks, only the ones they created. The global
     * scope that enforces the latter in the panel is not trusted here, since it is
     * registered once per process and may have been set up for somebody else.
     */
    protected function canViewTask(Authenticatable $user, FinisterreTask $task): bool
    {
        // Anything that cannot be decided (no panel, a policy that throws) refuses.
        return rescue(function() use ($user, $task): bool {
            $gate = Gate::forUser($user);

            if (! $gate->allows('viewAny', FinisterreTask::class) || ! $gate->allows('view', $task)) {
                return false;
            }

            return ! FinisterrePlugin::get()->canViewOnlyTheirTasks()
                || (string)$task->creator_id === (string)$user->getAuthIdentifier();
        }, false);
    }

    /**
     * Whether the path is this media's file or one of its generated conversions,
     * so an id cannot be paired with some other file in its directory.
     */
    protected function mediaServes(Media $media, string $path): bool
    {
        $conversions = array_keys(array_filter((array)$media->generated_conversions));

        foreach (['', ...$conversions] as $conversion) {
            if (rescue(fn() => $media->getPathRelativeToRoot($conversion), report: false) === $path) {
                return true;
            }
        }

        return false;
    }

    protected function loads(?string $html, string $file): bool
    {
        return in_array($file, EditorFiles::in($html), true);
    }

    protected function serve(string $path): BinaryFileResponse
    {
        $disk = Storage::disk(config('finisterre.attachments_disk') ?? 'public');

        abort_unless(rescue(fn() => $disk->exists($path), false, report: false), 404);

        return response()->file($disk->path($path));
    }
}
