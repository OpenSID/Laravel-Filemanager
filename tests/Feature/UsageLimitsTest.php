<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Usage limits & response hardening on the execute/ajax endpoints (#8).
 */
class UsageLimitsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        config(['filemanager.extensions.image' => ['jpg', 'jpeg', 'png']]);
    }

    #[Test]
    public function delete_files_refuses_a_paths_list_over_the_batch_limit_without_deleting_anything(): void
    {
        config(['filemanager.max_batch_paths' => 2]);

        foreach (['a.jpg', 'b.jpg', 'c.jpg'] as $name) {
            Storage::disk('filemanager')->put($name, 'x');
        }

        $response = $this->post(route('filemanager.execute'), [
            'action' => 'delete_files',
            'paths' => ['a.jpg', 'b.jpg', 'c.jpg'],
        ]);

        $response->assertOk();
        $this->assertNotSame('', $response->getContent());
        Storage::disk('filemanager')->assertExists(['a.jpg', 'b.jpg', 'c.jpg']);
    }

    #[Test]
    public function delete_files_within_the_limit_still_works(): void
    {
        config(['filemanager.max_batch_paths' => 2]);

        Storage::disk('filemanager')->put('a.jpg', 'x');
        Storage::disk('filemanager')->put('b.jpg', 'x');

        $this->post(route('filemanager.execute'), [
            'action' => 'delete_files',
            'paths' => ['a.jpg', 'b.jpg'],
        ])->assertOk()->assertContent('');

        Storage::disk('filemanager')->assertMissing(['a.jpg', 'b.jpg']);
    }

    #[Test]
    public function crop_image_enforces_max_upload_size_on_the_decoded_body(): void
    {
        config(['filemanager.max_upload_size' => 1]);
        Storage::disk('filemanager')->put('original.png', $this->png(10, 10));

        $response = $this->post(route('filemanager.execute'), [
            'action' => 'crop_image',
            'path' => 'original.png',
            'name_new' => 'besar.png',
            // A real, valid PNG (random noise, ~1.2 MB) — so the only thing
            // that can reject it is the size limit itself.
            'image_data' => 'data:image/png;base64,'.base64_encode($this->noisePng(640, 640)),
        ]);

        $response->assertOk();
        $this->assertNotSame('', $response->getContent());
        Storage::disk('filemanager')->assertMissing('besar.png');
    }

    #[Test]
    public function crop_image_rejects_an_image_over_the_pixel_limit(): void
    {
        config(['filemanager.max_image_pixels' => 50 * 50]);
        Storage::disk('filemanager')->put('original.png', $this->png(10, 10));

        $response = $this->post(route('filemanager.execute'), [
            'action' => 'crop_image',
            'path' => 'original.png',
            'name_new' => 'bom.png',
            'image_data' => 'data:image/png;base64,'.base64_encode($this->png(100, 100)),
        ]);

        $this->assertNotSame('', $response->getContent());
        Storage::disk('filemanager')->assertMissing('bom.png');
    }

    #[Test]
    public function crop_image_respects_max_total_size(): void
    {
        config(['filemanager.max_total_size' => 1]);
        Storage::disk('filemanager')->put('original.png', $this->png(10, 10));
        Storage::disk('filemanager')->put('isi.jpg', str_repeat('x', 1024 * 1024));

        $response = $this->post(route('filemanager.execute'), [
            'action' => 'crop_image',
            'path' => 'original.png',
            'name_new' => 'baru.png',
            'image_data' => 'data:image/png;base64,'.base64_encode($this->png(20, 20)),
        ]);

        $this->assertNotSame('', $response->getContent());
        Storage::disk('filemanager')->assertMissing('baru.png');
    }

    #[Test]
    public function a_valid_crop_still_succeeds(): void
    {
        Storage::disk('filemanager')->put('original.png', $this->png(10, 10));

        $this->post(route('filemanager.execute'), [
            'action' => 'crop_image',
            'path' => 'original.png',
            'name_new' => 'hasil.png',
            'image_data' => 'data:image/png;base64,'.base64_encode($this->png(20, 20)),
        ])->assertOk()->assertContent('');

        Storage::disk('filemanager')->assertExists('hasil.png');
    }

    #[Test]
    public function execute_and_ajax_responses_carry_nosniff_and_no_store(): void
    {
        $responses = [
            $this->post(route('filemanager.execute'), ['action' => 'not_a_real_action']),
            $this->post(route('filemanager.ajax'), ['action' => 'clear_clipboard']),
        ];

        foreach ($responses as $response) {
            $response->assertHeader('X-Content-Type-Options', 'nosniff');
            $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        }
    }

    #[Test]
    public function the_execute_route_is_rate_limited(): void
    {
        $route = app('router')->getRoutes()->getByName('filemanager.execute');

        $this->assertContains(ThrottleRequests::class.':120,1', $route->gatherMiddleware());
    }

    #[Test]
    public function the_rate_limit_actually_kicks_in(): void
    {
        // Cheap end-to-end check with the real default limiter key (the
        // logged-in user): request #121 inside one minute gets a 429.
        for ($i = 0; $i < 120; $i++) {
            $this->post(route('filemanager.execute'), ['action' => 'noop'])->assertOk();
        }

        $this->post(route('filemanager.execute'), ['action' => 'noop'])->assertStatus(429);
    }

    protected function noisePng(int $width, int $height): string
    {
        mt_srand(8);
        $image = imagecreatetruecolor($width, $height);

        for ($x = 0; $x < $width; $x++) {
            for ($y = 0; $y < $height; $y++) {
                imagesetpixel($image, $x, $y, mt_rand(0, 0xFFFFFF));
            }
        }

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }

    protected function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
