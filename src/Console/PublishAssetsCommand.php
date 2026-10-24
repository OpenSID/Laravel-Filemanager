<?php

namespace OpenSID\LaravelFilemanager\Console;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

/**
 * Publishes the bundled static assets (CSS/JS/img) to the host's webroot.
 * Uses a symlink when the platform allows it, so a `composer update` is
 * enough to refresh the assets; otherwise falls back to copying the files
 * (what `vendor:publish --tag=filemanager-assets` always does).
 */
class PublishAssetsCommand extends Command
{
    protected $signature = 'filemanager:assets
                            {--copy : Salin berkas, jangan coba symlink}
                            {--force : Timpa tujuan yang sudah ada}';

    protected $description = 'Publikasikan aset filemanager: symlink bila didukung, bila tidak salin berkas.';

    public function handle(Filesystem $files): int
    {
        $source = realpath(__DIR__.'/../../resources/dist');
        $target = base_path((string) config('filemanager.assets_path', 'assets/vendor/filemanager'));

        if ($source === false) {
            $this->error('Direktori sumber aset tidak ditemukan.');

            return self::FAILURE;
        }

        if (is_link($target) || file_exists($target)) {
            if (is_link($target) && realpath($target) === $source && ! $this->option('copy')) {
                $this->info("Symlink sudah benar: {$target}");

                return self::SUCCESS;
            }

            if (! $this->option('force')) {
                $this->error("Tujuan sudah ada: {$target} (gunakan --force untuk menimpa).");

                return self::FAILURE;
            }

            $this->removeTarget($files, $target);
        }

        $files->ensureDirectoryExists(dirname($target));

        if (! $this->option('copy') && $this->symlink($source, $target)) {
            $this->info("Symlink dibuat: {$target} -> {$source}");

            return self::SUCCESS;
        }

        if (! $this->option('copy')) {
            $this->warn('Symlink tidak didukung di lingkungan ini, beralih ke salin berkas.');
        }

        if (! $files->copyDirectory($source, $target)) {
            $this->error("Gagal menyalin aset ke {$target}.");

            return self::FAILURE;
        }

        $this->info("Aset disalin ke: {$target}");

        return self::SUCCESS;
    }

    protected function symlink(string $source, string $target): bool
    {
        if (! function_exists('symlink')) {
            return false;
        }

        return @symlink($source, $target) && is_link($target);
    }

    protected function removeTarget(Filesystem $files, string $target): void
    {
        // A symlinked directory must be unlinked, not recursively emptied,
        // or deleteDirectory() would wipe the package's own source files.
        if (is_link($target)) {
            if (! @unlink($target)) {
                @rmdir($target); // Windows directory junctions/symlinks
            }

            return;
        }

        is_dir($target) ? $files->deleteDirectory($target) : $files->delete($target);
    }
}
