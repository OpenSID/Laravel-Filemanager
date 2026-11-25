<?php

namespace OpenSID\LaravelFilemanager\Services;

/**
 * Port of rfm's fix_filename()/sanitize(). Strips tags, HTML-special
 * characters, quotes, slashes and invisible Unicode format characters,
 * optionally transliterates and converts spaces, per config. The result is
 * stored raw — escaping is the job of whoever renders it, not of the name.
 */
class FilenameSanitizer
{
    public function sanitize(string $name, bool $isFolder = false): string
    {
        // Strip null bytes, control characters, and Windows Alternate Data Streams
        $name = str_replace(["\0", '%00', '::$DATA'], '', $name);
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?? $name;

        // Invisible Unicode format characters (\p{Cf}) let a name display as
        // something it is not: U+202E (right-to-left override) shows
        // "photo<RLO>gpj.exe" as "photoexe.jpg", U+200B hides inside an
        // otherwise identical-looking name. ZWNJ/ZWJ (U+200C/U+200D) are
        // kept — Persian/Indic scripts and emoji sequences need them.
        $name = preg_replace('/(?![\x{200C}\x{200D}])\p{Cf}/u', '', $name) ?? $name;

        // Surrounding whitespace goes first, before space->replace_with
        // conversion can turn a trailing space into a "_" that trim can't
        // reach later.
        $name = trim($name);

        $name = $this->stripDangerousMarkup($name);

        if (config('filemanager.convert_spaces')) {
            $name = str_replace(' ', (string) config('filemanager.replace_with', '_'), $name);
        }

        if (config('filemanager.transliterate')) {
            $name = $this->transliterate($name);
        }

        $name = str_replace(['/', '\\'], '', $name);

        if (config('filemanager.lower_case')) {
            $name = mb_strtolower($name);
        }

        // A name that transliterated down to just an extension (e.g. an
        // unsupported-script filename becoming ".jpg") gets a "file" prefix
        // instead of silently producing a hidden dotfile.
        if (! $isFolder && str_starts_with($name, '.')) {
            $name = 'file'.$name;
        }

        // Strip TRAILING dots/whitespace only (Windows silently drops them,
        // which turns "shell.php." into an executable "shell.php"). A
        // leading dot is deliberately kept here — for files it was already
        // neutralised by the "file" prefix above; for folders ".git"-style
        // names stay intact.
        $name = rtrim($name, " .\t\n\r\0\x0B");

        return $this->clampLength($name);
    }

    /**
     * Keep the whole name within the common 255-byte filesystem limit
     * (ext4/APFS/NTFS component cap) so an over-long name surfaces as a
     * clean rejection upstream, not a filesystem write exception. The
     * extension is preserved.
     */
    protected function clampLength(string $name, int $max = 255): string
    {
        if (strlen($name) <= $max) {
            return $name;
        }

        $extension = pathinfo($name, PATHINFO_EXTENSION);
        $suffix = $extension !== '' ? '.'.$extension : '';
        $stem = substr($name, 0, strlen($name) - strlen($suffix));

        // mb_strcut() cuts by bytes like substr() but never inside a
        // multibyte character, so the result stays valid UTF-8 (a broken
        // sequence makes json_encode() of the upload response fail).
        $stem = mb_strcut($stem, 0, max(1, $max - strlen($suffix)), 'UTF-8');

        return rtrim($stem, " .\t\n\r\0\x0B").$suffix;
    }

    /**
     * Remove complete tags, then drop the characters that are special in
     * HTML/URLs (& < > " ' ; #). They are removed — not HTML-encoded —
     * because an entity would be stored as part of the filename
     * ("a & b" => "a &amp; b") and then double-encoded on display.
     *
     * Tags are matched as "<...>" instead of with strip_tags(), which treats
     * an unclosed "<" as the start of a tag and eats the rest of the name
     * ("a<b.jpg" => "a", losing the extension).
     */
    protected function stripDangerousMarkup(string $str): string
    {
        $str = preg_replace('/<[^<>]*>/', '', $str) ?? $str;

        return str_replace(['&', '<', '>', '"', "'", ';', '#'], '', $str);
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
