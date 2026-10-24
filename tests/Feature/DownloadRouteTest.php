<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DownloadRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    #[Test]
    public function download_route_allows_downloading_allowed_files(): void
    {
        Storage::disk('filemanager')->put('document.pdf', 'pdf-content');

        $response = $this->post(route('filemanager.download'), [
            'path' => '',
            'name' => 'document.pdf',
        ]);

        $response->assertOk();
    }

    #[Test]
    public function download_route_blocks_downloading_hidden_or_disallowed_files(): void
    {
        Storage::disk('filemanager')->put('.htaccess', 'deny from all');
        Storage::disk('filemanager')->put('config.php', '<?php return [];');

        $this->post(route('filemanager.download'), [
            'path' => '',
            'name' => '.htaccess',
        ])->assertForbidden();

        $this->post(route('filemanager.download'), [
            'path' => '',
            'name' => 'config.php',
        ])->assertForbidden();
    }

    #[Test]
    public function download_route_blocks_a_blacklisted_extension_not_in_hidden_extensions(): void
    {
        // .phar isn't in hidden_extensions but is blacklisted — a legacy
        // file like this must not be downloadable.
        Storage::disk('filemanager')->put('legacy.phar', 'PK payload');

        $this->post(route('filemanager.download'), [
            'path' => '',
            'name' => 'legacy.phar',
        ])->assertForbidden();
    }

    #[Test]
    public function an_empty_path_resolves_to_base_folder_not_the_disk_root(): void
    {
        // Regresi #7: path '' lolos isInsideBaseFolder() dan dulu disusun
        // menjadi '/rahasia.pdf' — berkas di root disk, di luar base_folder.
        config(['filemanager.base_folder' => 'desa/upload']);

        Storage::disk('filemanager')->put('rahasia.pdf', 'di luar base_folder');
        Storage::disk('filemanager')->put('desa/upload/dokumen.pdf', 'di dalam base_folder');

        $this->post(route('filemanager.download'), [
            'path' => '',
            'name' => 'rahasia.pdf',
        ])->assertNotFound();

        $this->post(route('filemanager.download'), [
            'path' => '',
            'name' => 'dokumen.pdf',
        ])->assertOk();
    }

    #[Test]
    public function a_path_outside_base_folder_is_rejected(): void
    {
        config(['filemanager.base_folder' => 'desa/upload']);

        Storage::disk('filemanager')->put('lain/rahasia.pdf', 'di luar base_folder');

        $this->post(route('filemanager.download'), [
            'path' => 'lain',
            'name' => 'rahasia.pdf',
        ])->assertStatus(400);
    }

    #[Test]
    public function download_only_serves_whitelisted_extensions(): void
    {
        // Tidak di-blacklist, tetapi juga tidak ada di whitelist "extensions".
        foreach (['.env', 'dump.sql', 'laravel.log', 'kunci.pem', 'composer.json', 'tanpa-ekstensi'] as $name) {
            Storage::disk('filemanager')->put($name, 'rahasia');

            $this->post(route('filemanager.download'), [
                'path' => '',
                'name' => $name,
            ])->assertForbidden();
        }
    }

    #[Test]
    public function editable_text_files_are_downloadable_only_while_text_editing_is_enabled(): void
    {
        Storage::disk('filemanager')->put('catatan.txt', 'halo');

        config(['filemanager.text_editing_enabled' => false]);
        $this->post(route('filemanager.download'), ['path' => '', 'name' => 'catatan.txt'])->assertForbidden();

        config(['filemanager.text_editing_enabled' => true]);
        $this->post(route('filemanager.download'), ['path' => '', 'name' => 'catatan.txt'])->assertOk();
    }
}
