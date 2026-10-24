<?php

namespace OpenSID\LaravelFilemanager\Support;

class FilemanagerConfig
{
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
            $diskRoot = $this->diskRoot();

            // If the disk root already points to or ends with base_folder, relative baseFolder is disk root ('')
            if ($diskRoot !== '' && ($diskRoot === $baseFolder || str_ends_with($diskRoot, '/'.$baseFolder))) {
                return '';
            }

            // If base_folder was written relative to base_path() and starts with the disk root
            // (e.g. disk root base_path('desa/upload'), base_folder='desa/upload/media' => 'media').
            // Matched on whole segments: disk root '.../media' must not eat 'media-extra/x'.
            $relativeDiskRoot = $this->relativeDiskRoot();
            if ($relativeDiskRoot !== '') {
                $stripped = self::stripPathPrefix($baseFolder, $relativeDiskRoot);
                if ($stripped !== null) {
                    return $stripped;
                }
            }
        } catch (\Throwable) {
        }

        return $baseFolder;
    }

    /**
     * Disk root relative to base_path() (or the full root when the disk lives
     * outside the project), '/'-separated without surrounding slashes. Lets a
     * path written relative to the project be mapped onto the disk.
     */
    public function relativeDiskRoot(): string
    {
        $diskRoot = $this->diskRoot();
        $basePath = trim(str_replace('\\', '/', (string) base_path()), '/');

        return self::stripPathPrefix($diskRoot, $basePath) ?? $diskRoot;
    }

    /**
     * Remainder of $path below $prefix, or null when $path is neither $prefix
     * itself nor inside it. Matches on whole segments only, so prefix 'desa'
     * does not match 'desa2' and 'media' does not match 'media-extra/x'.
     */
    public static function stripPathPrefix(string $path, string $prefix): ?string
    {
        $path = trim($path, '/');
        $prefix = trim($prefix, '/');

        if ($prefix === '') {
            return $path;
        }

        if ($path === $prefix) {
            return '';
        }

        return str_starts_with($path, $prefix.'/')
            ? substr($path, strlen($prefix) + 1)
            : null;
    }

    protected function diskRoot(): string
    {
        $diskConfig = config('filesystems.disks.'.$this->disk(), []);

        return trim(str_replace('\\', '/', (string) ($diskConfig['root'] ?? '')), '/');
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

        return $mb === false ? false : ((int) $mb * 1024 * 1024);
    }
}
