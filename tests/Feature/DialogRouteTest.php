<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

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
        \Illuminate\Support\Facades\Route::get('login', fn () => 'login')->name('login');

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
    public function an_authenticated_user_without_the_access_ability_is_forbidden(): void
    {
        $this->actingAsUser();
        \Illuminate\Support\Facades\Gate::define('filemanager.access', fn () => false);

        $this->get(route('filemanager.dialog'))->assertForbidden();
    }
}
