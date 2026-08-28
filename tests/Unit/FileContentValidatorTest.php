<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use OpenSID\LaravelFilemanager\Services\FileContentValidator;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FileContentValidatorTest extends TestCase
{
    protected FileContentValidator $validator;

    protected array $tempFiles = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new FileContentValidator();

        config(['filemanager.extensions.image' => ['jpg', 'jpeg', 'png', 'gif', 'svg', 'ico', 'webp']]);
    }

    #[Test]
    public function accepts_a_real_jpeg(): void
    {
        $path = $this->realJpeg();

        $this->assertTrue($this->validator->isValidUpload($path, 'jpg'));
    }

    #[Test]
    public function rejects_a_php_payload_disguised_with_an_image_extension(): void
    {
        // The exact attack the check exists for: a webshell renamed to
        // "shell.jpg" — same bytes, extension lies about what it is.
        $path = $this->tempFile("<?php system(\$_GET['cmd']); ?>");

        $this->assertFalse($this->validator->isValidUpload($path, 'jpg'));
    }

    #[Test]
    public function rejects_plain_text_wearing_a_png_extension(): void
    {
        $path = $this->tempFile('just some plain text, not an image at all');

        $this->assertFalse($this->validator->isValidUpload($path, 'png'));
    }

    #[Test]
    public function accepts_a_clean_svg(): void
    {
        $path = $this->tempFile('<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="red"/></svg>');

        $this->assertTrue($this->validator->isValidUpload($path, 'svg'));
    }

    #[Test]
    public function rejects_an_svg_containing_an_embedded_script_tag(): void
    {
        $path = $this->tempFile('<svg xmlns="http://www.w3.org/2000/svg"><script>alert(document.cookie)</script></svg>');

        $this->assertFalse($this->validator->isValidUpload($path, 'svg'));
    }

    #[Test]
    public function rejects_an_svg_containing_an_event_handler_attribute(): void
    {
        $path = $this->tempFile('<svg xmlns="http://www.w3.org/2000/svg" onload="alert(1)"></svg>');

        $this->assertFalse($this->validator->isValidUpload($path, 'svg'));
    }

    #[Test]
    public function rejects_a_php_payload_disguised_as_svg(): void
    {
        $path = $this->tempFile("<?php system(\$_GET['cmd']); ?>");

        $this->assertFalse($this->validator->isValidUpload($path, 'svg'));
    }

    #[Test]
    public function rejects_an_svg_containing_meta_or_form_tags(): void
    {
        $path1 = $this->tempFile('<svg xmlns="http://www.w3.org/2000/svg"><meta http-equiv="refresh" content="0;url=http://evil.com"/></svg>');
        $path2 = $this->tempFile('<svg xmlns="http://www.w3.org/2000/svg"><form action="http://evil.com"><input type="submit"/></form></svg>');

        $this->assertFalse($this->validator->isValidUpload($path1, 'svg'));
        $this->assertFalse($this->validator->isValidUpload($path2, 'svg'));
    }

    #[Test]
    public function rejects_html_smuggling_in_raster_images(): void
    {
        $path = $this->tempFile("<!DOCTYPE html><html><body><script>alert(1)</script></body></html>");

        $this->assertFalse($this->validator->isValidUpload($path, 'jpg'));
    }

    #[Test]
    public function non_image_extensions_are_not_content_checked_at_all(): void
    {
        // This validator's job is specifically "claims to be an image but
        // isn't" — a .pdf/.docx/.zip claiming to be one of those isn't its
        // concern (isExtensionAllowed()/the blacklist handle that surface).
        $path = $this->tempFile("<?php system(\$_GET['cmd']); ?>");

        $this->assertTrue($this->validator->isValidUpload($path, 'pdf'));
    }

    protected function realJpeg(): string
    {
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $this->tempFile($bytes);
    }

    protected function tempFile(string $contents): string
    {
        $path = tempnam(sys_get_temp_dir(), 'fcv');
        file_put_contents($path, $contents);
        $this->addTempFileForCleanup($path);

        return $path;
    }

    protected function addTempFileForCleanup(string $path): void
    {
        $this->tempFiles[] = $path;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }
}
