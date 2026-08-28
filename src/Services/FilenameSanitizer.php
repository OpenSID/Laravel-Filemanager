<?php

namespace OpenSID\LaravelFilemanager\Services;

/**
 * Port of rfm's fix_filename()/sanitize(). Strips tags/HTML entities, quotes,
 * slashes, optionally transliterates and converts spaces, per config.
 */
class FilenameSanitizer
{
    public function sanitize(string $name, bool $isFolder = false): string
    {
        // Strip null bytes, control characters, and Windows Alternate Data Streams
        $name = str_replace(["\0", "%00", '::$DATA'], '', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

        $name = $this->stripDangerousMarkup($name);

        if (config('filemanager.convert_spaces')) {
            $name = str_replace(' ', (string) config('filemanager.replace_with', '_'), $name);
        }

        if (config('filemanager.transliterate')) {
            $name = $this->transliterate($name);
        }

        $name = str_replace(['"', "'", '/', '\\'], '', $name);
        $name = strip_tags($name);

        if (config('filemanager.lower_case')) {
            $name = mb_strtolower($name);
        }

        // A name that transliterated down to just an extension (e.g. an
        // unsupported-script filename becoming ".jpg") gets a "file" prefix
        // instead of silently producing a hidden dotfile.
        if (! $isFolder && str_starts_with($name, '.')) {
            $name = 'file' . $name;
        }

        // Trim whitespace, tabs, and trailing dots (Windows filesystem automatically strips trailing dots and spaces)
        return trim($name, " .\t\n\r\0\x0B");
    }

    protected function stripDangerousMarkup(string $str): string
    {
        return strip_tags(htmlspecialchars($str));
    }

    protected function transliterate(string $str): string
    {
        if (! mb_detect_encoding($str, 'UTF-8', true)) {
            $str = mb_convert_encoding($str, 'UTF-8');
        }

        if (function_exists('transliterator_transliterate')) {
            $str = transliterator_transliterate('Any-Latin; Latin-ASCII', $str) ?: $str;
        } elseif (function_exists('iconv')) {
            $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str;
        }

        return preg_replace('/[^a-zA-Z0-9.\[\]_| -]/', '', $str) ?? $str;
    }
}
