<?php

namespace OpenSID\LaravelFilemanager\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use OpenSID\LaravelFilemanager\Services\ThumbnailGenerator;

/**
 * Queued thumbnail generation for uploads, used instead of the synchronous
 * ThumbnailGenerator::make() call in UploadController when
 * config('filemanager.thumbnail_queue') is enabled — keeps image decoding /
 * resizing (and the disk round-trips to read the original and write the
 * thumbnail) out of the upload request.
 */
class GenerateThumbnail implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $sourceDisk,
        public string $sourcePath,
        public string $thumbDisk,
        public string $thumbPath,
    ) {}

    public function handle(ThumbnailGenerator $thumbnails): void
    {
        // make() already swallows decode failures; a deleted/renamed source
        // by the time the worker runs simply yields no thumbnail.
        $thumbnails->make($this->sourceDisk, $this->sourcePath, $this->thumbDisk, $this->thumbPath);
    }
}
