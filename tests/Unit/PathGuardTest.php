<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use OpenSID\LaravelFilemanager\Exceptions\UnsafePathException;
use OpenSID\LaravelFilemanager\Services\PathGuard;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PathGuardTest extends TestCase
{
    #[Test]
    #[DataProvider('unsafePaths')]
    public function rejects_path_traversal_attempts(string $path): void
    {
        $this->assertFalse(PathGuard::isSafe($path));
        $this->expectException(UnsafePathException::class);
        PathGuard::assertSafe($path);
    }

    public static function unsafePaths(): array
    {
        return [
            'parent dir' => ['../etc/passwd'],
            'parent dir mid-path' => ['foo/../../etc/passwd'],
            'current dir marker' => ['./foo'],
            'windows-style traversal' => ['foo\\..\\bar'],
            'bare double-dot' => ['..'],
            'bare dot' => ['.'],
            'trailing parent marker' => ['foo/..'],
            'url-encoded traversal' => ['foo%2F..%2F..%2Fetc%2Fpasswd'],
            'double url-encoded traversal' => ['foo%252e%252e%252fetc'],
            'null byte injection' => ["foo\0bar"],
            'url encoded null byte' => ['foo%00bar'],
            'windows alternate data stream' => ['image.jpg::$DATA'],
        ];
    }

    #[Test]
    #[DataProvider('safePaths')]
    public function allows_ordinary_relative_paths(string $path): void
    {
        $this->assertTrue(PathGuard::isSafe($path));
        PathGuard::assertSafe($path); // should not throw
        $this->addToAssertionCount(1);
    }

    public static function safePaths(): array
    {
        return [
            'empty (root)' => [''],
            'single segment' => ['foto.jpg'],
            'nested folder' => ['galeri/2026/foto.jpg'],
            'name containing dots but not traversal' => ['v1.2.3-release-notes.txt'],
        ];
    }

    #[Test]
    public function null_is_treated_as_safe(): void
    {
        $this->assertTrue(PathGuard::isSafe(null));
        PathGuard::assertSafe(null);
        $this->addToAssertionCount(1);
    }
}
