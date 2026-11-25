<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Jalur sukses (dan no-op yang disengaja) untuk aksi ExecuteController yang
 * sebelumnya belum tercakup ExecuteRouteTest: paste_clipboard, duplicate_file,
 * rename_folder, delete_files, create_file, save_text_file dan crop_image.
 */
class ExecuteActionsTest extends TestCase
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

    protected function execute(array $data, array $session = [])
    {
        return $this->withSession($session)->post(route('filemanager.execute'), $data);
    }

    protected function clipboard(string $path, string $action): array
    {
        return [
            'filemanager.clipboard.path' => $path,
            'filemanager.clipboard.action' => $action,
        ];
    }

    #[Test]
    public function paste_clipboard_copy_duplicates_the_file_into_the_destination_and_clears_the_clipboard(): void
    {
        Storage::disk('filemanager')->put('sumber/laporan.pdf', 'isi');
        Storage::disk('filemanager')->makeDirectory('tujuan');

        $this->execute(['action' => 'paste_clipboard', 'path' => 'tujuan'], $this->clipboard('sumber/laporan.pdf', 'copy'))
            ->assertOk()
            ->assertContent('');

        Storage::disk('filemanager')->assertExists('sumber/laporan.pdf');
        $this->assertSame('isi', Storage::disk('filemanager')->get('tujuan/laporan.pdf'));
        $this->assertNull(session('filemanager.clipboard.path'));
    }

    #[Test]
    public function paste_clipboard_cut_moves_the_file_into_the_destination(): void
    {
        Storage::disk('filemanager')->put('sumber/foto.jpg', 'gambar');
        Storage::disk('filemanager')->makeDirectory('tujuan');

        $this->execute(['action' => 'paste_clipboard', 'path' => 'tujuan'], $this->clipboard('sumber/foto.jpg', 'cut'))
            ->assertOk()
            ->assertContent('');

        Storage::disk('filemanager')->assertMissing('sumber/foto.jpg');
        $this->assertSame('gambar', Storage::disk('filemanager')->get('tujuan/foto.jpg'));
    }

    #[Test]
    public function paste_clipboard_into_the_same_directory_is_a_silent_no_op(): void
    {
        Storage::disk('filemanager')->put('sumber/foto.jpg', 'gambar');

        $this->execute(['action' => 'paste_clipboard', 'path' => 'sumber'], $this->clipboard('sumber/foto.jpg', 'cut'))
            ->assertOk()
            ->assertContent('');

        Storage::disk('filemanager')->assertExists('sumber/foto.jpg');
        $this->assertSame(['sumber/foto.jpg'], Storage::disk('filemanager')->allFiles());
    }

    #[Test]
    public function paste_clipboard_refuses_to_move_a_folder_into_its_own_subfolder(): void
    {
        Storage::disk('filemanager')->put('album/sub/foto.jpg', 'gambar');

        $this->execute(['action' => 'paste_clipboard', 'path' => 'album/sub'], $this->clipboard('album', 'cut'))
            ->assertOk()
            ->assertContent('');

        Storage::disk('filemanager')->assertExists('album/sub/foto.jpg');
        Storage::disk('filemanager')->assertMissing('album/sub/album');
    }

    #[Test]
    public function paste_clipboard_without_clipboard_content_does_nothing(): void
    {
        Storage::disk('filemanager')->makeDirectory('tujuan');

        $this->execute(['action' => 'paste_clipboard', 'path' => 'tujuan'])
            ->assertOk()
            ->assertContent('');

        $this->assertSame([], Storage::disk('filemanager')->allFiles());
    }

    #[Test]
    public function duplicate_file_copies_the_file_under_the_new_name_keeping_the_extension(): void
    {
        Storage::disk('filemanager')->put('dok/surat.pdf', 'isi-surat');

        $this->execute(['action' => 'duplicate_file', 'path' => 'dok/surat.pdf', 'name' => 'surat-salinan'])
            ->assertOk()
            ->assertContent('');

        Storage::disk('filemanager')->assertExists('dok/surat.pdf');
        $this->assertSame('isi-surat', Storage::disk('filemanager')->get('dok/surat-salinan.pdf'));
    }

    #[Test]
    public function duplicate_file_does_not_overwrite_an_existing_target(): void
    {
        Storage::disk('filemanager')->put('surat.pdf', 'baru');
        Storage::disk('filemanager')->put('lama.pdf', 'lama');

        $this->execute(['action' => 'duplicate_file', 'path' => 'surat.pdf', 'name' => 'lama'])
            ->assertOk()
            ->assertSee(trans('filemanager::filemanager.Rename_existing_file'), false);

        $this->assertSame('lama', Storage::disk('filemanager')->get('lama.pdf'));
    }

    #[Test]
    public function rename_folder_moves_the_folder_with_its_contents(): void
    {
        Storage::disk('filemanager')->put('album-lama/foto.jpg', 'gambar');

        $this->execute(['action' => 'rename_folder', 'path' => 'album-lama', 'name' => 'album-baru'])
            ->assertOk()
            ->assertContent('');

        Storage::disk('filemanager')->assertMissing('album-lama/foto.jpg');
        Storage::disk('filemanager')->assertExists('album-baru/foto.jpg');
    }

    #[Test]
    public function rename_folder_refuses_to_rename_the_base_folder(): void
    {
        config(['filemanager.base_folder' => 'desa/upload']);
        Storage::disk('filemanager')->put('desa/upload/foto.jpg', 'gambar');

        $this->execute(['action' => 'rename_folder', 'path' => 'desa/upload', 'name' => 'lain'])
            ->assertOk()
            ->assertSee(trans('filemanager::filemanager.wrong path'), false);

        Storage::disk('filemanager')->assertExists('desa/upload/foto.jpg');
    }

    #[Test]
    public function delete_files_removes_every_listed_file_but_skips_hidden_ones(): void
    {
        Storage::disk('filemanager')->put('a.jpg', '1');
        Storage::disk('filemanager')->put('b.pdf', '2');
        Storage::disk('filemanager')->put('.htaccess', 'deny from all');
        Storage::disk('filemanager')->put('c.jpg', '3');

        $this->execute(['action' => 'delete_files', 'paths' => ['a.jpg', 'b.pdf', '.htaccess']])
            ->assertOk()
            ->assertContent('');

        Storage::disk('filemanager')->assertMissing('a.jpg');
        Storage::disk('filemanager')->assertMissing('b.pdf');
        Storage::disk('filemanager')->assertExists('.htaccess');
        Storage::disk('filemanager')->assertExists('c.jpg');
    }

    #[Test]
    public function create_file_writes_a_new_text_file_when_text_editing_is_enabled(): void
    {
        config(['filemanager.text_editing_enabled' => true]);
        Storage::disk('filemanager')->makeDirectory('catatan');

        $this->execute(['action' => 'create_file', 'path' => 'catatan', 'name' => 'agenda.txt', 'new_content' => 'rapat desa'])
            ->assertOk()
            ->assertContent('');

        $this->assertSame('rapat desa', Storage::disk('filemanager')->get('catatan/agenda.txt'));
    }

    #[Test]
    public function create_file_is_refused_while_text_editing_is_disabled_or_for_non_text_extensions(): void
    {
        config(['filemanager.text_editing_enabled' => false]);

        $this->execute(['action' => 'create_file', 'path' => '', 'name' => 'agenda.txt', 'new_content' => 'x'])
            ->assertOk()
            ->assertSee(trans('filemanager::filemanager.File_Open_Edit_Not_Allowed'), false);

        config(['filemanager.text_editing_enabled' => true]);

        $this->execute(['action' => 'create_file', 'path' => '', 'name' => 'halaman.html', 'new_content' => '<script></script>'])
            ->assertOk()
            ->assertSee(trans('filemanager::filemanager.Error_extension'), false);

        $this->assertSame([], Storage::disk('filemanager')->allFiles());
    }

    #[Test]
    public function create_file_does_not_overwrite_an_existing_file(): void
    {
        config(['filemanager.text_editing_enabled' => true]);
        Storage::disk('filemanager')->put('agenda.txt', 'asli');

        $this->execute(['action' => 'create_file', 'path' => '', 'name' => 'agenda.txt', 'new_content' => 'baru'])
            ->assertOk()
            ->assertSee(trans('filemanager::filemanager.Rename_existing_file'), false);

        $this->assertSame('asli', Storage::disk('filemanager')->get('agenda.txt'));
    }

    #[Test]
    public function save_text_file_overwrites_an_editable_text_file(): void
    {
        config(['filemanager.text_editing_enabled' => true]);
        Storage::disk('filemanager')->put('catatan/agenda.txt', 'lama');

        $this->execute(['action' => 'save_text_file', 'path' => 'catatan/agenda.txt', 'new_content' => 'baru'])
            ->assertOk()
            ->assertContent('');

        $this->assertSame('baru', Storage::disk('filemanager')->get('catatan/agenda.txt'));
    }

    #[Test]
    public function crop_image_overwrites_the_original_with_the_cropped_png(): void
    {
        Storage::disk('filemanager')->put('foto.png', $this->png(40, 30));
        $cropped = $this->png(10, 10);

        $this->execute([
            'action' => 'crop_image',
            'path' => 'foto.png',
            'image_data' => 'data:image/png;base64,'.base64_encode($cropped),
        ])->assertOk()->assertContent('');

        $this->assertSame($cropped, Storage::disk('filemanager')->get('foto.png'));
    }

    #[Test]
    public function crop_image_can_save_to_a_new_file_next_to_the_original(): void
    {
        $original = $this->png(40, 30);
        Storage::disk('filemanager')->put('album/foto.png', $original);
        $cropped = $this->png(10, 10);

        $this->execute([
            'action' => 'crop_image',
            'path' => 'album/foto.png',
            'name_new' => 'foto-crop',
            'image_data' => 'data:image/png;base64,'.base64_encode($cropped),
        ])->assertOk()->assertContent('');

        $this->assertSame($original, Storage::disk('filemanager')->get('album/foto.png'));
        $this->assertSame($cropped, Storage::disk('filemanager')->get('album/foto-crop.png'));
    }

    protected function png(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 30, 120, 200));

        ob_start();
        imagepng($image);

        return (string) ob_get_clean();
    }
}
