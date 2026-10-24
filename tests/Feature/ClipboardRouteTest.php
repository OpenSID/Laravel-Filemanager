<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regresi #7: copy_cut (AjaxController) dan paste_clipboard
 * (ExecuteController) harus menerapkan aturan yang sama dengan
 * delete/rename — berkas pelindung (.htaccess, index.html) tidak boleh
 * dipindah dan ekstensi di luar whitelist ditolak.
 */
class ClipboardRouteTest extends TestCase
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
    #[DataProvider('protectedFiles')]
    public function cutting_a_protected_or_non_whitelisted_file_is_refused(string $name): void
    {
        Storage::disk('filemanager')->put("album/{$name}", 'isi');

        $this->post(route('filemanager.ajax'), [
            'action' => 'copy_cut',
            'sub_action' => 'cut',
            'path' => "album/{$name}",
        ])->assertOk()->assertSee(trans('filemanager::filemanager.File_Permission_Not_Allowed'), false);

        $this->assertNull(Session::get('filemanager.clipboard.path'));
    }

    public static function protectedFiles(): array
    {
        return [
            'htaccess' => ['.htaccess'],
            'index.html' => ['index.html'],
            'env' => ['.env'],
            'sql dump' => ['dump.sql'],
        ];
    }

    #[Test]
    public function cutting_a_hidden_folder_is_refused(): void
    {
        Storage::disk('filemanager')->put('.git/config', 'isi');

        $this->post(route('filemanager.ajax'), [
            'action' => 'copy_cut',
            'sub_action' => 'cut',
            'path' => '.git',
        ])->assertOk()->assertSee(trans('filemanager::filemanager.File_Permission_Not_Allowed'), false);

        $this->assertNull(Session::get('filemanager.clipboard.path'));
    }

    #[Test]
    public function pasting_a_protected_file_left_in_the_clipboard_is_refused(): void
    {
        // Clipboard yang terisi sebelum copy_cut diperketat (mis. sesi lama)
        // tetap harus ditolak saat paste.
        Storage::disk('filemanager')->put('album/.htaccess', 'deny from all');
        Storage::disk('filemanager')->makeDirectory('lain');

        $this->withSession([
            'filemanager.clipboard.path' => 'album/.htaccess',
            'filemanager.clipboard.action' => 'cut',
        ])->post(route('filemanager.execute'), [
            'action' => 'paste_clipboard',
            'path' => 'lain',
        ])->assertOk()->assertSee(trans('filemanager::filemanager.File_Permission_Not_Allowed'), false);

        Storage::disk('filemanager')->assertExists('album/.htaccess');
        Storage::disk('filemanager')->assertMissing('lain/.htaccess');
        $this->assertNull(Session::get('filemanager.clipboard.path'));
    }

    #[Test]
    public function pasting_into_a_hidden_folder_is_refused(): void
    {
        Storage::disk('filemanager')->put('album/foto.jpg', 'jpg');
        Storage::disk('filemanager')->makeDirectory('.git');

        $this->withSession([
            'filemanager.clipboard.path' => 'album/foto.jpg',
            'filemanager.clipboard.action' => 'cut',
        ])->post(route('filemanager.execute'), [
            'action' => 'paste_clipboard',
            'path' => '.git',
        ])->assertOk()->assertSee(trans('filemanager::filemanager.File_Permission_Not_Allowed'), false);

        Storage::disk('filemanager')->assertExists('album/foto.jpg');
        Storage::disk('filemanager')->assertMissing('.git/foto.jpg');
    }

    #[Test]
    public function cut_and_paste_of_an_ordinary_file_still_works(): void
    {
        Storage::disk('filemanager')->put('album/foto.jpg', 'jpg');
        Storage::disk('filemanager')->makeDirectory('lain');

        $this->post(route('filemanager.ajax'), [
            'action' => 'copy_cut',
            'sub_action' => 'cut',
            'path' => 'album/foto.jpg',
        ])->assertOk()->assertContent('');

        $this->post(route('filemanager.execute'), [
            'action' => 'paste_clipboard',
            'path' => 'lain',
        ])->assertOk()->assertContent('');

        Storage::disk('filemanager')->assertMissing('album/foto.jpg');
        Storage::disk('filemanager')->assertExists('lain/foto.jpg');
    }

    #[Test]
    public function get_file_refuses_hidden_editable_text_files(): void
    {
        config([
            'filemanager.text_editing_enabled' => true,
            'filemanager.hidden_files' => ['rahasia.txt'],
        ]);

        Storage::disk('filemanager')->put('rahasia.txt', 'isi rahasia');

        $this->post(route('filemanager.ajax'), [
            'action' => 'get_file',
            'sub_action' => 'preview',
            'path' => 'rahasia.txt',
        ])->assertOk()->assertDontSee('isi rahasia');
    }
}
