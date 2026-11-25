<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Services\ClipboardManager;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * AjaxController's contract (inherited from rfm/ajax_calls.php): always
 * HTTP 200, empty body = success, any non-empty body = an error message
 * include.js alert()s.
 */
class AjaxRouteTest extends TestCase
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

    protected function clipboard(): ClipboardManager
    {
        return app(ClipboardManager::class);
    }

    #[Test]
    public function copy_and_cut_put_the_path_and_action_on_the_clipboard(): void
    {
        foreach (['copy', 'cut'] as $subAction) {
            $this->post(route('filemanager.ajax'), [
                'action' => 'copy_cut',
                'sub_action' => $subAction,
                'path' => 'album/foto.jpg',
            ])->assertOk()->assertContent('');

            $this->assertSame('album/foto.jpg', $this->clipboard()->path());
            $this->assertSame($subAction, $this->clipboard()->action());
        }
    }

    #[Test]
    public function copy_cut_rejects_an_unknown_sub_action_without_touching_the_clipboard(): void
    {
        $response = $this->post(route('filemanager.ajax'), [
            'action' => 'copy_cut',
            'sub_action' => 'move',
            'path' => 'foto.jpg',
        ]);

        $response->assertOk();
        $this->assertNotSame('', $response->getContent());
        $this->assertFalse($this->clipboard()->hasContent());
    }

    #[Test]
    public function copy_cut_requires_the_delete_ability(): void
    {
        Gate::define('filemanager.delete', fn () => false);

        $response = $this->post(route('filemanager.ajax'), [
            'action' => 'copy_cut',
            'sub_action' => 'cut',
            'path' => 'foto.jpg',
        ]);

        $response->assertOk();
        $this->assertNotSame('', $response->getContent());
        $this->assertFalse($this->clipboard()->hasContent());
    }

    #[Test]
    public function copy_cut_rejects_an_empty_path(): void
    {
        $response = $this->post(route('filemanager.ajax'), [
            'action' => 'copy_cut',
            'sub_action' => 'copy',
            'path' => '   ',
        ]);

        $response->assertOk();
        $this->assertNotSame('', $response->getContent());
        $this->assertFalse($this->clipboard()->hasContent());
    }

    #[Test]
    public function copy_cut_rejects_traversal_and_paths_outside_the_base_folder(): void
    {
        config(['filemanager.base_folder' => 'desa']);

        foreach (['../../.env', 'desa/../../.env', 'lain/foto.jpg'] as $path) {
            $response = $this->post(route('filemanager.ajax'), [
                'action' => 'copy_cut',
                'sub_action' => 'copy',
                'path' => $path,
            ]);

            $response->assertOk();
            $this->assertNotSame('', $response->getContent(), "path {$path} should be rejected");
            $this->assertFalse($this->clipboard()->hasContent(), "path {$path} must not reach the clipboard");
        }
    }

    #[Test]
    public function clear_clipboard_empties_it(): void
    {
        $this->clipboard()->set('foto.jpg', 'copy');

        $this->post(route('filemanager.ajax'), ['action' => 'clear_clipboard'])
            ->assertOk()
            ->assertContent('');

        $this->assertFalse($this->clipboard()->hasContent());
    }

    #[Test]
    public function get_file_is_refused_while_text_editing_is_disabled(): void
    {
        config(['filemanager.text_editing_enabled' => false]);
        Storage::disk('filemanager')->put('catatan.txt', 'rahasia');

        $response = $this->get(route('filemanager.ajax', [
            'action' => 'get_file',
            'sub_action' => 'preview',
            'file' => 'catatan.txt',
        ]));

        $response->assertOk();
        $response->assertDontSee('rahasia', false);
    }

    #[Test]
    public function get_file_returns_the_content_html_escaped_inside_a_textarea(): void
    {
        config(['filemanager.text_editing_enabled' => true]);
        Storage::disk('filemanager')->put('catatan.txt', '</textarea><script>alert(1)</script>');

        $response = $this->get(route('filemanager.ajax', [
            'action' => 'get_file',
            'sub_action' => 'preview',
            'file' => 'catatan.txt',
        ]));

        $response->assertOk();
        $response->assertSee('<textarea id="textfile_edit_area"', false);
        $response->assertSee('&lt;/textarea&gt;&lt;script&gt;', false);
        $response->assertDontSee('<script>alert(1)</script>', false);
    }

    #[Test]
    public function get_file_refuses_an_extension_that_is_not_editable_text(): void
    {
        config(['filemanager.text_editing_enabled' => true]);
        Storage::disk('filemanager')->put('skrip.php', '<?php echo "x";');

        $response = $this->get(route('filemanager.ajax', [
            'action' => 'get_file',
            'sub_action' => 'edit',
            'file' => 'skrip.php',
        ]));

        $response->assertOk();
        $response->assertDontSee('textfile_edit_area', false);
        $response->assertDontSee('echo', false);
    }

    #[Test]
    public function get_file_rejects_an_unknown_sub_action_a_missing_file_and_a_traversal(): void
    {
        config(['filemanager.text_editing_enabled' => true]);
        Storage::disk('filemanager')->put('catatan.txt', 'isi');

        $cases = [
            ['sub_action' => 'delete', 'file' => 'catatan.txt'],
            ['sub_action' => 'preview', 'file' => 'tidak-ada.txt'],
            ['sub_action' => 'preview', 'file' => '../../.env.txt'],
        ];

        foreach ($cases as $params) {
            $response = $this->get(route('filemanager.ajax', ['action' => 'get_file'] + $params));

            $response->assertOk();
            $this->assertNotSame('', $response->getContent());
            $response->assertDontSee('textfile_edit_area', false);
        }
    }

    #[Test]
    public function an_unknown_action_returns_a_friendly_error_body(): void
    {
        $response = $this->post(route('filemanager.ajax'), ['action' => 'hapus_semua']);

        $response->assertOk();
        $this->assertNotSame('', $response->getContent());
    }
}
