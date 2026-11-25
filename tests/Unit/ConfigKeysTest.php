<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Keeps config/filemanager.php and the code that reads it in sync (#15):
 * a key read by the package but missing from the shipped config silently
 * falls back to a hidden default, and a shipped key nobody reads is a
 * setting that does nothing when changed.
 */
class ConfigKeysTest extends TestCase
{
    #[Test]
    public function every_config_key_read_by_the_package_exists_in_the_shipped_config(): void
    {
        $missing = array_diff($this->keysReadByCode(), array_keys($this->shippedConfig()));

        $this->assertSame([], array_values($missing), 'Read via config() but absent from config/filemanager.php: '.implode(', ', $missing));
    }

    #[Test]
    public function every_shipped_config_key_is_read_by_the_package(): void
    {
        $unused = array_diff(array_keys($this->shippedConfig()), $this->keysReadByCode());

        $this->assertSame([], array_values($unused), 'In config/filemanager.php but never read: '.implode(', ', $unused));
    }

    /**
     * @return array<string, mixed>
     */
    private function shippedConfig(): array
    {
        return require __DIR__.'/../../config/filemanager.php';
    }

    /**
     * Top-level keys of every config('filemanager.<key>...') call in src/,
     * routes/ and resources/views/.
     *
     * @return list<string>
     */
    private function keysReadByCode(): array
    {
        $root = dirname(__DIR__, 2);
        $keys = [];

        foreach (['src', 'routes', 'resources/views'] as $dir) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/'.$dir, RecursiveDirectoryIterator::SKIP_DOTS));

            foreach ($files as $file) {
                if ($file->getExtension() !== 'php') {
                    continue;
                }

                preg_match_all('/config\(\s*[\'"]filemanager\.([a-z_]+)/', (string) file_get_contents($file->getPathname()), $matches);
                array_push($keys, ...$matches[1]);
            }
        }

        $keys = array_values(array_unique($keys));
        sort($keys);

        return $keys;
    }
}
