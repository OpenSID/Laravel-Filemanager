<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
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
    public function the_dialog_cannot_be_framed_by_foreign_origins_by_default(): void
    {
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->get(route('filemanager.dialog'))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self'")
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function configured_origins_are_added_to_frame_ancestors_and_invalid_ones_ignored(): void
    {
        config(['filemanager.allowed_origins' => [
            'https://CMS.example.id/',
            'https://desa.example.id:8443',
            'https://evil.example; script-src *',
            'javascript:alert(1)',
            '',
        ]]);
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->get(route('filemanager.dialog'))
            ->assertOk()
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'self' https://cms.example.id https://desa.example.id:8443")
            ->assertHeaderMissing('X-Frame-Options')
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    #[Test]
    public function crossdomain_post_message_targets_an_allowed_referer_origin_never_a_wildcard(): void
    {
        config(['filemanager.allowed_origins' => ['https://cms.example.id', 'https://desa.example.id']]);
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->withHeader('Referer', 'https://desa.example.id/admin/artikel?id=1')
            ->get(route('filemanager.dialog', ['crossdomain' => 1, 'field_id' => 'gambar']))
            ->assertOk()
            ->assertSee('var rfm_target_origin = "https:\/\/desa.example.id" || window.location.origin;', false);
    }

    #[Test]
    public function crossdomain_post_message_falls_back_to_own_origin_for_a_foreign_referer(): void
    {
        config(['filemanager.allowed_origins' => ['https://cms.example.id', 'https://desa.example.id']]);
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->withHeader('Referer', 'https://evil.example/steal')
            ->get(route('filemanager.dialog', ['crossdomain' => 1, 'field_id' => 'gambar']))
            ->assertOk()
            ->assertSee('var rfm_target_origin = "" || window.location.origin;', false)
            ->assertDontSee('evil.example', false);
    }

    #[Test]
    public function crossdomain_post_message_uses_the_single_allowed_origin_without_a_referer(): void
    {
        config(['filemanager.allowed_origins' => ['https://cms.example.id']]);
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->get(route('filemanager.dialog', ['crossdomain' => 1]))
            ->assertOk()
            ->assertSee('var rfm_target_origin = "https:\/\/cms.example.id" || window.location.origin;', false);
    }

    #[Test]
    public function include_js_never_posts_messages_to_a_wildcard_origin(): void
    {
        $js = (string) file_get_contents(__DIR__.'/../../resources/dist/js/include.js');

        $this->assertGreaterThan(0, substr_count($js, 'postMessage('));
        $this->assertDoesNotMatchRegularExpression('/postMessage\([^)]*,\s*["\']\*["\']\s*\)/', $js);
    }

    #[Test]
    public function include_js_does_not_build_markup_from_file_names_by_string_concatenation(): void
    {
        $js = (string) file_get_contents(__DIR__.'/../../resources/dist/js/include.js');

        // Duplikat berkas dulu menyusun <li ... data-name='"+nama+"'> —
        // nama dengan kutip tunggal bisa keluar dari atribut (DOM XSS).
        $this->assertStringNotContainsString("data-name='\"+", $js);
        // Pembersih nama sisi klien harus membuang SEMUA kutip/garis miring,
        // bukan hanya kemunculan pertama (String.replace tanpa flag g).
        $this->assertStringNotContainsString(".replace(\"'\",\"\")", $js);
    }
}
