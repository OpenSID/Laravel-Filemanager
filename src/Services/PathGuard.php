<?php

namespace OpenSID\LaravelFilemanager\Services;

use OpenSID\LaravelFilemanager\Exceptions\UnsafePathException;

/**
 * Path-traversal guard. Port of rfm's checkRelativePath()/checkRelativePathPartial(),
 * checked against both the raw value and its rawurldecode()'d form since a path
 * segment can smuggle an encoded ".." past a naive check.
 */
class PathGuard
{
    public static function isSafe(?string $path): bool
    {
        if ($path === null) {
            return true;
        }

        return self::isSafePartial($path) && self::isSafePartial(rawurldecode($path));
    }

    public static function assertSafe(?string $path): void
    {
        if (! self::isSafe($path)) {
            throw UnsafePathException::forPath($path ?? '');
        }
    }

    public static function isInsideBaseFolder(?string $path, ?string $baseFolder): bool
    {
        $baseFolder = trim((string) $baseFolder, '/');
        if ($baseFolder === '') {
            return true;
        }

        $path = trim((string) $path, '/');
        if ($path === '' || $path === $baseFolder) {
            return true;
        }

        return str_starts_with($path, $baseFolder . '/');
    }

    public static function assertInsideBaseFolder(?string $path, ?string $baseFolder): void
    {
        if (! self::isInsideBaseFolder($path, $baseFolder)) {
            throw UnsafePathException::forPath($path ?? '');
        }
    }

    protected static function isSafePartial(string $path): bool
    {
        if ($path === '..') {
            return false;
        }

        foreach (['../', './', '/..', '..\\', '\\..', '.\\'] as $needle) {
            if (str_contains($path, $needle)) {
                return false;
            }
        }

        return true;
    }
}
