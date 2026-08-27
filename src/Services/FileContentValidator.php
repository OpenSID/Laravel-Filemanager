<?php

namespace OpenSID\LaravelFilemanager\Services;

/**
 * Cross-checks uploaded bytes against the extension they claim to be —
 * FilemanagerConfig::isExtensionAllowed()/isFilenameSafe() only look at the
 * filename, which a webshell renamed to "shell.jpg" satisfies trivially.
 * This is the classic "image upload is secretly PHP" defense: a real
 * raster image has to actually decode as one.
 */
class FileContentValidator
{
    protected const DANGEROUS_SVG_PATTERNS = [
        '<script',
        'javascript:',
        '<!entity',
        '<!doctype',
        'onload=',
        'onerror=',
        'onclick=',
        'onmouseover=',
    ];

    /**
     * Only meaningful for extensions in the "image" category — everything
     * else passes through untouched (this validator's job is specifically
     * the "image that secretly isn't one" case, not general content
     * scanning of every upload).
     */
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
            // getimagesize() support for .ico is unreliable across PHP/GD
            // builds; a stored file that never gets executed by the
            // webserver (see rfm/.htaccess-style deny rules on the upload
            // dir) is low enough risk to accept on extension checks alone.
            return true;
        }

        // The real check: does this actually decode as an image? A PHP
        // payload (or anything else) wearing a raster-image extension
        // fails this — getimagesize() parses real image headers, it
        // doesn't just sniff a few magic bytes.
        return @getimagesize($localPath) !== false;
    }

    protected function isSafeSvg(string $localPath): bool
    {
        $contents = @file_get_contents($localPath);

        if ($contents === false || ! str_contains($contents, '<svg')) {
            return false;
        }

        $lower = mb_strtolower($contents);

        foreach (self::DANGEROUS_SVG_PATTERNS as $pattern) {
            if (str_contains($lower, $pattern)) {
                return false;
            }
        }

        return true;
    }
}
