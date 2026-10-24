<?php

namespace OpenSID\LaravelFilemanager\Support;

class FilemanagerConfig
{
    /**
     * Whitelist for the user-controlled list state stored in the session.
     * Anything else (including array input like sort_by[]=x, which used to
     * reach DialogController::sortEntries(string) and fatal with a 500)
     * collapses to the default.
     */
    public const SORT_FIELDS = ['name', 'date', 'size', 'extension'];

    public const VIEW_TYPES = [0, 1, 2];

    public function disk(): string
    {
        return (string) config('filemanager.disk', 'filemanager');
    }

    public function thumbsDisk(): string
    {
        return (string) config('filemanager.thumbs_disk', 'filemanager_thumbs');
    }

    public function baseFolder(): string
    {
        $baseFolder = trim(str_replace('\\', '/', (string) config('filemanager.base_folder', '')), '/');
        if ($baseFolder === '') {
            return '';
        }

        try {
            $diskConfig = config('filesystems.disks.'.$this->disk(), []);
            $diskRoot = trim(str_replace('\\', '/', (string) ($diskConfig['root'] ?? '')), '/');

            // If the disk root already points to or ends with base_folder, relative baseFolder is disk root ('')
            if ($diskRoot !== '' && ($diskRoot === $baseFolder || str_ends_with($diskRoot, '/'.$baseFolder))) {
                return '';
            }

            // If base_folder starts with part of diskRoot (e.g. diskRoot='desa/upload', base_folder='desa/upload/media')
            $basePath = trim(str_replace('\\', '/', (string) base_path()), '/');
            $relativeDiskRoot = str_starts_with($diskRoot, $basePath)
                ? trim(substr($diskRoot, strlen($basePath)), '/')
                : $diskRoot;

            if ($relativeDiskRoot !== '' && str_starts_with($baseFolder, $relativeDiskRoot)) {
                return trim(substr($baseFolder, strlen($relativeDiskRoot)), '/');
            }
        } catch (\Throwable) {
        }

        return $baseFolder;
    }

    public function allowedExtensions(): array
    {
        return array_merge(...array_values(config('filemanager.extensions', [])));
    }

    public function allowedExtensionsForType(?int $type = null): array
    {
        return match ($type) {
            1 => config('filemanager.extensions.image', []),
            3 => config('filemanager.extensions.video', []),
            default => $this->allowedExtensions(),
        };
    }

    public function acceptAttributeForType(?int $type = null): string
    {
        $exts = $this->allowedExtensionsForType($type);

        return implode(',', array_map(fn ($ext) => '.'.ltrim($ext, '.'), $exts));
    }

    public function isExtensionBlacklisted(string $extension): bool
    {
        return in_array(mb_strtolower(ltrim($extension, '.')), config('filemanager.blacklisted_extensions', []), true);
    }

    public function isExtensionAllowed(string $extension): bool
    {
        $extension = mb_strtolower(ltrim($extension, '.'));

        if ($this->isExtensionBlacklisted($extension)) {
            return false;
        }

        if ($extension === '') {
            return false;
        }

        return in_array($extension, $this->allowedExtensions(), true);
    }

    /**
     * Defense-in-depth against double-extension tricks (e.g. "shell.php.jpg")
     * that pass isExtensionAllowed() (which only looks at the final
     * extension) but embed a blacklisted extension as a middle segment —
     * relevant on servers with legacy multiviews-style execution behaviour.
     * Checked in addition to, not instead of, isExtensionAllowed().
     */
    public function isFilenameSafe(string $filename): bool
    {
        $segments = explode('.', $filename);
        array_pop($segments); // the real, already-validated final extension

        $blacklist = config('filemanager.blacklisted_extensions', []);

        foreach ($segments as $segment) {
            if (in_array(mb_strtolower($segment), $blacklist, true)) {
                return false;
            }
        }

        return true;
    }

    public function isEditableTextExtension(string $extension): bool
    {
        return in_array(mb_strtolower(ltrim($extension, '.')), config('filemanager.editable_text_extensions', []), true);
    }

    public function isHiddenFile(string $name): bool
    {
        return in_array($name, config('filemanager.hidden_files', []), true);
    }

    public function isHiddenFolder(string $name): bool
    {
        return in_array($name, config('filemanager.hidden_folders', []), true);
    }

    public function isHiddenExtension(string $extension): bool
    {
        return in_array(mb_strtolower(ltrim($extension, '.')), config('filemanager.hidden_extensions', []), true);
    }

    public function maxUploadSizeBytes(): int
    {
        return $this->maxUploadSizeMb() * 1024 * 1024;
    }

    public function maxUploadSizeMb(): int
    {
        return (int) config('filemanager.max_upload_size', 8);
    }

    public function maxTotalSizeBytes(): int|false
    {
        $mb = config('filemanager.max_total_size', false);

        return ($mb === false || $mb === null) ? false : ((int) $mb * 1024 * 1024);
    }

    /**
     * Upper bound on width × height for any raster image the package will
     * decode (thumbnail, crop, upload validation). GD allocates ~4-5 bytes
     * per pixel, so a tiny highly-compressed PNG declaring 30000×30000
     * pixels would need ~4 GB — a fatal "Allowed memory size exhausted"
     * that no try/catch can intercept. 0/false disables the check.
     */
    public function maxImagePixels(): int
    {
        return (int) config('filemanager.max_image_pixels', 25_000_000);
    }

    /**
     * maxImagePixels() as a human "25" / "12.5" megapixel label for
     * error messages.
     */
    public function maxImageMegapixelsLabel(): string
    {
        return rtrim(rtrim(number_format($this->maxImagePixels() / 1_000_000, 1, '.', ''), '0'), '.');
    }

    /**
     * Maximum number of entries accepted by a single bulk action
     * (delete_files' paths[]).
     */
    public function maxBatchPaths(): int
    {
        return max(1, (int) config('filemanager.max_batch_paths', 200));
    }

    /**
     * Abandoned chunk-upload staging files older than this are removed by
     * `filemanager:prune-chunks`.
     */
    public function chunkTtlMinutes(): int
    {
        return max(1, (int) config('filemanager.chunk_ttl_minutes', 60));
    }

    /**
     * Local directory where chunked uploads are reassembled before they
     * are validated and handed to the configured disk.
     */
    public function chunkStagingDirectory(): string
    {
        return storage_path('app/filemanager-chunks');
    }

    public function sanitizeSortBy(mixed $value): string
    {
        return is_string($value) && in_array($value, self::SORT_FIELDS, true) ? $value : 'name';
    }

    public function sanitizeViewType(mixed $value, int $default = 0): int
    {
        $default = in_array($default, self::VIEW_TYPES, true) ? $default : 0;

        if (! is_scalar($value) || ! is_numeric($value)) {
            return $default;
        }

        return in_array((int) $value, self::VIEW_TYPES, true) ? (int) $value : $default;
    }

    public function sanitizeFilter(mixed $value): string
    {
        return is_scalar($value) ? mb_substr((string) $value, 0, 100) : '';
    }
}
