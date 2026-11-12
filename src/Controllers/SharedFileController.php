<?php

namespace Arzcode\Finisterre\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Routing\Middleware\ValidateSignature;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Serves a task's file to whoever has a short-lived signed link to it, with no
 * login. The links are put in the markdown that "Copy as markdown" builds, so a
 * coding assistant the task is pasted into can open its images and attachments.
 * Only the server signs a link, and it only does so for a file of a task that the
 * user copying it can see.
 */
class SharedFileController extends Controller
{
    public const PATH = 'finisterre/shared-files';

    /** How long a link opens its file, in minutes. */
    public const MINUTES = 10;

    public static function register(): void
    {
        if (app()->routesAreCached()) {
            return;
        }

        // Always there, whatever the disk, so a cached route list keeps it. No web
        // middleware: the signature stands in for the session.
        Route::get(self::PATH . '/{disk}/{path}', static::class)
            ->where('path', '.+')
            ->middleware(ValidateSignature::class)
            ->name('finisterre.shared-file');
    }

    public static function url(string $disk, string $path): string
    {
        return URL::temporarySignedRoute('finisterre.shared-file', now()->addMinutes(self::MINUTES), [
            'disk' => $disk,
            'path' => $path,
        ]);
    }

    public function __invoke(string $disk, string $path): BinaryFileResponse
    {
        abort_unless(is_array(config('filesystems.disks.' . $disk)), 404);

        $storage = Storage::disk($disk);

        abort_unless(rescue(fn() => $storage->exists($path), false, report: false), 404);

        return response()->file($storage->path($path));
    }
}
