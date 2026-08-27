<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use OpenSID\LaravelFilemanager\Support\FilemanagerConfig;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FilemanagerConfigTest extends TestCase
{
    protected FilemanagerConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new FilemanagerConfig();

        config([
            'filemanager.extensions' => [
                'image' => ['jpg', 'jpeg', 'png'],
                'document' => ['pdf', 'docx'],
            ],
            'filemanager.blacklisted_extensions' => ['php', 'phtml', 'php3', 'php4', 'php5', 'phar', 'exe', 'sh'],
        ]);
    }

    #[Test]
    #[DataProvider('allowedExtensions')]
    public function allows_configured_extensions(string $extension): void
    {
        $this->assertTrue($this->config->isExtensionAllowed($extension));
    }

    public static function allowedExtensions(): array
    {
        return [
            ['jpg'],
            ['PNG'], // case-insensitive
            ['pdf'],
        ];
    }

    #[Test]
    #[DataProvider('disallowedExtensions')]
    public function rejects_blacklisted_or_unlisted_extensions(string $extension): void
    {
        $this->assertFalse($this->config->isExtensionAllowed($extension));
    }

    public static function disallowedExtensions(): array
    {
        return [
            'blacklisted' => ['php'],
            'blacklisted, mixed case' => ['PHP'],
            'not in any allowed category' => ['zip'],
            'empty extension' => [''],
        ];
    }

    #[Test]
    #[DataProvider('doubleExtensionAttempts')]
    public function rejects_a_blacklisted_extension_embedded_anywhere_in_the_filename(string $filename): void
    {
        // These all end in an *allowed* extension (jpg/png) — the point is
        // isExtensionAllowed() alone (final-extension-only) would pass them;
        // isFilenameSafe() is the defense-in-depth layer that catches the
        // embedded blacklisted segment.
        $this->assertFalse($this->config->isFilenameSafe($filename));
    }

    public static function doubleExtensionAttempts(): array
    {
        return [
            'php then jpg' => ['shell.php.jpg'],
            'phtml then png' => ['webshell.phtml.png'],
            'mixed case embedded extension' => ['shell.PHP.jpg'],
        ];
    }

    #[Test]
    #[DataProvider('safeFilenames')]
    public function allows_ordinary_filenames_with_multiple_dots(string $filename): void
    {
        $this->assertTrue($this->config->isFilenameSafe($filename));
    }

    public static function safeFilenames(): array
    {
        return [
            'version-like name' => ['report.v1.2.final.pdf'],
            'single extension' => ['photo.jpg'],
            'no extension' => ['README'],
        ];
    }
}
