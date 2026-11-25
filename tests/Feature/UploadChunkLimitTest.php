<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Batas max_upload_size pada jalur chunk: chunk pertama boleh kecil, tetapi
 * "total" di Content-Range yang melebihi batas harus langsung ditolak tanpa
 * menulis berkas staging.
 */
class UploadChunkLimitTest extends TestCase
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
            'filemanager.max_upload_size' => 2,
        ]);
    }

    #[Test]
    public function a_chunk_whose_declared_total_exceeds_max_upload_size_is_rejected_up_front(): void
    {
        app()->setLocale('id');

        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, str_repeat('A', 1024));
        $chunk = new UploadedFile($tmp, 'besar.jpg', 'image/jpeg', null, true);

        $total = 3 * 1024 * 1024; // 3 MB > batas 2 MB

        $response = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], [], ['files' => [$chunk]], [
            'HTTP_Content-Range' => 'bytes 0-1023/'.$total,
        ]);

        $response->assertOk();
        $this->assertSame(
            trans('filemanager::filemanager.max_size_reached', ['size' => 2]),
            $response->json('files.0.error')
        );

        $this->assertFileDoesNotExist(storage_path('app/filemanager-chunks/'.sha1('|besar.jpg')));
        Storage::disk('filemanager')->assertMissing('besar.jpg');
    }

    #[Test]
    public function a_chunk_whose_declared_total_is_within_the_limit_is_accepted_as_partial(): void
    {
        $tmp = tempnam(sys_get_temp_dir(), 'up');
        file_put_contents($tmp, str_repeat('A', 1024));
        $chunk = new UploadedFile($tmp, 'kecil.jpg', 'image/jpeg', null, true);

        $response = $this->call('POST', route('filemanager.upload'), ['fldr' => ''], [], ['files' => [$chunk]], [
            'HTTP_Content-Range' => 'bytes 0-1023/'.(1024 * 1024),
        ]);

        $response->assertOk();
        $this->assertSame(['name' => 'kecil.jpg', 'size' => 1024], $response->json('files.0'));
        Storage::disk('filemanager')->assertMissing('kecil.jpg');

        @unlink(storage_path('app/filemanager-chunks/'.sha1('|kecil.jpg')));
    }
}
