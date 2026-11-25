<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use League\Flysystem\UnableToDeleteFile;
use League\Flysystem\UnableToWriteFile;
use Mockery;
use OpenSID\LaravelFilemanager\Events\FilemanagerActionPerformed;
use OpenSID\LaravelFilemanager\Services\FilesystemManager;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regressions for OpenSID/Laravel-Filemanager#11: array query params,
 * audit trail, storage exceptions, Host-header-derived URLs and the
 * opt-out `Options` line in the generated .htaccess.
 */
class BackendResilienceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
        $this->withoutMiddleware(VerifyCsrfToken::class);
    }

    #[Test]
    public function the_dialog_does_not_500_when_picker_params_arrive_as_arrays(): void
    {
        $query = http_build_query([
            'multiple' => ['1'],
            'popup' => ['1'],
            'crossdomain' => ['1'],
            'relative_url' => ['1'],
            'field_id' => ['x'],
            'editor' => ['tinymce'],
            'callback' => ['cb'],
            'type' => ['1'],
        ]);

        $response = $this->get(route('filemanager.dialog').'?'.$query);

        $response->assertOk();
        $response->assertSee('id="multiple" value=""', false);
        $response->assertSee('id="popup" value="0"', false);
        $response->assertSee('id="crossdomain" value="0"', false);
        $response->assertSee('id="return_relative_url" value="0"', false);
    }

    #[Test]
    public function scalar_picker_params_still_work(): void
    {
        $this->get(route('filemanager.dialog', ['multiple' => 1, 'popup' => 1, 'relative_url' => 1]))
            ->assertOk()
            ->assertSee('id="multiple" value="1"', false)
            ->assertSee('id="popup" value="1"', false)
            ->assertSee('id="return_relative_url" value="1"', false);
    }

    #[Test]
    public function the_htaccess_options_line_is_written_by_default_and_can_be_omitted(): void
    {
        $files = app(FilesystemManager::class);

        $files->protectDirectory('dengan-options');
        $this->assertStringContainsString(
            'Options -ExecCGI -Indexes',
            Storage::disk('filemanager')->get('dengan-options/.htaccess')
        );

        config(['filemanager.htaccess_options' => false]);
        $files->protectDirectory('tanpa-options');
        $htaccess = Storage::disk('filemanager')->get('tanpa-options/.htaccess');

        $this->assertDoesNotMatchRegularExpression('/^\s*Options\b/m', $htaccess);
        // the rest of the hardening is untouched
        $this->assertStringContainsString('Require all denied', $htaccess);
        $this->assertStringContainsString('php_flag engine off', $htaccess);
    }

    #[Test]
    public function mutating_actions_dispatch_an_audit_event_with_user_action_and_path(): void
    {
        Event::fake([FilemanagerActionPerformed::class]);

        Storage::disk('filemanager')->put('lama.txt', 'x');
        config(['filemanager.extensions.document' => ['txt']]);

        $this->post(route('filemanager.execute'), ['action' => 'create_folder', 'path' => '', 'name' => 'album'])->assertOk();
        $this->post(route('filemanager.execute'), ['action' => 'rename_file', 'path' => 'lama.txt', 'name' => 'baru'])->assertOk();
        $this->post(route('filemanager.execute'), ['action' => 'delete_file', 'path' => 'baru.txt'])->assertOk();
        $this->post(route('filemanager.upload'), ['fldr' => 'album', 'files' => [UploadedFile::fake()->image('foto.jpg')]])->assertOk();

        Event::assertDispatched(FilemanagerActionPerformed::class, fn ($e) => $e->action === 'create_folder' && $e->paths === ['album'] && $e->userId === 1);
        Event::assertDispatched(FilemanagerActionPerformed::class, fn ($e) => $e->action === 'rename_file' && $e->paths === ['from' => 'lama.txt', 'to' => 'baru.txt']);
        Event::assertDispatched(FilemanagerActionPerformed::class, fn ($e) => $e->action === 'delete_file' && $e->paths === ['baru.txt']);
        Event::assertDispatched(FilemanagerActionPerformed::class, fn ($e) => $e->action === 'upload' && $e->paths === ['album/foto.jpg']);
    }

    #[Test]
    public function a_paste_is_audited_and_written_to_the_log(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->once()->with('filemanager.paste_cut', Mockery::on(
            fn (array $context) => $context['user_id'] === 1
                && $context['action'] === 'paste_cut'
                && $context['paths'] === ['from' => 'a.jpg', 'to' => 'tujuan/a.jpg']
        ));

        Storage::disk('filemanager')->put('a.jpg', 'x');
        Storage::disk('filemanager')->makeDirectory('tujuan');
        session(['filemanager.clipboard' => ['path' => 'a.jpg', 'action' => 'cut']]);

        $this->post(route('filemanager.execute'), ['action' => 'paste_clipboard', 'path' => 'tujuan'])->assertOk();

        Storage::disk('filemanager')->assertExists('tujuan/a.jpg');
    }

    #[Test]
    public function audit_logging_can_be_disabled_while_the_event_still_fires(): void
    {
        Event::fake([FilemanagerActionPerformed::class]);
        Log::shouldReceive('channel')->never();
        config(['filemanager.audit_log.enabled' => false]);

        $this->post(route('filemanager.execute'), ['action' => 'create_folder', 'path' => '', 'name' => 'album'])->assertOk();

        Event::assertDispatched(FilemanagerActionPerformed::class);
    }

    #[Test]
    public function a_throwing_disk_in_execute_returns_200_with_a_generic_message_and_is_reported(): void
    {
        Exceptions::fake();

        $this->partialMock(FilesystemManager::class, function ($mock) {
            $mock->shouldReceive('deleteFile')->andThrow(UnableToDeleteFile::atLocation('a.jpg', 'S3 down'));
        });

        $this->post(route('filemanager.execute'), ['action' => 'delete_file', 'path' => 'a.jpg'])
            ->assertOk()
            ->assertSee(trans('filemanager::filemanager.storage_error'), false)
            ->assertDontSee('S3 down', false);

        Exceptions::assertReported(UnableToDeleteFile::class);
    }

    #[Test]
    public function a_throwing_disk_in_upload_returns_a_per_file_error_not_a_500(): void
    {
        Exceptions::fake();
        config(['filemanager.extensions.image' => ['jpg']]);

        $disk = Mockery::mock(Filesystem::class);
        $disk->shouldReceive('exists')->andReturnFalse();
        $disk->shouldReceive('put')->andThrow(UnableToWriteFile::atLocation('foto.jpg', 'SFTP down'));

        $this->partialMock(FilesystemManager::class, function ($mock) use ($disk) {
            $mock->shouldReceive('disk')->andReturn($disk);
        });

        $response = $this->post(route('filemanager.upload'), ['fldr' => '', 'files' => [UploadedFile::fake()->image('foto.jpg')]]);

        $response->assertOk();
        $this->assertSame('foto.jpg', $response->json('files.0.name'));
        $this->assertSame(trans('filemanager::filemanager.storage_error'), $response->json('files.0.error'));
        Exceptions::assertReported(UnableToWriteFile::class);
    }

    #[Test]
    public function file_urls_follow_the_request_host_by_default_but_app_url_when_configured(): void
    {
        config(['app.url' => 'https://desa.example']);
        // stands in for a forged Host header reaching url()
        URL::forceRootUrl('http://evil.test');

        $files = app(FilesystemManager::class);

        $this->assertStringStartsWith('http://evil.test/', $files->url('media/foto.jpg'));

        config(['filemanager.use_app_url' => true]);

        $url = $files->url('media/foto.jpg');
        $this->assertStringStartsWith('https://desa.example/', $url);
        $this->assertStringEndsWith('/media/foto.jpg', $url);
    }
}
