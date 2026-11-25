<?php

use Illuminate\Http\Request;
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
| bundle (resources/dist/js/include.js) is a static file that can't resolve
| a Laravel route by name, so dialog.blade.php hands it the real URLs via
| `window.FILEMANAGER_ROUTES` (ajax/execute/upload, built with route()).
| Changing filemanager.route_prefix or serving the app from a sub-folder
| therefore needs no re-patching of the package's JS. A host-side TinyMCE
| plugin that opens the dialog should likewise build its URL from
| route('filemanager.dialog') rather than a literal "/filemanager".
|
*/

Route::prefix(config('filemanager.route_prefix', 'filemanager'))
    ->middleware(array_merge(config('filemanager.middleware', ['web', 'auth']), [EnsureFilemanagerAccess::class]))
    ->group(function (): void {
        Route::get('/', [DialogController::class, 'show'])->name('filemanager.dialog');

        Route::match(['get', 'post'], 'ajax', [AjaxController::class, 'handle'])
            ->name('filemanager.ajax');

        Route::post('execute', [ExecuteController::class, 'handle'])
            ->name('filemanager.execute');

        Route::match(['get', 'post'], 'upload', function (Request $request, UploadController $controller) {
            return $request->isMethod('GET')
                ? $controller->index($request)
                : $controller->store($request);
        })->name('filemanager.upload');

        Route::post('download', [DownloadController::class, 'stream'])
            ->name('filemanager.download');
    });
