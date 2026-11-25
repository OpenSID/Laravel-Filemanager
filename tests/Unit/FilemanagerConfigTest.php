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

        $this->config = new FilemanagerConfig;

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

    #[Test]
    #[DataProvider('baseFolderCases')]
    public function resolves_base_folder_against_the_disk_root_on_whole_segments(string $diskRoot, string $baseFolder, string $expected): void
    {
        config([
            'filesystems.disks.filemanager.root' => base_path($diskRoot),
            'filemanager.base_folder' => $baseFolder,
        ]);

        $this->assertSame($expected, $this->config->baseFolder());
    }

    public static function baseFolderCases(): array
    {
        return [
            'sibling folder sharing the disk root prefix' => ['media', 'media-extra/x', 'media-extra/x'],
            'sibling folder with a digit suffix' => ['desa', 'desa2', 'desa2'],
            'base_path-relative value under the disk root' => ['desa/upload', 'desa/upload/media', 'media'],
            'base_path-relative value equal to the disk root' => ['desa/upload', 'desa/upload', ''],
            'disk root already ends with base_folder' => ['desa/upload/media', 'media', ''],
            'plain disk-relative value' => ['desa/upload', 'media', 'media'],
        ];
    }

    #[Test]
    #[DataProvider('pathPrefixCases')]
    public function strips_a_path_prefix_only_on_segment_boundaries(string $path, string $prefix, ?string $expected): void
    {
        $this->assertSame($expected, FilemanagerConfig::stripPathPrefix($path, $prefix));
    }

    public static function pathPrefixCases(): array
    {
        return [
            'exact match' => ['media', 'media', ''],
            'child path' => ['media/a/b', 'media', 'a/b'],
            'shared prefix, different segment' => ['media-extra/x', 'media', null],
            'empty prefix' => ['a/b', '', 'a/b'],
            'unrelated' => ['foo', 'media', null],
        ];
    }

    public static function safeFilenames(): array
    {
        return [
            'version-like name' => ['report.v1.2.final.pdf'],
            'single extension' => ['photo.jpg'],
            'no extension' => ['README'],
        ];
    }

    #[Test]
    public function file_accessibility_uses_the_whitelist_and_hidden_rules(): void
    {
        config([
            'filemanager.hidden_files' => ['.htaccess', 'index.html'],
            'filemanager.hidden_extensions' => ['php'],
            'filemanager.editable_text_extensions' => ['txt'],
            'filemanager.text_editing_enabled' => false,
        ]);

        $this->assertTrue($this->config->isFileAccessible('foto.jpg'));
        $this->assertTrue($this->config->isFileAccessible('Laporan.PDF'));

        foreach (['.htaccess', 'index.html', '.env', 'dump.sql', 'app.log', 'shell.php', 'shell.php.jpg', 'README', 'catatan.txt'] as $name) {
            $this->assertFalse($this->config->isFileAccessible($name), $name);
        }

        config(['filemanager.text_editing_enabled' => true]);
        $this->assertTrue($this->config->isFileAccessible('catatan.txt'));
    }

    #[Test]
    public function clipboard_source_rules_cover_files_hidden_folders_and_the_base_folder(): void
    {
        config([
            'filemanager.hidden_files' => ['.htaccess'],
            'filemanager.hidden_folders' => ['.git'],
            'filemanager.base_folder' => 'desa/upload',
        ]);

        $this->assertTrue($this->config->isClipboardSourceAllowed('desa/upload/album', true));
        $this->assertTrue($this->config->isClipboardSourceAllowed('desa/upload/album/foto.jpg', false));
        $this->assertFalse($this->config->isClipboardSourceAllowed('desa/upload/album/.htaccess', false));
        $this->assertFalse($this->config->isClipboardSourceAllowed('desa/upload/.git', true));
        $this->assertFalse($this->config->isClipboardSourceAllowed('desa/upload', true));
        $this->assertFalse($this->config->isClipboardSourceAllowed('', true));
    }

    #[Test]
    public function detects_a_disk_rooted_at_the_application_root_without_base_folder(): void
    {
        config([
            'filesystems.disks.filemanager.root' => base_path(),
            'filemanager.base_folder' => '',
        ]);
        $this->assertTrue($this->config->exposesApplicationRoot());

        config(['filemanager.base_folder' => 'desa/upload']);
        $this->assertFalse($this->config->exposesApplicationRoot());

        config([
            'filesystems.disks.filemanager.root' => storage_path('framework/testing/disks/filemanager'),
            'filemanager.base_folder' => '',
        ]);
        $this->assertFalse($this->config->exposesApplicationRoot());
    }
}
