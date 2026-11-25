<?php

namespace OpenSID\LaravelFilemanager\Console;

use Illuminate\Console\Command;
use OpenSID\LaravelFilemanager\Support\FilemanagerConfig;

/**
 * Deletes chunk-upload staging files that were never completed (the client
 * went away before sending the final Content-Range chunk). Without this,
 * every abandoned upload leaves up to max_upload_size bytes behind in
 * storage/app/filemanager-chunks forever.
 *
 * Scheduled hourly by the service provider; can also be run by hand.
 */
class PruneChunksCommand extends Command
{
    protected $signature = 'filemanager:prune-chunks
                            {--minutes= : Hapus staging yang tidak berubah lebih lama dari N menit (bawaan: filemanager.chunk_ttl_minutes)}';

    protected $description = 'Menghapus berkas staging unggahan chunk filemanager yang terbengkalai.';

    public function handle(FilemanagerConfig $config): int
    {
        $minutes = $this->option('minutes') !== null ? max(1, (int) $this->option('minutes')) : $config->chunkTtlMinutes();
        $directory = $config->chunkStagingDirectory();
        $threshold = time() - ($minutes * 60);
        $deleted = 0;

        if (is_dir($directory)) {
            foreach (new \DirectoryIterator($directory) as $file) {
                if ($file->isFile() && $file->getMTime() < $threshold && @unlink($file->getPathname())) {
                    $deleted++;
                }
            }
        }

        $this->info(sprintf('Selesai: %d berkas staging berumur > %d menit dihapus.', $deleted, $minutes));

        return self::SUCCESS;
    }
}
