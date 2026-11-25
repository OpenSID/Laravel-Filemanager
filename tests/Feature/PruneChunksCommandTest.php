<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\File;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class PruneChunksCommandTest extends TestCase
{
    protected string $dir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->dir = storage_path('app/filemanager-chunks');
        File::ensureDirectoryExists($this->dir);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->dir);

        parent::tearDown();
    }

    #[Test]
    public function it_removes_only_staging_files_older_than_the_ttl(): void
    {
        file_put_contents($this->dir.'/lama', 'x');
        touch($this->dir.'/lama', time() - 2 * 3600);
        file_put_contents($this->dir.'/baru', 'x');

        $this->artisan('filemanager:prune-chunks')->assertSuccessful();

        $this->assertFileDoesNotExist($this->dir.'/lama');
        $this->assertFileExists($this->dir.'/baru');
    }

    #[Test]
    public function the_age_can_be_overridden_per_run(): void
    {
        file_put_contents($this->dir.'/sepuluh-menit', 'x');
        touch($this->dir.'/sepuluh-menit', time() - 600);

        $this->artisan('filemanager:prune-chunks', ['--minutes' => 5])->assertSuccessful();

        $this->assertFileDoesNotExist($this->dir.'/sepuluh-menit');
    }

    #[Test]
    public function it_succeeds_when_the_staging_directory_does_not_exist(): void
    {
        File::deleteDirectory($this->dir);

        $this->artisan('filemanager:prune-chunks')->assertSuccessful();
    }

    #[Test]
    public function it_is_scheduled_hourly(): void
    {
        $events = collect(app(Schedule::class)->events())
            ->filter(fn ($event) => str_contains((string) $event->command, 'filemanager:prune-chunks'));

        $this->assertCount(1, $events);
        $this->assertSame('0 * * * *', $events->first()->expression);
    }
}
