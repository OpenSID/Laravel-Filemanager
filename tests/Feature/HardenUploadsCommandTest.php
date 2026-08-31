<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Services\FilesystemManager;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class HardenUploadsCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');
    }

    #[Test]
    public function it_stamps_a_hardened_htaccess_into_the_root_and_every_subfolder(): void
    {
        Storage::disk('filemanager')->makeDirectory('galeri/2026');
        Storage::disk('filemanager')->put('galeri/.htaccess', 'old weak rule');

        $this->artisan('filemanager:harden')->assertSuccessful();

        foreach (['.htaccess', 'galeri/.htaccess', 'galeri/2026/.htaccess'] as $path) {
            Storage::disk('filemanager')->assertExists($path);
            $contents = Storage::disk('filemanager')->get($path);

            $this->assertStringContainsString('(?i)', $contents);
            $this->assertStringContainsString('php_flag engine off', $contents);
            $this->assertStringContainsString('SetHandler None', $contents);
            $this->assertStringContainsString('nosniff', $contents);
        }

        Storage::disk('filemanager')->assertExists('index.html');
    }

    #[Test]
    public function creating_a_folder_through_the_package_protects_it_immediately(): void
    {
        app(FilesystemManager::class)->makeDirectory('baru');

        Storage::disk('filemanager')->assertExists('baru/.htaccess');
        Storage::disk('filemanager')->assertExists('baru/index.html');
    }
}
