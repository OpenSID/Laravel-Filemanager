<?php

namespace OpenSID\LaravelFilemanager\Services;

/**
 * Cross-checks uploaded bytes against the extension they claim to be —
 * FilemanagerConfig::isExtensionAllowed()/isFilenameSafe() only look at the
 * filename, which a webshell renamed to "shell.jpg" satisfies trivially.
 *
 * Implements defenses against:
 * 1. SVG XSS injection (scripts, object, foreignObject, event handlers, javascript: pseudo-protocols)
 * 2. Image Polyglot payloads (valid raster images concealing PHP/Script tags in EXIF/chunks)
 */
class FileContentValidator
{
    public function isValidUpload(string $localPath, string $extension): bool
    {
        $extension = mb_strtolower(ltrim($extension, '.'));

        if (! in_array($extension, config('filemanager.extensions.image', []), true)) {
            return true;
        }

        if ($extension === 'svg') {
            return $this->isSafeSvg($localPath);
        }

        if ($extension === 'ico') {
            return $this->isSafeRasterImage($localPath, false);
        }

        return $this->isSafeRasterImage($localPath, true);
    }

    protected function isSafeSvg(string $localPath): bool
    {
        $contents = @file_get_contents($localPath);

        if ($contents === false || ! str_contains($contents, '<svg')) {
            return false;
        }

        // Detect all XSS vectors and embedded script/PHP tags in SVG (PR #6272 / Issue #11136)
        $pattern = '/(<\s*script|<\s*object|<\s*iframe|<\s*embed|<\s*foreignObject|<!entity|<!doctype|javascript\s*:|\b(on[a-zA-Z]+)\s*=|xmlns\s*:\s*script|<\?php|<\?=)/i';

        return ! preg_match($pattern, $contents);
    }

    protected function isSafeRasterImage(string $localPath, bool $checkImageSize): bool
    {
        if ($checkImageSize && @getimagesize($localPath) === false) {
            return false;
        }

        $contents = @file_get_contents($localPath);
        if ($contents === false) {
            return false;
        }

        // Block Image Polyglot: legitimate image binary containing hidden PHP or script tags (PR #6272)
        if (preg_match('/(<\s*script|<\?php|<\?=)/i', $contents)) {
            return false;
        }

        return true;
    }
}

