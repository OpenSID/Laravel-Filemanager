<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UploadRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        config([
            'filemanager.extensions.image' => ['jpg', 'jpeg', 'png'],
            'filemanager.max_upload_size' => 8,
        ]);
    }

    #[Test]
    public function uploads_an_allowed_file_and_returns_its_url(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg', 400, 300);

        $response = $this->post(route('filemanager.upload'), [
            'fldr' => '',
            'files' => [$file],
        ]);

        $response->assertOk();
        $body = $response->json();

        $this->assertArrayHasKey('files', $body);
        $this->assertSame('photo.jpg', $body['files'][0]['name']);
        $this->assertArrayHasKey('url', $body['files'][0]);

        Storage::disk('filemanager')->assertExists('photo.jpg');
    }

    #[Test]
    public function reports_the_actual_size_limit_when_a_file_is_too_large(): void
    {
        // Regression: max_size_reached used to be an untranslated stock
        // string with a raw "%d" that trans() never substitutes, so the
        // user saw the literal text "...maximale size of %d MB." instead
        // of the configured limit. 'id' is the locale OpenSID host apps
        // actually run under (config/app.php: APP_LOCALE default 'id').
        app()->setLocale('id');
        config(['filemanager.max_upload_size' => 2]);

        $file = UploadedFile::fake()->create('big.jpg', 3 * 1024)->size(3 * 1024 * 1024);

        $response = $this->post(route('filemanager.upload'), [
            'fldr' => '',
            'files' => [$file],
        ]);

        $response->assertOk();
        $error = $response->json()['files'][0]['error'];

        $this->assertStringContainsString('2', $error);
        $this->assertStringNotContainsString('%d', $error);
        $this->assertStringNotContainsString(':size', $error);
    }

    #[Test]
    public function rejects_a_disallowed_extension_without_storing_anything(): void
    {
        $file = UploadedFile::fake()->create('shell.php', 10);

        $response = $this->post(route('filemanager.upload'), [
            'fldr' => '',
            'files' => [$file],
        ]);

        $response->assertOk();
        $body = $response->json();

        $this->assertArrayHasKey('error', $body['files'][0]);
        Storage::disk('filemanager')->assertMissing('shell.php');
    }

    #[Test]
    public function rejects_a_php_webshell_disguised_as_a_jpg_even_though_the_extension_is_allowed(): void
    {
        // shell.php gets caught by the extension blacklist alone; the real
        // point of this test is that renaming it to an *allowed* extension
        // doesn't let it through — the content itself has to be validated.
        $tmp = tempnam(sys_get_temp_dir(), 'shell');
        file_put_contents($tmp, "<?php system(\$_GET['cmd']); ?>");
        $file = new UploadedFile($tmp, 'photo.jpg', 'image/jpeg', null, true);

        $response = $this->post(route('filemanager.upload'), [
            'fldr' => '',
            'files' => [$file],
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json()['files'][0]);
        Storage::disk('filemanager')->assertMissing('photo.jpg');
    }

    #[Test]
    public function rejects_a_double_extension_that_embeds_a_blacklisted_extension(): void
    {
        $file = UploadedFile::fake()->create('shell.php.jpg', 10);

        $response = $this->post(route('filemanager.upload'), [
            'fldr' => '',
            'files' => [$file],
        ]);

        $body = $response->json();

        $this->assertArrayHasKey('error', $body['files'][0]);
        Storage::disk('filemanager')->assertMissing('shell.php.jpg');
    }

    #[Test]
    public function rejects_a_path_traversal_attempt_in_the_target_folder(): void
    {
        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post(route('filemanager.upload'), [
            'fldr' => '../../etc',
            'files' => [$file],
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json()['files'][0]);
    }

    #[Test]
    public function a_second_upload_with_the_same_name_gets_a_unique_filename_instead_of_overwriting(): void
    {
        Storage::disk('filemanager')->put('photo.jpg', 'existing content');

        $file = UploadedFile::fake()->image('photo.jpg');

        $response = $this->post(route('filemanager.upload'), [
            'fldr' => '',
            'files' => [$file],
        ]);

        $storedName = $response->json()['files'][0]['name'];

        $this->assertNotSame('photo.jpg', $storedName);
        $this->assertSame('existing content', Storage::disk('filemanager')->get('photo.jpg'));
    }

    #[Test]
    public function reassembles_a_chunked_upload_across_two_requests(): void
    {
        $bytes = "PK\x03\x04".str_repeat('A', 3 * 1024 * 1024 - 4); // 3MB, valid zip signature
        $chunk1 = substr($bytes, 0, 2 * 1024 * 1024);
        $chunk2 = substr($bytes, 2 * 1024 * 1024);

        config(['filemanager.extensions.archive' => ['zip']]);
        config(['filemanager.extensions' => array_merge(config('filemanager.extensions'), ['archive' => ['zip']])]);

        $tmp1 = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp1, $chunk1);
        $file1 = new UploadedFile($tmp1, 'bigfile.zip', 'application/zip', null, true);

        $tmp2 = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp2, $chunk2);
        $file2 = new UploadedFile($tmp2, 'bigfile.zip', 'application/zip', null, true);

        // A real browser carries its session cookie on every chunk; staging
        // is keyed by that session, so the test must do the same.
        $cookies = $this->sessionCookie(str_repeat('a', 40));

        $this->call('POST', route('filemanager.upload'), ['fldr' => ''], $cookies, ['files' => [$file1]], [
            'HTTP_Content-Range' => 'bytes 0-2097151/3145728',
        ])->assertOk();

        Storage::disk('filemanager')->assertMissing('bigfile.zip');

        $final = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], $cookies, ['files' => [$file2]], [
            'HTTP_Content-Range' => 'bytes 2097152-3145727/3145728',
        ]);

        $final->assertOk();
        Storage::disk('filemanager')->assertExists('bigfile.zip');
        $this->assertSame($bytes, Storage::disk('filemanager')->get('bigfile.zip'));
    }

    #[Test]
    public function chunked_upload_rejects_chunks_whose_total_bytes_exceed_the_declared_size(): void
    {
        config(['filemanager.max_upload_size' => 8]);
        config(['filemanager.extensions.archive' => ['zip']]);
        config(['filemanager.extensions' => array_merge(config('filemanager.extensions'), ['archive' => ['zip']])]);

        // Declares total = 1 MB, then tries to stream a 2 MB chunk into staging.
        $oversized = "PK\x03\x04".str_repeat('A', 2 * 1024 * 1024);
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, $oversized);
        $file = new UploadedFile($tmp, 'bomb.zip', 'application/zip', null, true);

        $response = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], [], ['files' => [$file]], [
            'HTTP_Content-Range' => 'bytes 0-2097156/1048576',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json()['files'][0]);
        Storage::disk('filemanager')->assertMissing('bomb.zip');
    }

    #[Test]
    public function chunked_upload_rejects_a_non_contiguous_chunk(): void
    {
        config(['filemanager.extensions.archive' => ['zip']]);
        config(['filemanager.extensions' => array_merge(config('filemanager.extensions'), ['archive' => ['zip']])]);

        // Second chunk (offset 2 MB) with no first chunk ever sent.
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, str_repeat('B', 1024));
        $file = new UploadedFile($tmp, 'gappy.zip', 'application/zip', null, true);

        $response = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], [], ['files' => [$file]], [
            'HTTP_Content-Range' => 'bytes 2097152-2098175/3145728',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json()['files'][0]);
        Storage::disk('filemanager')->assertMissing('gappy.zip');
    }

    #[Test]
    public function a_chunk_from_another_session_cannot_continue_someone_elses_staging_file(): void
    {
        // Regression (#8): staging used to be sha1(folder|name) only, so a
        // second operator uploading the same filename into the same folder
        // appended into — and corrupted or hijacked — the first one's file.
        config(['filemanager.extensions' => array_merge(config('filemanager.extensions'), ['archive' => ['zip']])]);

        $tmp1 = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp1, "PK\x03\x04".str_repeat('A', 2 * 1024 * 1024 - 4));
        $file1 = new UploadedFile($tmp1, 'shared.zip', 'application/zip', null, true);

        $this->call('POST', route('filemanager.upload'), ['fldr' => ''], $this->sessionCookie(str_repeat('a', 40)), ['files' => [$file1]], [
            'HTTP_Content-Range' => 'bytes 0-2097151/3145728',
        ])->assertOk();

        $tmp2 = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp2, str_repeat('B', 1024 * 1024));
        $file2 = new UploadedFile($tmp2, 'shared.zip', 'application/zip', null, true);

        $response = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], $this->sessionCookie(str_repeat('b', 40)), ['files' => [$file2]], [
            'HTTP_Content-Range' => 'bytes 2097152-3145727/3145728',
        ]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json()['files'][0]);
        Storage::disk('filemanager')->assertMissing('shared.zip');
    }

    #[Test]
    public function max_total_size_is_enforced_on_upload(): void
    {
        // Regression (#8): max_total_size existed in config but was never read.
        config(['filemanager.max_total_size' => 1]);
        Storage::disk('filemanager')->put('existing.jpg', str_repeat('x', 900 * 1024));

        $file = UploadedFile::fake()->image('photo.jpg', 400, 300)->size(200);

        $response = $this->post(route('filemanager.upload'), ['fldr' => '', 'files' => [$file]]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json()['files'][0]);
        Storage::disk('filemanager')->assertMissing('photo.jpg');
    }

    #[Test]
    public function max_total_size_is_checked_on_the_first_chunk_before_anything_is_staged(): void
    {
        config(['filemanager.max_total_size' => 2]);
        config(['filemanager.extensions' => array_merge(config('filemanager.extensions'), ['archive' => ['zip']])]);

        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, "PK\x03\x04".str_repeat('A', 1024));
        $file = new UploadedFile($tmp, 'big.zip', 'application/zip', null, true);

        $response = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], [], ['files' => [$file]], [
            'HTTP_Content-Range' => 'bytes 0-1027/3145728',
        ]);

        $this->assertArrayHasKey('error', $response->json()['files'][0]);
        $this->assertSame([], glob(storage_path('app/filemanager-chunks/*')) ?: []);
    }

    #[Test]
    public function an_image_over_the_pixel_limit_is_rejected_before_it_is_stored_or_thumbnailed(): void
    {
        // Image bomb (#8): a small file can declare a huge canvas; GD would
        // then fatal with out-of-memory while making the thumbnail.
        config(['filemanager.max_image_pixels' => 100 * 100]);

        $file = UploadedFile::fake()->image('huge.png', 200, 200);

        $response = $this->post(route('filemanager.upload'), ['fldr' => '', 'files' => [$file]]);

        $response->assertOk();
        $this->assertArrayHasKey('error', $response->json()['files'][0]);
        Storage::disk('filemanager')->assertMissing('huge.png');
        Storage::disk('filemanager_thumbs')->assertMissing('huge.png');
    }

    #[Test]
    public function the_upload_route_is_rate_limited(): void
    {
        $route = app('router')->getRoutes()->getByName('filemanager.upload');

        $this->assertContains(ThrottleRequests::class.':120,1', $route->gatherMiddleware());
    }

    /**
     * Encrypted session cookie pinning the request to a fixed session id,
     * the way a browser's cookie does across chunk requests.
     */
    protected function sessionCookie(string $sessionId): array
    {
        return [
            config('session.cookie') => encrypt(CookieValuePrefix::create(config('session.cookie'), app('encrypter')->getKey()).$sessionId, false),
        ];
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/filemanager-chunks'));

        parent::tearDown();
    }
}
