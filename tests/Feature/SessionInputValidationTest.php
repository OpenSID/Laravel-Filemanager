<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Storage;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * sort_by / filter / view are user input persisted in the session (#8).
 */
class SessionInputValidationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('filemanager');
        Storage::fake('filemanager_thumbs');

        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();
        $this->withoutMiddleware(VerifyCsrfToken::class);

        Storage::disk('filemanager')->put('a.jpg', 'x');
        Storage::disk('filemanager')->put('b.jpg', 'xx');
    }

    #[Test]
    public function an_array_sort_by_in_the_query_does_not_crash_the_dialog(): void
    {
        // Regression: sort_by[]=x reached sortEntries(string $sortBy) -> TypeError -> 500.
        $this->get(route('filemanager.dialog').'?sort_by[]=x&view[]=1&filter[]=y')->assertOk();

        $this->assertSame('name', Session::get('filemanager.sort_by'));
        $this->assertSame(0, Session::get('filemanager.view_type'));
    }

    #[Test]
    public function an_unknown_sort_field_and_view_type_fall_back_to_the_defaults(): void
    {
        $this->get(route('filemanager.dialog', ['sort_by' => 'password', 'view' => 99]))->assertOk();

        $this->assertSame('name', Session::get('filemanager.sort_by'));
        $this->assertSame(0, Session::get('filemanager.view_type'));
    }

    #[Test]
    public function whitelisted_values_are_kept(): void
    {
        $this->get(route('filemanager.dialog', ['sort_by' => 'size', 'view' => 2]))
            ->assertOk()
            ->assertSee('id="sort_by" value="size"', false);

        $this->assertSame('size', Session::get('filemanager.sort_by'));
        $this->assertSame(2, Session::get('filemanager.view_type'));
    }

    #[Test]
    public function the_ajax_sort_action_cannot_poison_the_session_with_an_array(): void
    {
        $this->post(route('filemanager.ajax'), ['action' => 'sort', 'sort_by' => ['x']])->assertOk();
        $this->assertSame('name', Session::get('filemanager.sort_by'));

        $this->post(route('filemanager.ajax'), ['action' => 'sort', 'sort_by' => 'date'])->assertOk();
        $this->assertSame('date', Session::get('filemanager.sort_by'));
    }

    #[Test]
    public function the_ajax_view_and_filter_actions_are_normalised(): void
    {
        $this->post(route('filemanager.ajax'), ['action' => 'view', 'type' => '7'])->assertOk();
        $this->assertSame(0, Session::get('filemanager.view_type'));

        $this->post(route('filemanager.ajax'), ['action' => 'view', 'type' => '1'])->assertOk();
        $this->assertSame(1, Session::get('filemanager.view_type'));

        $this->post(route('filemanager.ajax'), ['action' => 'filter', 'type' => ['a' => 'b']])->assertOk();
        $this->assertSame('', Session::get('filemanager.filter'));
    }

    #[Test]
    public function an_already_poisoned_session_no_longer_crashes_the_dialog(): void
    {
        // Sessions written before this fix may still hold an array.
        $this->withSession(['filemanager.sort_by' => ['x'], 'filemanager.view_type' => ['y'], 'filemanager.filter' => ['z']])
            ->get(route('filemanager.dialog'))
            ->assertOk();
    }
}
