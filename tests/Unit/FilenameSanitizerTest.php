<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use OpenSID\LaravelFilemanager\Services\FilenameSanitizer;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class FilenameSanitizerTest extends TestCase
{
    protected FilenameSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sanitizer = new FilenameSanitizer;

        config([
            'filemanager.convert_spaces' => true,
            'filemanager.replace_with' => '_',
            'filemanager.transliterate' => false,
            'filemanager.lower_case' => false,
        ]);
    }

    #[Test]
    public function clamps_an_over_long_name_to_255_bytes_keeping_the_extension(): void
    {
        $long = str_repeat('a', 400).'.jpg';

        $result = $this->sanitizer->sanitize($long);

        $this->assertLessThanOrEqual(255, strlen($result));
        $this->assertStringEndsWith('.jpg', $result);
    }

    #[Test]
    public function converts_spaces_to_the_configured_replacement(): void
    {
        $this->assertSame('my_photo.jpg', $this->sanitizer->sanitize('my photo.jpg'));
    }

    #[Test]
    public function strips_path_separators_so_a_filename_cannot_smuggle_a_directory_change(): void
    {
        $result = $this->sanitizer->sanitize('../../etc/passwd');

        $this->assertStringNotContainsString('/', $result);
        $this->assertStringNotContainsString('\\', $result);
    }

    #[Test]
    public function html_escapes_markup_instead_of_letting_it_through_raw(): void
    {
        $result = $this->sanitizer->sanitize('<script>alert(1)</script>.jpg');

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    #[Test]
    public function strips_quote_characters(): void
    {
        $result = $this->sanitizer->sanitize('a"b\'c.jpg');

        $this->assertStringNotContainsString('"', $result);
        $this->assertStringNotContainsString("'", $result);
    }

    #[Test]
    public function prefixes_a_leading_dot_so_it_cannot_become_a_hidden_file(): void
    {
        $this->assertSame('file.htaccess', $this->sanitizer->sanitize('.htaccess'));
    }

    #[Test]
    public function does_not_prefix_a_leading_dot_for_folder_names(): void
    {
        $this->assertSame('.htaccess', $this->sanitizer->sanitize('.htaccess', isFolder: true));
    }

    #[Test]
    public function lowercases_when_configured(): void
    {
        config(['filemanager.lower_case' => true]);

        $this->assertSame('my_photo.jpg', $this->sanitizer->sanitize('My Photo.JPG'));
    }

    #[Test]
    public function strips_null_bytes_and_ads(): void
    {
        $this->assertSame('photo.jpg', $this->sanitizer->sanitize("photo\0.jpg"));
        $this->assertSame('photo.jpg', $this->sanitizer->sanitize('photo%00.jpg'));
        $this->assertSame('photo.jpg', $this->sanitizer->sanitize('photo.jpg::$DATA'));
    }

    #[Test]
    public function trims_trailing_dots_and_spaces_preventing_windows_truncation(): void
    {
        $this->assertSame('photo.php', $this->sanitizer->sanitize('photo.php.'));
        $this->assertSame('photo.php', $this->sanitizer->sanitize('photo.php...'));
        $this->assertSame('photo.php', $this->sanitizer->sanitize('photo.php '));
    }
}
