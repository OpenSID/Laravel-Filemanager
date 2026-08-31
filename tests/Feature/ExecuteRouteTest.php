<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class ExecuteRouteTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
    }

    #[Test]
    public function the_route_carries_the_web_middleware_group_so_csrf_is_enforced_outside_of_tests(): void
    {
        // Laravel deliberately disables CSRF enforcement for every request
        // made during a PHPUnit run (Illuminate's CSRF middleware has a
        // built-in runningUnitTests() bypass) — so the only way to prove
        // the protection is actually wired up is to inspect the route's
        // middleware, not to try to trigger a real 419 here.
        $route = app('router')->getRoutes()->getByName('filemanager.execute');

        $this->assertContains('web', $route->gatherMiddleware());
    }

    #[Test]
    public function create_folder_then_delete_folder_round_trips_on_the_real_disk(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->post(route('filemanager.execute'), [
            'action' => 'create_folder',
            'path' => '',
            'name' => 'album-baru',
        ])->assertOk()->assertSee('', false); // empty body = success, per the package's own contract

        Storage::disk('filemanager')->assertExists('album-baru');

        $this->post(route('filemanager.execute'), [
            'action' => 'delete_folder',
            'path' => 'album-baru',
        ])->assertOk();

        Storage::disk('filemanager')->assertMissing('album-baru');
    }

    #[Test]
    public function create_folder_rejects_a_path_traversal_attempt_without_touching_the_disk(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $this->post(route('filemanager.execute'), [
            'action' => 'create_folder',
            'path' => '../../etc',
            'name' => 'pwned',
        ])->assertOk(); // the package's contract: friendly error body, not a 500

        // Note: we can't ask the disk to assertMissing('../../etc/pwned')
        // here — Flysystem's own path normalizer throws PathTraversalDetected
        // on a traversal-shaped path before it can even answer that
        // question. Confirming nothing exists at all is the safe way to
        // verify the attempt didn't create anything, anywhere.
        $this->assertSame([], Storage::disk('filemanager')->allFiles());
        $this->assertSame([], Storage::disk('filemanager')->allDirectories());
    }

    #[Test]
    public function unknown_action_returns_a_friendly_error_body_not_a_crash(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        $response = $this->post(route('filemanager.execute'), ['action' => 'not_a_real_action']);

        $response->assertOk();
        $this->assertNotSame('', $response->getContent());
    }

    #[Test]
    public function delete_is_denied_without_the_delete_ability(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);
        Gate::define('filemanager.delete', fn () => false);

        Storage::disk('filemanager')->makeDirectory('protected-folder');

        $this->post(route('filemanager.execute'), [
            'action' => 'delete_folder',
            'path' => 'protected-folder',
        ])->assertOk();

        Storage::disk('filemanager')->assertExists('protected-folder');
    }

    #[Test]
    public function delete_file_rejects_deleting_hidden_files(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Storage::disk('filemanager')->put('.htaccess', 'deny from all');

        $this->post(route('filemanager.execute'), [
            'action' => 'delete_file',
            'path' => '.htaccess',
        ])->assertOk();

        Storage::disk('filemanager')->assertExists('.htaccess');
    }

    #[Test]
    public function save_text_file_rejects_overwriting_non_editable_or_hidden_files(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        config(['filemanager.text_editing_enabled' => true]);

        Storage::disk('filemanager')->put('.htaccess', 'deny from all');
        Storage::disk('filemanager')->put('image.png', 'binary-data');

        $this->post(route('filemanager.execute'), [
            'action' => 'save_text_file',
            'path' => '.htaccess',
            'new_content' => 'allow from all',
        ])->assertOk();

        $this->assertSame('deny from all', Storage::disk('filemanager')->get('.htaccess'));

        $this->post(route('filemanager.execute'), [
            'action' => 'save_text_file',
            'path' => 'image.png',
            'new_content' => 'malicious text',
        ])->assertOk();

        $this->assertSame('binary-data', Storage::disk('filemanager')->get('image.png'));
    }

    #[Test]
    public function rename_file_rejects_renaming_to_disallowed_extension_or_hidden_file(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Storage::disk('filemanager')->put('readme', 'hello world');

        $this->post(route('filemanager.execute'), [
            'action' => 'rename_file',
            'path' => 'readme',
            'name' => 'exploit.php',
        ])->assertOk();

        Storage::disk('filemanager')->assertMissing('exploit.php');
        Storage::disk('filemanager')->assertExists('readme');
    }

    #[Test]
    public function crop_image_rejects_dangerous_new_extension(): void
    {
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Storage::disk('filemanager')->put('original.jpg', 'img-data');

        $this->post(route('filemanager.execute'), [
            'action' => 'crop_image',
            'path' => 'original.jpg',
            'name_new' => 'payload.php',
            'image_data' => 'data:image/jpeg;base64,'.base64_encode('fake image bytes'),
        ])->assertOk();

        Storage::disk('filemanager')->assertMissing('payload.php');
    }
}
