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

    /**
     * Normalised config('filemanager.allowed_origins'): only well-formed
     * "scheme://host[:port]" entries survive, lower-cased and without a
     * trailing slash, so they are safe to drop verbatim into a
     * Content-Security-Policy header and to compare against a request's
     * Origin/Referer.
     *
     * @return list<string>
     */
    public function allowedOrigins(): array
    {
        $origins = [];

        foreach ((array) config('filemanager.allowed_origins', []) as $origin) {
            $origin = strtolower(rtrim(trim((string) $origin), '/'));

            if (preg_match('#^https?://[a-z0-9.-]+(:\d{1,5})?$#', $origin)) {
                $origins[] = $origin;
            }
        }

        return array_values(array_unique($origins));
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

    /**
     * Whitelist check for an EXISTING file the user wants to download,
     * copy or move (as opposed to isExtensionAllowed(), which gates new
     * uploads/renames). Editable text extensions are only accepted while
     * text editing is enabled, since that is the only way such files are
     * meant to end up inside the filemanager. A blacklist alone is not
     * enough here: ".env", ".sql", ".log", ".pem" … are not blacklisted.
     */
    public function isExtensionAccessible(string $extension): bool
    {
        $extension = mb_strtolower(ltrim($extension, '.'));

        if ($extension === '' || $this->isExtensionBlacklisted($extension) || $this->isHiddenExtension($extension)) {
            return false;
        }

        if ($this->isExtensionAllowed($extension)) {
            return true;
        }

        return config('filemanager.text_editing_enabled') && $this->isEditableTextExtension($extension);
    }

    /**
     * Same rules deleteFile/renameFile apply, bundled for download and
     * clipboard copy/cut/paste: hidden/protective files (.htaccess,
     * index.html, …) stay put and non-whitelisted extensions are refused.
     */
    public function isFileAccessible(string $name): bool
    {
        return ! $this->isHiddenFile($name)
            && $this->isFilenameSafe($name)
            && $this->isExtensionAccessible(pathinfo($name, PATHINFO_EXTENSION));
    }

    /**
     * Whether $path may be copied/cut to the clipboard (and pasted from
     * it): files go through isFileAccessible(); folders must not be a
     * hidden folder nor the base_folder root itself.
     */
    public function isClipboardSourceAllowed(string $path, bool $isDirectory): bool
    {
        $path = trim(str_replace('\\', '/', $path), '/');
        $name = basename($path);

        if ($path === '' || $name === '') {
            return false;
        }

        if ($isDirectory) {
            return $path !== $this->baseFolder() && ! $this->isHiddenFolder($name);
        }

        return $this->isFileAccessible($name);
    }

    /**
     * True when no base_folder narrows the scope AND the disk root is the
     * application root itself — every operation (download, delete, rename,
     * cut) can then reach .env, vendor/, storage/ and so on.
     */
    public function exposesApplicationRoot(): bool
    {
        if ($this->baseFolder() !== '') {
            return false;
        }

        $root = config('filesystems.disks.'.$this->disk().'.root');
        if (! is_string($root) || $root === '') {
            return false;
        }

        $normalize = static fn (string $path): string => rtrim(str_replace('\\', '/', (string) (realpath($path) ?: $path)), '/');

        return $normalize($root) === $normalize(base_path());
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
