<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Services\FilesystemManager;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilesystemManagerTest extends TestCase
{
    public static function sizes(): array
    {
        return [
            'zero' => [0, '0 B'],
            'half a KB stays in bytes' => [512, '512 B'],
            'one byte under 1 KB' => [1023, '1,023 B'],
            'exactly 1 KB' => [1024, '1 KB'],
            'fractional KB' => [1536, '1.5 KB'],
            'one byte under 1 MB' => [1048575, '1,024 KB'],
            'exactly 1 MB' => [1048576, '1 MB'],
            'MB with decimals' => [5842880, '5.57 MB'],
            'exactly 1 GB' => [1073741824, '1 GB'],
        ];
    }

    #[Test]
    #[DataProvider('sizes')]
    public function human_file_size_only_steps_up_a_unit_at_a_full_1024(int $bytes, string $expected): void
    {
        $this->assertSame($expected, (new FilesystemManager)->humanFileSize($bytes));
    }

    #[Test]
    public function file_exists_is_false_for_a_directory_unlike_exists(): void
    {
        Storage::fake('filemanager');
        Storage::disk('filemanager')->makeDirectory('album');
        Storage::disk('filemanager')->put('album/photo.jpg', 'x');

        $files = new FilesystemManager;

        $this->assertTrue($files->exists('album'));
        $this->assertFalse($files->fileExists('album'));
        $this->assertTrue($files->fileExists('album/photo.jpg'));
    }

    #[Test]
    public function copy_duplicates_a_folder_recursively_with_its_thumbnails(): void
    {
        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $disk = Storage::disk('filemanager');
        $disk->put('album/a.jpg', 'a');
        $disk->put('album/sub/b.txt', 'b');
        $disk->put('album/.htaccess', 'weak');
        Storage::disk('filemanager_thumbs')->put('album/a.jpg', 'thumb-a');

        $this->assertTrue((new FilesystemManager)->copy('album', 'album-copy'));

        $this->assertSame('a', $disk->get('album-copy/a.jpg'));
        $this->assertSame('b', $disk->get('album-copy/sub/b.txt'));
        $this->assertSame('thumb-a', Storage::disk('filemanager_thumbs')->get('album-copy/a.jpg'));

        // every copied directory carries the package's own hardening, not
        // whatever .htaccess the source happened to have
        $this->assertStringContainsString('php_flag engine off', $disk->get('album-copy/.htaccess'));
        $disk->assertExists('album-copy/sub/.htaccess');
        $disk->assertExists('album-copy/sub/index.html');

        // source untouched
        $this->assertSame('a', $disk->get('album/a.jpg'));
    }

    #[Test]
    public function copy_refuses_to_overwrite_an_existing_target_folder(): void
    {
        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        Storage::disk('filemanager')->put('album/a.jpg', 'a');
        Storage::disk('filemanager')->put('other/keep.txt', 'keep');

        $this->assertFalse((new FilesystemManager)->copy('album', 'other'));
        Storage::disk('filemanager')->assertMissing('other/a.jpg');
    }
}
