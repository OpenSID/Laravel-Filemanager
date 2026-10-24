<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Support\Facades\Gate;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * The toolbar's "New File" button (.create-file-btn, rendered when
 * text_editing_enabled is on) makes include.js GET ajax?action=new_file_form
 * and shows the body as the create-file dialog. The action used to be
 * dropped, so the dialog showed "no action passed" instead of a form (#15).
 */
class NewFileFormTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
    }

    #[Test]
    public function it_returns_the_create_file_form_with_the_editable_extensions(): void
    {
        config([
            'filemanager.text_editing_enabled' => true,
            'filemanager.editable_text_extensions' => ['txt', 'md'],
        ]);

        $response = $this->get(route('filemanager.ajax', ['action' => 'new_file_form']))->assertOk();

        $response->assertSee('id="create_text_file_name"', false);
        $response->assertSee('id="textfile_create_area"', false);
        $response->assertSee('<option value=".txt">.txt</option>', false);
        $response->assertSee('<option value=".md">.md</option>', false);
        $response->assertDontSee('no action passed', false);
    }

    #[Test]
    public function it_refuses_when_text_editing_is_disabled(): void
    {
        config(['filemanager.text_editing_enabled' => false]);

        $this->get(route('filemanager.ajax', ['action' => 'new_file_form']))
            ->assertOk()
            ->assertDontSee('create_text_file_name', false);
    }

    #[Test]
    public function it_refuses_without_the_upload_ability(): void
    {
        config(['filemanager.text_editing_enabled' => true]);
        Gate::define('filemanager.upload', fn () => false);

        $this->get(route('filemanager.ajax', ['action' => 'new_file_form']))
            ->assertOk()
            ->assertDontSee('create_text_file_name', false);
    }
}
