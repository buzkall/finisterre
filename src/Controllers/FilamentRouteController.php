<?php

namespace Buzkall\Finisterre\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

class FilamentRouteController extends Controller
{
    public function __invoke(): void
    {
        // we use the web middleware to get the logged user
        // can't use the auth middleware because it will redirect to the login -> we check it manually
        Route::group(['middleware' => 'web'], function() {
            Route::get('storage/finisterre-files/{id}/{file}', function($id, $file) {
                // we check if the user is logged in
                abort_if(auth()->guard(config('finisterre.guard'))->guest(), 403);

                $disk = config('finisterre.attachments_disk') ?? 'public';
                $filePath = $id . DIRECTORY_SEPARATOR . $file;

                // file exists
                abort_unless(Storage::disk($disk)->exists($filePath), 404);

                // access is filtered by user
                abort_unless(auth()->guard(config('finisterre.guard'))->user()->{config('finisterre.authenticatable_filter_column')} ==
                    config('finisterre.authenticatable_filter_value'), 403);

                return response()->file(Storage::disk($disk)->path($filePath));
            });
        });
    }
}
