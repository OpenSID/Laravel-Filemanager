<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PublishAssetsCommandTest extends TestCase
{
    protected string $relative = 'storage/framework/testing/assets-out';

    protected function setUp(): void
    {
        parent::setUp();

        config(['filemanager.assets_path' => $this->relative]);
        $this->cleanup();
    }

    protected function tearDown(): void
    {
        $this->cleanup();

        parent::tearDown();
    }

    protected function cleanup(): void
    {
        $target = base_path($this->relative);

        if (is_link($target)) {
            @unlink($target);
        } elseif (is_dir($target)) {
            (new Filesystem)->deleteDirectory($target);
        }
    }

    #[Test]
    public function it_symlinks_the_assets_when_supported(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('symlink() tidak tersedia.');
        }

        $this->artisan('filemanager:assets')->assertSuccessful();

        $target = base_path($this->relative);

        if (! is_link($target)) {
            $this->markTestSkipped('Lingkungan menolak symlink; jalur salin diuji terpisah.');
        }

        $this->assertSame(realpath(__DIR__.'/../../resources/dist'), realpath($target));
        $this->assertFileExists($target.'/js/include.js');
    }

    #[Test]
    public function copy_option_copies_files_instead_of_linking(): void
    {
        $this->artisan('filemanager:assets --copy')->assertSuccessful();

        $target = base_path($this->relative);

        $this->assertFalse(is_link($target));
        $this->assertFileExists($target.'/js/include.js');
    }

    #[Test]
    public function it_refuses_to_overwrite_without_force_and_replaces_with_it(): void
    {
        $this->artisan('filemanager:assets --copy')->assertSuccessful();
        $this->artisan('filemanager:assets --copy')->assertFailed();
        $this->artisan('filemanager:assets --copy --force')->assertSuccessful();

        $this->assertFileExists(base_path($this->relative).'/js/include.js');
    }

    #[Test]
    public function forcing_over_a_symlink_never_deletes_the_package_sources(): void
    {
        if (! function_exists('symlink')) {
            $this->markTestSkipped('symlink() tidak tersedia.');
        }

        $this->artisan('filemanager:assets')->assertSuccessful();

        if (! is_link(base_path($this->relative))) {
            $this->markTestSkipped('Lingkungan menolak symlink.');
        }

        $this->artisan('filemanager:assets --copy --force')->assertSuccessful();

        $this->assertFileExists(__DIR__.'/../../resources/dist/js/include.js');
        $this->assertFalse(is_link(base_path($this->relative)));
    }
}
