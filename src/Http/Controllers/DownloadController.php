<?php

namespace OpenSID\LaravelFilemanager\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use OpenSID\LaravelFilemanager\Exceptions\UnsafePathException;
use OpenSID\LaravelFilemanager\Services\FilesystemManager;
use OpenSID\LaravelFilemanager\Services\PathGuard;

/**
 * Replaces rfm/force_download.php. Storage::download() already streams with
 * correct headers (and Range support, for the "local" driver) — no more
 * manual mime detection / chunked fread loop.
 */
class DownloadController extends Controller
{
    public function __construct(protected FilesystemManager $files) {}

    public function stream(Request $request)
    {
        $baseFolder = $this->files->config()->baseFolder();
        $path = trim((string) $request->input('path', ''), '/');
        $name = (string) $request->input('name', '');

        // An empty path means "the filemanager root", i.e. base_folder — NOT
        // the disk root. PathGuard::isInsideBaseFolder('') is true, so without
        // this a file sitting outside base_folder could be downloaded (same
        // substitution ExecuteController::safePath() does).
        if ($path === '') {
            $path = $baseFolder;
        }

        if ($name === '' || str_contains($name, '/') || str_contains($name, '\\')) {
            abort(400, trans('filemanager::filemanager.wrong path'));
        }

        $fullPath = trim($path.'/'.$name, '/');

        try {
            PathGuard::assertSafe($path);
            PathGuard::assertSafe($name);
            PathGuard::assertInsideBaseFolder($path, $baseFolder);
            PathGuard::assertInsideBaseFolder($fullPath, $baseFolder);
        } catch (UnsafePathException) {
            abort(400, trans('filemanager::filemanager.wrong path'));
        }

        // Whitelist, not just a blacklist: ".env", ".sql", ".log", ".pem" …
        // are not blacklisted but must never be served. Checked before
        // exists() so a refused name can't be used to probe the disk.
        if (! $this->files->config()->isFileAccessible($name)) {
            abort(403, trans('filemanager::filemanager.File_Permission_Not_Allowed'));
        }

        if (! $this->files->exists($fullPath)) {
            abort(404, trans('filemanager::filemanager.File_Not_Found'));
        }

        return $this->files->disk()->download($fullPath, basename($fullPath));
    }
}
