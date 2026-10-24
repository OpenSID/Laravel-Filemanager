<?php

namespace OpenSID\LaravelFilemanager\Services;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use OpenSID\LaravelFilemanager\Events\FilemanagerActionPerformed;

/**
 * Audit trail for mutating actions: dispatches FilemanagerActionPerformed
 * and (config filemanager.audit_log.enabled) writes one Log::info line.
 * Never throws — a broken log channel must not fail the file operation
 * that already succeeded.
 */
class AuditLogger
{
    /**
     * @param  array<string, string>|list<string>  $paths
     */
    public function record(string $action, array $paths): void
    {
        $userId = Auth::id();
        $ip = request()->ip();

        try {
            event(new FilemanagerActionPerformed($action, $paths, $userId, $ip));

            if (config('filemanager.audit_log.enabled', true)) {
                Log::channel(config('filemanager.audit_log.channel'))->info('filemanager.'.$action, [
                    'user_id' => $userId,
                    'action' => $action,
                    'paths' => $paths,
                    'ip' => $ip,
                ]);
            }
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
