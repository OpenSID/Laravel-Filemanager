<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use OpenSID\LaravelFilemanager\Support\FilemanagerConfig;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ChunkSizeTest extends TestCase
{
    protected FilemanagerConfig $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->config = new FilemanagerConfig;
    }

    #[Test]
    public function the_chunk_fits_under_a_2m_post_limit(): void
    {
        // Regresi OpenSID/OpenSID#12193: potongan 2 MiB + overhead multipart
        // melebihi post_max_size 2M, sehingga server membalas 413.
        $chunk = $this->config->maxChunkSizeBytes('2M', '2M');

        $this->assertSame(1966080, $chunk);
        $this->assertLessThanOrEqual(2097152 - FilemanagerConfig::CHUNK_OVERHEAD_RESERVE, $chunk);
    }

    #[Test]
    public function the_chunk_stays_at_the_default_when_php_limits_are_generous(): void
    {
        $this->assertSame(FilemanagerConfig::DEFAULT_CHUNK_SIZE, $this->config->maxChunkSizeBytes('64M', '64M'));
        $this->assertSame(FilemanagerConfig::DEFAULT_CHUNK_SIZE, $this->config->maxChunkSizeBytes('8M', '2G'));
    }

    #[Test]
    public function the_chunk_follows_the_smaller_limit(): void
    {
        $expected = 1048576 - FilemanagerConfig::CHUNK_OVERHEAD_RESERVE;

        $this->assertSame($expected, $this->config->maxChunkSizeBytes('8M', '1M'));
        $this->assertSame($expected, $this->config->maxChunkSizeBytes('1M', '8M'));
    }

    #[Test]
    public function the_chunk_never_drops_below_the_minimum(): void
    {
        $this->assertSame(FilemanagerConfig::MIN_CHUNK_SIZE, $this->config->maxChunkSizeBytes('128K', '128K'));
    }

    #[Test]
    public function unlimited_limits_are_ignored(): void
    {
        $this->assertSame(FilemanagerConfig::DEFAULT_CHUNK_SIZE, $this->config->maxChunkSizeBytes('0', '-1'));
        $this->assertSame(1966080, $this->config->maxChunkSizeBytes('0', '2M'));
    }

    #[Test]
    #[DataProvider('iniNotations')]
    public function it_parses_php_ini_size_notation(string $value, int $bytes): void
    {
        $this->assertSame($bytes, $this->config->iniSizeToBytes($value));
    }

    public static function iniNotations(): array
    {
        return [
            ['2M', 2097152],
            ['2m', 2097152],
            ['512K', 524288],
            ['1G', 1073741824],
            ['1024', 1024],
            [' 8M ', 8388608],
            ['-1', 0],
            ['', 0],
            ['abc', 0],
        ];
    }
}
