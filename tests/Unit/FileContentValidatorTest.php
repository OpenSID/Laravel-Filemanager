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

        $this->validator = new FileContentValidator;

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
        $path = $this->tempFile('<!DOCTYPE html><html><body><script>alert(1)</script></body></html>');

        $this->assertFalse($this->validator->isValidUpload($path, 'jpg'));
    }

    #[Test]
    public function rejects_a_non_image_whose_bytes_dont_match_its_claimed_signature(): void
    {
        // A webshell renamed shell.pdf: no %PDF- header -> rejected before
        // the content scan even runs.
        $path = $this->tempFile("<?php system(\$_GET['cmd']); ?>");

        $this->assertFalse($this->validator->isValidUpload($path, 'pdf'));
        $this->assertFalse($this->validator->isValidUpload($path, 'zip'));
        $this->assertFalse($this->validator->isValidUpload($path, 'docx'));
    }

    #[Test]
    public function accepts_a_non_image_with_a_valid_signature(): void
    {
        $pdf = $this->tempFile("%PDF-1.4\n1 0 obj<< >>endobj\ntrailer<< >>\n%%EOF");
        $zip = $this->tempFile("PK\x03\x04".str_repeat("\x00", 26));

        $this->assertTrue($this->validator->isValidUpload($pdf, 'pdf'));
        $this->assertTrue($this->validator->isValidUpload($zip, 'zip'));
    }

    #[Test]
    public function rejects_a_non_image_extension_with_no_verifiable_signature(): void
    {
        // "can't verify" == "don't trust": an allowed extension absent from
        // KNOWN_SIGNATURES never passes.
        $path = $this->tempFile('anything at all');

        $this->assertFalse($this->validator->isValidUpload($path, 'psd'));
    }

    #[Test]
    public function rejects_a_bare_php_short_tag_appended_to_a_valid_image(): void
    {
        // Valid JPEG (passes getimagesize) with a bare "<?" (no "php")
        // trailer — dangerous when short_open_tag=On, missed by /<\?php/.
        $image = imagecreatetruecolor(10, 10);
        ob_start();
        imagejpeg($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        $path = $this->tempFile($bytes."\n<? echo `id`; ?>");

        $this->assertFalse($this->validator->isValidUpload($path, 'jpg'));
    }

    #[Test]
    public function rejects_an_svg_with_a_style_import_or_animated_href(): void
    {
        $style = $this->tempFile('<svg xmlns="http://www.w3.org/2000/svg"><style>@import url(//evil.com/x.css)</style></svg>');
        $animate = $this->tempFile('<svg xmlns="http://www.w3.org/2000/svg"><set attributeName="href" to="javascript:alert(1)"/></svg>');

        $this->assertFalse($this->validator->isValidUpload($style, 'svg'));
        $this->assertFalse($this->validator->isValidUpload($animate, 'svg'));
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
