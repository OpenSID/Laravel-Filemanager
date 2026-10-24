<?php

namespace OpenSID\LaravelFilemanager\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after every successful mutating filemanager action (upload,
 * delete, rename, paste, ...) so the host app can feed its own activity
 * log. See AuditLogger.
 */
class FilemanagerActionPerformed
{
    use Dispatchable;

    /**
     * @param  string  $action  e.g. "upload", "delete_file", "rename_file", "paste_cut"
     * @param  array<string, string>|list<string>  $paths  affected path(s), relative to the disk root
     */
    public function __construct(
        public readonly string $action,
        public readonly array $paths,
        public readonly int|string|null $userId,
        public readonly ?string $ip,
    ) {}
}
