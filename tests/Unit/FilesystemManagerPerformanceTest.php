<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Mockery;
use OpenSID\LaravelFilemanager\Services\FilesystemManager;
use OpenSID\LaravelFilemanager\Support\FilemanagerConfig;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression tests for #9 — fewer disk round-trips (matters on S3/FTP/SFTP
 * where every exists()/size()/put() is a network request).
 */
class FilesystemManagerPerformanceTest extends TestCase
{
    protected FilesystemManager $files;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->files = new FilesystemManager;
    }

    #[Test]
    public function thumbnail_index_lists_existing_thumbnails_of_one_folder_in_a_single_pass(): void
    {
        Storage::disk('filemanager_thumbs')->put('foto/a.jpg', 'x');
        Storage::disk('filemanager_thumbs')->put('foto/b.png', 'x');
        Storage::disk('filemanager_thumbs')->put('foto/sub/c.jpg', 'x');

        $index = $this->files->thumbnailIndex('foto');

        $this->assertSame(['foto/a.jpg' => true, 'foto/b.png' => true], $this->sortedKeys($index));
        $this->assertSame([], $this->files->thumbnailIndex('tidak-ada'));
    }

    #[Test]
    public function write_operations_do_not_probe_the_thumbs_disk_with_exists_first(): void
    {
        Storage::disk('filemanager')->put('a.jpg', 'x');
        Storage::disk('filemanager')->put('b.jpg', 'x');

        $thumbs = Mockery::mock(Filesystem::class);
        $thumbs->shouldNotReceive('exists');
        $thumbs->shouldReceive('delete')->once()->with('a.jpg')->andReturn(false);
        // no thumbnail to move: a throwing thumbs disk must not fail the move
        $thumbs->shouldReceive('move')->once()->with('b.jpg', 'c.jpg')->andThrow(new \RuntimeException('missing'));
        $thumbs->shouldReceive('copy')->once()->with('c.jpg', 'd.jpg')->andReturn(false);
        $thumbs->shouldReceive('deleteDirectory')->once()->with('folder')->andReturn(false);
        Storage::set('filemanager_thumbs', $thumbs);

        $this->assertTrue($this->files->deleteFile('a.jpg'));
        $this->assertTrue($this->files->move('b.jpg', 'c.jpg'));
        $this->assertTrue($this->files->copy('c.jpg', 'd.jpg'));
        Storage::disk('filemanager')->makeDirectory('folder');
        $this->assertTrue($this->files->deleteDirectory('folder'));

        Storage::disk('filemanager')->assertMissing('a.jpg');
        Storage::disk('filemanager')->assertExists(['c.jpg', 'd.jpg']);
    }

    #[Test]
    public function write_operations_still_mirror_existing_thumbnails(): void
    {
        Storage::disk('filemanager')->put('a.jpg', 'x');
        Storage::disk('filemanager_thumbs')->put('a.jpg', 't');

        $this->files->move('a.jpg', 'b.jpg');
        Storage::disk('filemanager_thumbs')->assertMissing('a.jpg');
        Storage::disk('filemanager_thumbs')->assertExists('b.jpg');

        $this->files->copy('b.jpg', 'c.jpg');
        Storage::disk('filemanager_thumbs')->assertExists('c.jpg');

        $this->files->deleteFile('b.jpg');
        Storage::disk('filemanager_thumbs')->assertMissing('b.jpg');
    }

    #[Test]
    public function protect_directory_only_rewrites_files_whose_content_differs(): void
    {
        $disk = Storage::disk('filemanager');
        $disk->makeDirectory('dok');
        $this->files->protectDirectory('dok');

        $htaccess = $disk->path('dok/.htaccess');
        $index = $disk->path('dok/index.html');
        $original = (string) file_get_contents($htaccess);

        // backdate both; an unchanged file must keep its old mtime
        touch($htaccess, 1000000000);
        touch($index, 1000000000);
        $disk->put('dok/index.html', 'lama');
        touch($index, 1000000000);
        clearstatcache();

        $this->files->protectDirectory('dok');
        clearstatcache();

        $this->assertSame(1000000000, filemtime($htaccess), '.htaccess identik tidak boleh ditulis ulang');
        $this->assertSame($original, file_get_contents($htaccess));
        $this->assertNotSame('lama', file_get_contents($index), 'index.html yang berbeda harus ditulis ulang');
    }

    #[Test]
    public function directory_stats_sums_sizes_from_one_recursive_listing(): void
    {
        $disk = Storage::disk('filemanager');
        $disk->put('arsip/a.txt', str_repeat('a', 10));
        $disk->put('arsip/x/b.txt', str_repeat('b', 20));
        $disk->put('arsip/x/y/c.txt', str_repeat('c', 5));

        $this->assertSame([35, 3, 2], $this->files->directoryStats('arsip'));
    }

    #[Test]
    public function config_memoizes_base_folder_per_instance(): void
    {
        config(['filemanager.base_folder' => 'desa/upload']);
        $config = new FilemanagerConfig;
        $this->assertSame('desa/upload', $config->baseFolder());

        config(['filemanager.base_folder' => 'lain']);
        $this->assertSame('desa/upload', $config->baseFolder());
        $this->assertSame('lain', (new FilemanagerConfig)->baseFolder());
    }

    /**
     * @param  array<string, true>  $index
     * @return array<string, true>
     */
    protected function sortedKeys(array $index): array
    {
        ksort($index);

        return $index;
    }
}
