<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Services\ThumbnailGenerator;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ThumbnailGeneratorTest extends TestCase
{
    protected ThumbnailGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->generator = new ThumbnailGenerator();

        config(['filemanager.extensions.image' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'webp']]);
    }

    #[Test]
    public function raster_formats_are_thumbnail_eligible(): void
    {
        $this->assertTrue($this->generator->isImage('photo.jpg'));
        $this->assertTrue($this->generator->isImage('photo.PNG'));
        $this->assertTrue($this->generator->isImage('photo.webp'));
    }

    #[Test]
    public function vector_and_icon_formats_are_excluded_since_gd_cannot_rasterize_them(): void
    {
        // Without Imagick (not always available), spatie/image's GD driver
        // throws on these — make() must never be attempted for them.
        $this->assertFalse($this->generator->isImage('logo.svg'));
        $this->assertFalse($this->generator->isImage('favicon.ico'));
    }

    #[Test]
    public function non_image_extensions_are_not_thumbnail_eligible(): void
    {
        $this->assertFalse($this->generator->isImage('document.pdf'));
        $this->assertFalse($this->generator->isImage('archive.zip'));
    }

    #[Test]
    public function make_returns_false_without_attempting_a_load_for_a_non_eligible_extension(): void
    {
        // isImage() is false for .svg, so make() short-circuits before ever
        // touching Storage/spatie-image — no disk needs to be faked here.
        $this->assertFalse($this->generator->make('filemanager', 'logo.svg', 'filemanager_thumbs', 'logo.svg'));
    }

    #[Test]
    public function make_catches_a_load_failure_instead_of_letting_it_propagate(): void
    {
        // .jpg IS thumbnail-eligible, so this exercises the try/catch
        // around Image::load() itself — the stored bytes are not a real
        // image, which spatie/image's GD driver will refuse to load.
        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');
        Storage::disk('filemanager')->put('corrupt.jpg', 'this is not actually a jpeg');

        $result = $this->generator->make('filemanager', 'corrupt.jpg', 'filemanager_thumbs', 'corrupt.jpg');

        $this->assertFalse($result);
        Storage::disk('filemanager_thumbs')->assertMissing('corrupt.jpg');
    }

    #[Test]
    public function make_produces_a_correctly_sized_thumbnail_for_a_real_image(): void
    {
        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $image = imagecreatetruecolor(400, 300);
        ob_start();
        imagejpeg($image);
        $jpegBytes = ob_get_clean();
        imagedestroy($image);

        Storage::disk('filemanager')->put('photo.jpg', $jpegBytes);

        $result = $this->generator->make('filemanager', 'photo.jpg', 'filemanager_thumbs', 'photo.jpg', 122, 91);

        $this->assertTrue($result);
        Storage::disk('filemanager_thumbs')->assertExists('photo.jpg');

        $thumb = imagecreatefromstring(Storage::disk('filemanager_thumbs')->get('photo.jpg'));
        $this->assertSame(122, imagesx($thumb));
        $this->assertSame(91, imagesy($thumb));
    }
}
