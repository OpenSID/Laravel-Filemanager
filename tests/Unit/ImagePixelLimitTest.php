<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Services\FileContentValidator;
use OpenSID\LaravelFilemanager\Services\ThumbnailGenerator;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Image/decompression bomb guard (#8).
 */
class ImagePixelLimitTest extends TestCase
{
    protected string $bomb;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filemanager.extensions.image' => ['jpg', 'jpeg', 'png']]);

        // A tiny PNG whose header declares 40000x40000 pixels (1.6 GP,
        // ~6 GB once GD decodes it). The guard has to refuse it from the
        // header alone — a decoder that got this far would fatal.
        $this->bomb = (string) tempnam(sys_get_temp_dir(), 'bomb');
        file_put_contents($this->bomb, $this->pngHeader(40000, 40000));
    }

    protected function tearDown(): void
    {
        @unlink($this->bomb);

        parent::tearDown();
    }

    #[Test]
    public function the_header_alone_is_enough_to_flag_a_bomb(): void
    {
        $this->assertTrue((new FileContentValidator)->exceedsPixelLimit($this->bomb));
        $this->assertFalse((new FileContentValidator)->isValidUpload($this->bomb, 'png'));
    }

    #[Test]
    public function a_normal_image_is_under_the_default_limit(): void
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'ok');
        imagepng(imagecreatetruecolor(64, 48), $path);

        $this->assertFalse((new FileContentValidator)->exceedsPixelLimit($path));
        $this->assertTrue((new FileContentValidator)->isValidUpload($path, 'png'));

        @unlink($path);
    }

    #[Test]
    public function zero_disables_the_check(): void
    {
        config(['filemanager.max_image_pixels' => 0]);

        $this->assertFalse((new FileContentValidator)->exceedsPixelLimit($this->bomb));
    }

    #[Test]
    public function the_thumbnail_generator_refuses_a_bomb_without_decoding_it(): void
    {
        Storage::fake('src');
        Storage::fake('thumbs');
        Storage::disk('src')->put('bom.png', (string) file_get_contents($this->bomb));

        $this->assertFalse((new ThumbnailGenerator)->make('src', 'bom.png', 'thumbs', 'bom.png'));
        Storage::disk('thumbs')->assertMissing('bom.png');
    }

    protected function pngHeader(int $width, int $height): string
    {
        $chunk = fn (string $type, string $data): string => pack('N', strlen($data)).$type.$data.pack('N', crc32($type.$data));

        return "\x89PNG\r\n\x1a\n"
            .$chunk('IHDR', pack('NNCCCCC', $width, $height, 8, 2, 0, 0, 0))
            .$chunk('IDAT', (string) gzcompress(''))
            .$chunk('IEND', '');
    }
}
