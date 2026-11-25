<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use Illuminate\Support\Facades\Gate;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\Attributes\Test;

/**
 * Gate bawaan paket (LaravelFilemanagerServiceProvider::checkConfiguredAbility)
 * — tanpa override allowAllFilemanagerAbilities(), supaya yang diuji benar-benar
 * definisi bawaan berbasis config('filemanager.permissions').
 */
class DefaultGatesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
    }

    #[Test]
    public function a_null_permission_always_allows_regardless_of_context(): void
    {
        config(['filemanager.permissions.upload' => null]);

        $this->assertTrue(Gate::allows('filemanager.upload', null));
        $this->assertTrue(Gate::allows('filemanager.upload', 'artikel'));
    }

    #[Test]
    public function access_is_allowed_without_a_module_context_even_when_a_level_is_configured(): void
    {
        config(['filemanager.permissions.access' => 'b']);

        $this->assertTrue(Gate::allows('filemanager.access', null));
    }

    #[Test]
    public function upload_and_delete_do_not_get_the_null_context_exemption(): void
    {
        config(['filemanager.permissions.upload' => 'u', 'filemanager.permissions.delete' => 'h']);

        $this->assertFalse(Gate::allows('filemanager.upload', null));
        $this->assertFalse(Gate::allows('filemanager.delete', null));
    }

    #[Test]
    public function a_configured_level_is_denied_when_the_host_has_no_can_helper(): void
    {
        if (function_exists('can')) {
            $this->markTestSkipped('Fungsi global can() sudah terdefinisi di proses ini.');
        }

        config(['filemanager.permissions.access' => 'b']);

        $this->assertFalse(Gate::allows('filemanager.access', 'artikel'));
        $this->assertFalse(Gate::allows('filemanager.upload', 'artikel'));
        $this->assertFalse(Gate::allows('filemanager.delete', 'artikel'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function a_configured_level_is_delegated_to_the_host_can_helper_with_the_module_context(): void
    {
        // Proses terpisah: fungsi global tidak bisa dihapus lagi, jadi jangan
        // sampai bocor ke test lain yang mengandalkan can() tidak ada.
        eval('function can($akses, $modul) { return $akses === "u" && $modul === "artikel"; }');

        config(['filemanager.permissions.upload' => 'u', 'filemanager.permissions.delete' => 'h']);

        $this->assertTrue(Gate::allows('filemanager.upload', 'artikel'));
        $this->assertFalse(Gate::allows('filemanager.upload', 'surat'));
        $this->assertFalse(Gate::allows('filemanager.delete', 'artikel'));
    }
}
