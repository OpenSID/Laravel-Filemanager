<?php

namespace OpenSID\LaravelFilemanager\Services;

use Illuminate\Support\Facades\Storage;
use Spatie\Image\Enums\Fit;
use Spatie\Image\Image;

/**
 * Replaces rfm's create_img()/new_thumbnails_creation() and the entire
 * hand-rolled include/php_image_magician.php (3800+ lines of GD code) with
 * spatie/image, disk-agnostic via temp files so it works for any Flysystem
 * driver, not just "local".
 */
class ThumbnailGenerator
{
    /**
     * Generate a cropped thumbnail for an image already stored on $sourceDisk
     * at $sourcePath, writing it to $thumbDisk at $thumbPath.
     */
    public function make(
        string $sourceDisk,
        string $sourcePath,
        string $thumbDisk,
        string $thumbPath,
        ?int $width = null,
        ?int $height = null
    ): bool {
        if (! $this->isImage($sourcePath)) {
            return false;
        }

        $width ??= (int) config('filemanager.thumbnail_width', 122);
        $height ??= (int) config('filemanager.thumbnail_height', 91);

        $source = Storage::disk($sourceDisk);
        $extension = pathinfo($sourcePath, PATHINFO_EXTENSION) ?: 'jpg';

        // tempnam() creates the extension-less file itself; only the
        // suffixed name is used, but both must be cleaned up.
        $tmpInBase = tempnam(sys_get_temp_dir(), 'rfmIn');
        $tmpOutBase = tempnam(sys_get_temp_dir(), 'rfmOut');
        $tmpIn = $tmpInBase.'.'.$extension;
        $tmpOut = $tmpOutBase.'.'.$extension;

        try {
            // Stream disk -> temp file (and back below) instead of holding
            // the whole original in memory via get()/file_get_contents().
            $this->streamToLocal($source->readStream($sourcePath), $tmpIn);

            // Pin the driver (default: GD). spatie/image otherwise auto-
            // selects Imagick whenever ext-imagick is present, and running
            // freshly-uploaded, only-loosely-validated files through
            // ImageMagick's coder/delegate stack is exactly the surface
            // behind ImageTragick & the Ghostscript delegate RCEs. A host
            // that has hardened its ImageMagick policy.xml can opt back in
            // via config('filemanager.image_driver').
            Image::useImageDriver((string) config('filemanager.image_driver', 'gd'))
                ->loadFile($tmpIn)
                ->fit(Fit::Crop, $width, $height)
                ->save($tmpOut);

            $out = fopen($tmpOut, 'rb');
            try {
                $written = Storage::disk($thumbDisk)->writeStream($thumbPath, $out);
            } finally {
                if (is_resource($out)) {
                    fclose($out);
                }
            }

            return $written !== false;
        } catch (\Throwable) {
            // Never let a thumbnail failure take down the file operation
            // that triggered it (upload/rename/etc already succeeded by
            // this point) — e.g. GD (the only driver available on a host
            // without Imagick) can't rasterize SVG at all, and can choke
            // on malformed or exotic raster files too.
            return false;
        } finally {
            @unlink($tmpIn);
            @unlink($tmpOut);
            @unlink($tmpInBase);
            @unlink($tmpOutBase);
        }
    }

    /**
     * @param  resource|null  $stream
     */
    protected function streamToLocal($stream, string $localPath): void
    {
        if (! is_resource($stream)) {
            throw new \RuntimeException('Unable to read source image.');
        }

        $target = fopen($localPath, 'wb');

        try {
            stream_copy_to_stream($stream, $target);
        } finally {
            fclose($target);
            fclose($stream);
        }
    }

    public function isImage(string $path): bool
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (! in_array($extension, config('filemanager.extensions.image', []), true)) {
            return false;
        }

        // Not rasterizable by GD (the only driver available when Imagick
        // isn't installed) — skip thumbnailing rather than fail on every
        // attempt; the grid falls back to a generic/original preview.
        return ! in_array($extension, ['svg', 'ico'], true);
    }
}
