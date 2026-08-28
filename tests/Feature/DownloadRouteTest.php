<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

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
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);
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
}
