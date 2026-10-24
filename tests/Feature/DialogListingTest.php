<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

/**
 * DialogController: urutan entri, breadcrumb di bawah base_folder, pemilihan
 * fungsi "apply" untuk include.js, serta teks UI yang mengikuti locale.
 */
class DialogListingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
    }

    protected function names($response): array
    {
        return array_column($response->viewData('entries'), 'name');
    }

    #[Test]
    public function entries_are_sorted_by_name_with_folders_first_in_both_directions(): void
    {
        Storage::disk('filemanager')->put('b.jpg', 'x');
        Storage::disk('filemanager')->put('A.jpg', 'x');
        Storage::disk('filemanager')->put('c.jpg', 'x');
        Storage::disk('filemanager')->makeDirectory('zeta');
        Storage::disk('filemanager')->makeDirectory('alfa');

        $ascending = $this->get(route('filemanager.dialog', ['fldr' => '', 'sort_by' => 'name', 'descending' => 0]));
        $ascending->assertOk();
        $this->assertSame(['alfa', 'zeta', 'A.jpg', 'b.jpg', 'c.jpg'], $this->names($ascending));

        $descending = $this->get(route('filemanager.dialog', ['fldr' => '', 'sort_by' => 'name', 'descending' => 1]));
        $this->assertSame(['zeta', 'alfa', 'c.jpg', 'b.jpg', 'A.jpg'], $this->names($descending));
    }

    #[Test]
    public function entries_can_be_sorted_by_size(): void
    {
        Storage::disk('filemanager')->put('sedang.jpg', str_repeat('x', 20));
        Storage::disk('filemanager')->put('kecil.jpg', str_repeat('x', 5));
        Storage::disk('filemanager')->put('besar.jpg', str_repeat('x', 50));

        $response = $this->get(route('filemanager.dialog', ['fldr' => '', 'sort_by' => 'size', 'descending' => 0]));

        $this->assertSame(['kecil.jpg', 'sedang.jpg', 'besar.jpg'], $this->names($response));
    }

    #[Test]
    public function the_sort_choice_is_remembered_in_the_session(): void
    {
        Storage::disk('filemanager')->put('a.jpg', 'x');
        Storage::disk('filemanager')->put('b.jpg', 'x');

        $this->get(route('filemanager.dialog', ['fldr' => '', 'sort_by' => 'name', 'descending' => 0]));

        $response = $this->get(route('filemanager.dialog', ['fldr' => '']));

        $response->assertViewHas('sortBy', 'name');
        $this->assertSame(['a.jpg', 'b.jpg'], $this->names($response));
    }

    #[Test]
    public function breadcrumbs_are_relative_to_the_base_folder(): void
    {
        config(['filemanager.base_folder' => 'desa/upload']);
        Storage::disk('filemanager')->makeDirectory('desa/upload/galeri/2026');

        $response = $this->get(route('filemanager.dialog', ['fldr' => 'desa/upload/galeri/2026']));

        $response->assertOk();
        $response->assertViewHas('subdir', 'desa/upload/galeri/2026');
        $response->assertViewHas('breadcrumbs', [
            ['name' => 'galeri', 'path' => 'desa/upload/galeri'],
            ['name' => '2026', 'path' => 'desa/upload/galeri/2026'],
        ]);
    }

    #[Test]
    public function the_base_folder_itself_has_no_breadcrumbs_and_is_the_default_folder(): void
    {
        config(['filemanager.base_folder' => 'desa/upload']);
        Storage::disk('filemanager')->makeDirectory('desa/upload');

        $response = $this->get(route('filemanager.dialog', ['fldr' => '']));

        $response->assertViewHas('subdir', 'desa/upload');
        $response->assertViewHas('breadcrumbs', []);
    }

    #[Test]
    public function a_folder_outside_the_base_folder_falls_back_to_the_base_folder(): void
    {
        config(['filemanager.base_folder' => 'desa/upload']);
        Storage::disk('filemanager')->makeDirectory('desa/upload');
        Storage::disk('filemanager')->makeDirectory('rahasia');

        $response = $this->get(route('filemanager.dialog', ['fldr' => 'rahasia']));

        $response->assertViewHas('subdir', 'desa/upload');
    }

    public static function applyFunctions(): array
    {
        return [
            'gambar' => [['type' => 1], 'apply_img'],
            'tautan' => [['type' => 2], 'apply_link'],
            'video' => [['type' => 3], 'apply_video'],
            'jelajah tanpa field' => [['type' => 0], 'apply_none'],
            'semua tipe ke field' => [['type' => 0, 'field_id' => 'gambar'], 'apply'],
            'banyak berkas' => [['type' => 1, 'multiple' => 1], 'apply_multiple'],
        ];
    }

    #[Test]
    #[DataProvider('applyFunctions')]
    public function the_apply_function_follows_the_requested_type(array $query, string $expected): void
    {
        $this->get(route('filemanager.dialog', $query))
            ->assertOk()
            ->assertViewHas('apply', $expected);
    }

    #[Test]
    public function crop_and_upload_texts_follow_the_active_locale(): void
    {
        app()->setLocale('en_EN');

        $english = $this->get(route('filemanager.dialog'));
        $english->assertSee('Save Image');
        $english->assertSee('Overwrite original file');
        $english->assertSee('Processing...');
        $english->assertSee('"Saving..."', false);
        $english->assertDontSee('Simpan Gambar');
        $english->assertDontSee('Timpa berkas asli');
        $english->assertDontSee('Menyimpan...');

        app()->setLocale('id');

        $indonesian = $this->get(route('filemanager.dialog'));
        $indonesian->assertSee('Simpan Gambar');
        $indonesian->assertSee('Memproses...');
        $indonesian->assertSee('"Menyimpan..."', false);
        $indonesian->assertDontSee('Processing...');
    }
}
