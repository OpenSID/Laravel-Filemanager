<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Http\UploadedFile;
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
        $this->withoutMiddleware(\Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class);

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
        $bytes = str_repeat('A', 3 * 1024 * 1024); // 3MB
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

        $this->call('POST', route('filemanager.upload'), ['fldr' => ''], [], ['files' => [$file1]], [
            'HTTP_Content-Range' => 'bytes 0-2097151/3145728',
        ])->assertOk();

        Storage::disk('filemanager')->assertMissing('bigfile.zip');

        $final = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], [], ['files' => [$file2]], [
            'HTTP_Content-Range' => 'bytes 2097152-3145727/3145728',
        ]);

        $final->assertOk();
        Storage::disk('filemanager')->assertExists('bigfile.zip');
        $this->assertSame($bytes, Storage::disk('filemanager')->get('bigfile.zip'));
    }
}
