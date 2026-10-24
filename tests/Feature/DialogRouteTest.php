<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class DialogRouteTest extends TestCase
{
    #[Test]
    public function guests_are_redirected_away_from_the_dialog(): void
    {
        // A stand-in for the host app's real login route — this isolated
        // package doesn't ship one, but Auth's redirect-to-login needs
        // *something* named "login" to resolve a target URL for.
        Route::get('login', fn () => 'login')->name('login');

        $this->get(route('filemanager.dialog'))->assertRedirect(route('login'));
    }

    #[Test]
    public function an_authenticated_user_can_open_the_dialog(): void
    {
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->get(route('filemanager.dialog'))
            ->assertOk()
            ->assertSee('Toolbar', false);
    }

    #[Test]
    public function the_dialog_route_is_a_clean_laravel_path_not_a_php_file_reference(): void
    {
        $url = route('filemanager.dialog');

        $this->assertStringEndsWith('/filemanager', $url);
        $this->assertStringNotContainsString('.php', $url);
    }

    #[Test]
    public function a_guest_is_blocked_even_when_the_auth_middleware_is_absent(): void
    {
        // Defence-in-depth: if a host trims `auth` out of
        // config('filemanager.middleware'), EnsureFilemanagerAccess must
        // still refuse an unauthenticated request rather than fall through
        // to the always-true default `filemanager.access` gate.
        $this->withoutMiddleware(Authenticate::class);
        $this->allowAllFilemanagerAbilities();

        $this->get(route('filemanager.dialog'))->assertStatus(401);
    }

    #[Test]
    public function an_authenticated_user_without_the_access_ability_is_forbidden(): void
    {
        $this->actingAsUser();
        Gate::define('filemanager.access', fn () => false);

        $this->get(route('filemanager.dialog'))->assertForbidden();
    }

    #[Test]
    public function query_identifiers_are_sanitized(): void
    {
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $response = $this->get(route('filemanager.dialog', [
            'callback' => 'alert(1);//valid_callback',
            'field_id' => '"><script>alert(1)</script>',
        ]));

        $response->assertOk();
        $response->assertSee('value="alert1valid_callback"', false);
        $response->assertSee('value="scriptalert1script"', false);
        $response->assertDontSee('value="alert(1);//valid_callback"', false);
    }

    #[Test]
    public function the_grid_uses_thumbnails_only_for_images_that_have_one(): void
    {
        // #9: thumbnail presence comes from one listing of the thumbs folder
        // (FilesystemManager::thumbnailIndex) instead of exists() per image.
        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs', ['url' => 'http://thumbs.test/t']);
        config(['filemanager.extensions.image' => ['jpg', 'png']]);

        Storage::disk('filemanager')->put('galeri/ada.jpg', 'x');
        Storage::disk('filemanager')->put('galeri/tanpa.png', 'x');
        Storage::disk('filemanager_thumbs')->put('galeri/ada.jpg', 't');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->get(route('filemanager.dialog', ['fldr' => 'galeri']))
            ->assertOk()
            ->assertSee('http://thumbs.test/t/galeri/ada.jpg', false)
            ->assertDontSee('http://thumbs.test/t/galeri/tanpa.png', false)
            ->assertSee('tanpa.png', false);
    }
}
