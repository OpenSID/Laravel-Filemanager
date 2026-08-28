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

        // Detect all XSS vectors, embedded script/PHP tags, redirects, and entity injections in SVG
        $pattern = '/(<\s*script|<\s*object|<\s*iframe|<\s*embed|<\s*foreignObject|<\s*meta|<\s*link|<\s*form|<\s*base|<\s*applet|<!entity|<!doctype|javascript\s*:|data\s*:\s*text\/html|\b(on[a-zA-Z]+)\s*=|xmlns\s*:\s*script|<\?php|<\?=)/i';

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

        // Block Image Polyglot & HTML smuggling: legitimate image binary containing hidden PHP, script tags, or HTML document structure
        if (preg_match('/(<\s*script|<\?php|<\?=|<!doctype\s+html|<\s*html\b)/i', $contents)) {
            return false;
        }

        return true;
    }
}

