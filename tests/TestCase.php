<?php

namespace OpenSID\LaravelFilemanager\Tests;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Orchestra\Testbench\TestCase as Orchestra;
use OpenSID\LaravelFilemanager\LaravelFilemanagerServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            LaravelFilemanagerServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app): void
    {
        $app['config']->set('app.key', 'base64:' . base64_encode(random_bytes(32)));

        $app['config']->set('filesystems.disks.filemanager', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/filemanager'),
        ]);

        $app['config']->set('filesystems.disks.filemanager_thumbs', [
            'driver' => 'local',
            'root' => storage_path('framework/testing/disks/filemanager_thumbs'),
        ]);

        $app['config']->set('filemanager.disk', 'filemanager');
        $app['config']->set('filemanager.thumbs_disk', 'filemanager_thumbs');
    }

    /**
     * Logs in a bare Authenticatable stub — this package's own test suite
     * doesn't ship (or need) a real Eloquent User model/migrations, since
     * the package itself never queries the users table.
     */
    protected function actingAsUser(): Authenticatable
    {
        $user = new class implements Authenticatable
        {
            public $id = 1;

            public function getAuthIdentifierName()
            {
                return 'id';
            }

            public function getAuthIdentifier()
            {
                return $this->id;
            }

            public function getAuthPasswordName()
            {
                return 'password';
            }

            public function getAuthPassword()
            {
                return 'secret';
            }

            public function getRememberToken()
            {
                return null;
            }

            public function setRememberToken($value) {}

            public function getRememberTokenName()
            {
                return '';
            }
        };

        $this->actingAs($user);

        return $user;
    }

    /**
     * Overrides this package's config-driven default Gates (which fall
     * back to deny when the host's can() helper isn't present — as is the
     * case in this isolated testbench environment) with an always-allow
     * stand-in, for tests that are about the HTTP/routing layer rather
     * than about permission wiring itself.
     */
    protected function allowAllFilemanagerAbilities(): void
    {
        Gate::define('filemanager.access', fn () => true);
        Gate::define('filemanager.upload', fn () => true);
        Gate::define('filemanager.delete', fn () => true);
    }
}
