<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use OpenSID\LaravelFilemanager\Support\FilemanagerConfig;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class UploadLimitDialogTest extends TestCase
{
    #[Test]
    public function the_dialog_shows_the_upload_limit_and_configures_the_uploader(): void
    {
        config(['filemanager.max_upload_size' => 8]);
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $chunk = (new FilemanagerConfig)->maxChunkSizeBytes();

        $this->get(route('filemanager.dialog'))
            ->assertOk()
            ->assertSee('id="upload-limit-info"', false)
            ->assertSee(trans('filemanager::filemanager.Upload_max_size_info', ['size' => 8]), false)
            ->assertSee('maxChunkSize: '.$chunk, false)
            ->assertSee('maxFileSize: '.(8 * 1024 * 1024), false);
    }

    #[Test]
    public function the_displayed_limit_follows_the_configured_size(): void
    {
        config(['filemanager.max_upload_size' => 3]);
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->get(route('filemanager.dialog'))
            ->assertSee(trans('filemanager::filemanager.Upload_max_size_info', ['size' => 3]), false)
            ->assertSee('maxFileSize: '.(3 * 1024 * 1024), false);
    }
}
