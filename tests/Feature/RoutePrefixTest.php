<?php

namespace OpenSID\LaravelFilemanager\Tests\Feature;

use OpenSID\LaravelFilemanager\Tests\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Regression for #15: include.js and the crop handler in dialog.blade.php
 * used to call "/filemanager/..." literally, so a custom route_prefix (or
 * an app served from a sub-folder) broke every AJAX call in the dialog.
 */
class RoutePrefixTest extends TestCase
{
    public function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);

        // Must be set before the package's routes file is loaded.
        $app['config']->set('filemanager.route_prefix', 'admin-berkas');
    }

    #[Test]
    public function the_dialog_hands_include_js_urls_that_follow_the_configured_prefix(): void
    {
        $this->actingAsUser();
        $this->allowAllFilemanagerAbilities();

        $this->assertStringEndsWith('/admin-berkas/ajax', route('filemanager.ajax'));

        $response = $this->get(route('filemanager.dialog'))->assertOk();
        $html = $response->getContent();

        $this->assertMatchesRegularExpression('/var FILEMANAGER_ROUTES = (\{.*?\});/', $html);
        preg_match('/var FILEMANAGER_ROUTES = (\{.*?\});/', $html, $match);

        $this->assertSame([
            'ajax' => route('filemanager.ajax'),
            'execute' => route('filemanager.execute'),
            'upload' => route('filemanager.upload'),
        ], json_decode($match[1], true));
        $this->assertStringEndsWith('/admin-berkas/upload', route('filemanager.upload'));
        $this->assertStringContainsString("FILEMANAGER_ROUTES.execute + '?action=crop_image'", $html);
        $this->assertStringNotContainsString("'/filemanager/", $html);
        $this->assertStringNotContainsString('"/filemanager/', $html);
    }

    #[Test]
    public function include_js_has_no_hard_coded_filemanager_urls_left(): void
    {
        $js = file_get_contents(__DIR__.'/../../resources/dist/js/include.js');

        $this->assertStringNotContainsString('"/filemanager/', $js);
        $this->assertStringContainsString('FILEMANAGER_ROUTES.ajax', $js);
        $this->assertStringContainsString('FILEMANAGER_ROUTES.execute', $js);
        $this->assertStringContainsString('FILEMANAGER_ROUTES.upload', $js);
    }
}
