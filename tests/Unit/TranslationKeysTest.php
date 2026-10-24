<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use Illuminate\Support\Facades\Lang;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * A missing key doesn't error — trans() just returns the raw key, so the UI
 * silently shows "filemanager::filemanager.Upload_error" to the user. These
 * tests catch that at CI time instead.
 */
class TranslationKeysTest extends TestCase
{
    private const LOCALES = ['id', 'en_EN'];

    #[Test]
    public function every_translation_key_used_by_the_package_exists_in_every_locale(): void
    {
        $keys = $this->usedKeys();

        $this->assertNotEmpty($keys);

        $missing = [];
        foreach (self::LOCALES as $locale) {
            foreach ($keys as $key) {
                if (! Lang::has('filemanager::filemanager.'.$key, $locale, false)) {
                    $missing[] = "{$locale}: {$key}";
                }
            }
        }

        $this->assertSame([], $missing, 'Translation keys used in src/ or resources/views are missing.');
    }

    #[Test]
    public function every_locale_file_has_the_same_set_of_keys(): void
    {
        $sets = [];
        foreach (self::LOCALES as $locale) {
            $sets[$locale] = $this->flattenKeys(require $this->langPath($locale));
        }

        [$first, $second] = self::LOCALES;

        $this->assertSame([], array_values(array_diff($sets[$first], $sets[$second])), "Keys in {$first} but not in {$second}.");
        $this->assertSame([], array_values(array_diff($sets[$second], $sets[$first])), "Keys in {$second} but not in {$first}.");
    }

    /**
     * @return list<string>
     */
    private function usedKeys(): array
    {
        $root = dirname(__DIR__, 2);
        $keys = [];

        foreach (['src', 'resources/views'] as $dir) {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir));

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                preg_match_all('/filemanager::filemanager\.([^\'"]+)[\'"]/', (string) file_get_contents($file->getPathname()), $matches);

                foreach ($matches[1] as $key) {
                    // A dynamic suffix ('...Upload_error_messages.'.$code)
                    // leaves a trailing dot — check the parent group exists.
                    $keys[] = rtrim($key, '.');
                }
            }
        }

        return array_values(array_unique($keys));
    }

    /**
     * @return list<string>
     */
    private function flattenKeys(array $lines, string $prefix = ''): array
    {
        $keys = [];

        foreach ($lines as $key => $value) {
            $keys[] = $prefix.$key;

            if (is_array($value)) {
                array_push($keys, ...$this->flattenKeys($value, $prefix.$key.'.'));
            }
        }

        return $keys;
    }

    private function langPath(string $locale): string
    {
        return dirname(__DIR__, 2).'/resources/lang/'.$locale.'/filemanager.php';
    }
}
