<?php

namespace OpenSID\LaravelFilemanager\Support;

class FilemanagerConfig
{
    /**
     * Per-instance memo for values derived from config() that are asked for
     * many times per request (list(), every PathGuard check, every upload).
     * The class is resolved fresh from the container (not a singleton), so
     * a runtime config()->set() is still picked up by the next instance.
     */
    protected ?string $baseFolderMemo = null;

    /** @var array<string, array<int, string>> */
    protected array $extensionsMemo = [];

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
        return $this->baseFolderMemo ??= $this->resolveBaseFolder();
    }

    protected function resolveBaseFolder(): string
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
        return $this->extensionsMemo['all'] ??= array_merge(...array_values(config('filemanager.extensions', [])));
    }

    public function allowedExtensionsForType(?int $type = null): array
    {
        return match ($type) {
            1 => $this->extensionsMemo['image'] ??= config('filemanager.extensions.image', []),
            3 => $this->extensionsMemo['video'] ??= config('filemanager.extensions.video', []),
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
