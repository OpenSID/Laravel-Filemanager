<?php

use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Route;
use OpenSID\LaravelFilemanager\Http\Controllers\AjaxController;
use OpenSID\LaravelFilemanager\Http\Controllers\DialogController;
use OpenSID\LaravelFilemanager\Http\Controllers\DownloadController;
use OpenSID\LaravelFilemanager\Http\Controllers\ExecuteController;
use OpenSID\LaravelFilemanager\Http\Controllers\UploadController;
use OpenSID\LaravelFilemanager\Http\Middleware\EnsureFilemanagerAccess;

/*
|--------------------------------------------------------------------------
| Filemanager routes
|--------------------------------------------------------------------------
|
| Plain Laravel route names — no ".php"-styled URIs. The vendored legacy
| assets (resources/dist/js/include.js, and the app's pre-existing TinyMCE
| plugin at assets/js/tinymce-72/plugins/responsivefilemanager/plugin.min.js)
| are static files that can't resolve a Laravel route by name at request
| time, so they call these by ABSOLUTE path instead of relying on relative
| URL resolution against the current page (which would silently break the
| moment the dialog page's URL didn't end in a trailing slash). Those two
| files were edited to call "/{prefix}/..." literally, assuming the
| default prefix — if you change filemanager.route_prefix, re-patch them.
|
*/

// Rate limit for the two state-changing endpoints (upload + execute).
// "maxAttempts,decayMinutes" per authenticated user; null/false disables.
// Chunked uploads count one hit per 2 MB chunk, so keep this generous.
$throttle = config('filemanager.throttle', '120,1');
$writeMiddleware = $throttle ? [ThrottleRequests::class.':'.$throttle] : [];

Route::prefix(config('filemanager.route_prefix', 'filemanager'))
    ->middleware(array_merge(config('filemanager.middleware', ['web', 'auth']), [EnsureFilemanagerAccess::class]))
    ->group(function () use ($writeMiddleware): void {
        Route::get('/', [DialogController::class, 'show'])->name('filemanager.dialog');

        Route::match(['get', 'post'], 'ajax', [AjaxController::class, 'handle'])
            ->name('filemanager.ajax');

        Route::post('execute', [ExecuteController::class, 'handle'])
            ->middleware($writeMiddleware)
            ->name('filemanager.execute');

        Route::match(['get', 'post'], 'upload', function (Request $request, UploadController $controller) {
            return $request->isMethod('GET')
                ? $controller->index($request)
                : $controller->store($request);
        })->middleware($writeMiddleware)->name('filemanager.upload');

        Route::post('download', [DownloadController::class, 'stream'])
            ->name('filemanager.download');
    });
