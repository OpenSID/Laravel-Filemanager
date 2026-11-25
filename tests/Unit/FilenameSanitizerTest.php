<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use OpenSID\LaravelFilemanager\Services\FilenameSanitizer;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
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
    public function strips_markup_without_storing_html_entities(): void
    {
        $this->assertSame('alert(1).jpg', $this->sanitizer->sanitize('<script>alert(1)</script>.jpg'));
    }

    #[Test]
    #[DataProvider('htmlSpecialNames')]
    public function drops_html_special_characters_instead_of_encoding_them(string $input, string $expected): void
    {
        $result = $this->sanitizer->sanitize($input);

        $this->assertSame($expected, $result);
        $this->assertDoesNotMatchRegularExpression('/[&<>"\';#]/', $result);
    }

    public static function htmlSpecialNames(): array
    {
        return [
            'ampersand' => ['Laporan & Data.pdf', 'Laporan__Data.pdf'],
            'quotes and ampersand' => ["Surat Keterangan 'Usaha' & Izin.pdf", 'Surat_Keterangan_Usaha__Izin.pdf'],
            'unclosed less-than keeps the extension' => ['a<b.jpg', 'ab.jpg'],
            'greater-than' => ['a>b.jpg', 'ab.jpg'],
            'entity-looking text' => ['a&amp;b#1;.jpg', 'aampb1.jpg'],
        ];
    }

    #[Test]
    #[DataProvider('longMultibyteNames')]
    public function clamps_a_long_multibyte_name_without_breaking_utf8(string $input): void
    {
        $result = $this->sanitizer->sanitize($input);

        $this->assertLessThanOrEqual(255, strlen($result));
        $this->assertStringEndsWith('.jpg', $result);
        $this->assertTrue(mb_check_encoding($result, 'UTF-8'));
        $this->assertNotFalse(json_encode($result));
    }

    public static function longMultibyteNames(): array
    {
        return [
            '2-byte é' => [str_repeat('é', 200).'.jpg'],
            '3-byte 日' => [str_repeat('日', 100).'.jpg'],
            '4-byte emoji' => [str_repeat('😀', 80).'.jpg'],
        ];
    }

    #[Test]
    public function strips_invisible_unicode_format_characters(): void
    {
        // U+202E right-to-left override would display this as "photoexe.jpg"
        $this->assertSame('photogpj.exe', $this->sanitizer->sanitize("photo\u{202E}gpj.exe"));
        $this->assertSame('photo.jpg', $this->sanitizer->sanitize("pho\u{200B}to\u{FEFF}.jpg"));
    }

    #[Test]
    public function keeps_zero_width_joiners_needed_by_some_scripts(): void
    {
        $name = "\u{0645}\u{06CC}\u{200C}\u{062E}\u{0648}\u{0627}\u{0647}\u{0645}.jpg";

        $this->assertSame($name, $this->sanitizer->sanitize($name));
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
