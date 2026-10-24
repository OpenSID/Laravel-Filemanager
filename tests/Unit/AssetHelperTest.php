<?php

namespace OpenSID\LaravelFilemanager\Tests\Unit;

use Composer\InstalledVersions;
use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

class AssetHelperTest extends TestCase
{
    #[Test]
    public function appends_the_configured_version_as_a_query_string(): void
    {
        config(['filemanager.asset_version' => '1.2.0']);

        $this->assertSame(
            url('assets/vendor/filemanager/js/include.js').'?v=1.2.0',
            filemanager_asset('js/include.js'),
        );
    }

    #[Test]
    public function an_empty_configured_version_disables_the_suffix(): void
    {
        config(['filemanager.asset_version' => '']);

        $this->assertSame(url('assets/vendor/filemanager/css/style.css'), filemanager_asset('/css/style.css'));
    }

    #[Test]
    public function the_version_is_url_encoded(): void
    {
        config(['filemanager.asset_version' => 'v1 2&x']);

        $this->assertStringEndsWith('style.css?v=v1%202%26x', filemanager_asset('css/style.css'));
    }

    #[Test]
    public function defaults_to_the_installed_composer_version_of_the_package(): void
    {
        config(['filemanager.asset_version' => null]);

        $pretty = (string) InstalledVersions::getPrettyVersion('opensid/laravel-filemanager');
        $expected = str_starts_with($pretty, 'dev-')
            ? substr((string) InstalledVersions::getReference('opensid/laravel-filemanager'), 0, 8)
            : $pretty;

        $this->assertNotSame('', $expected);
        $this->assertSame($expected, filemanager_asset_version());
        $this->assertStringEndsWith('?v='.rawurlencode($expected), filemanager_asset('js/include.js'));
    }
}
