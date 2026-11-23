<?php

namespace OpenSID\LaravelFilemanager\Support;

class FilemanagerConfig
{
    /** Ukuran potongan bawaan blueimp (2 MiB). */
    public const DEFAULT_CHUNK_SIZE = 2097152;

    /** Potongan terkecil yang masih wajar (256 KiB). */
    public const MIN_CHUNK_SIZE = 262144;

    /** Ruang untuk header multipart dan field form per request (128 KiB). */
    public const CHUNK_OVERHEAD_RESERVE = 131072;

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

        return $mb === false ? false : ((int) $mb * 1024 * 1024);
    }

    /**
     * Ukuran potongan (byte) uploader chunked agar satu request tetap muat di bawah
     * post_max_size dan upload_max_filesize PHP. Potongan bawaan 2 MiB ditambah
     * overhead multipart melebihi batas 2M yang lazim di hosting dan memicu HTTP 413.
     * Batas tak terbatas ("0", "-1") diabaikan.
     */
    public function maxChunkSizeBytes(?string $postMaxSize = null, ?string $uploadMaxFilesize = null): int
    {
        $limits = array_filter([
            $this->iniSizeToBytes($postMaxSize ?? (string) ini_get('post_max_size')),
            $this->iniSizeToBytes($uploadMaxFilesize ?? (string) ini_get('upload_max_filesize')),
        ], static fn (int $bytes): bool => $bytes > 0);

        if ($limits === []) {
            return self::DEFAULT_CHUNK_SIZE;
        }

        $safe = min($limits) - self::CHUNK_OVERHEAD_RESERVE;

        return max(self::MIN_CHUNK_SIZE, min(self::DEFAULT_CHUNK_SIZE, $safe));
    }

    /**
     * Ubah notasi php.ini ("2M", "512K", "1G", "-1") menjadi byte; 0 bila tak terbatas/tak valid.
     */
    public function iniSizeToBytes(string $value): int
    {
        if (! preg_match('/^\s*(\d+)\s*([kmg])?b?\s*$/i', $value, $matches)) {
            return 0;
        }

        $number = (int) $matches[1];

        return match (strtolower($matches[2] ?? '')) {
            'g' => $number * 1073741824,
            'm' => $number * 1048576,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
