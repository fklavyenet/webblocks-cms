<?php

namespace WebBlocks\Cms\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class EmbeddedApplicationSettingsEditorStructureTest extends TestCase
{
    public function test_settings_use_a_table_modal_and_page_scoped_asset(): void
    {
        $root = dirname(__DIR__, 2);
        $view = file_get_contents($root.'/resources/views/admin/embedded-applications/form.blade.php');
        $script = file_get_contents($root.'/public/cms/js/admin/embedded-application-settings.js');

        $this->assertStringContainsString('data-wb-application-settings', $view);
        $this->assertStringContainsString('data-wb-setting-add', $view);
        $this->assertStringContainsString('id="embedded-application-setting-modal"', $view);
        $this->assertStringContainsString("@push('overlays')", $view);
        $this->assertStringNotContainsString("@push('modals')", $view);
        $this->assertStringContainsString('data-wb-setting-edit', $view);
        $this->assertStringContainsString('data-wb-setting-delete', $view);
        $this->assertStringNotContainsString('array_pad($settings', $view);
        $this->assertStringContainsString("'settings[' + index + '][' + field + ']'", $script);
        $this->assertContains('cms/js/admin/embedded-application-settings.js', WebBlocksCmsServiceProvider::PACKAGE_PUBLIC_ASSET_FILES);
        $this->assertContains('cms/js/admin/embedded-application-settings.js', WebBlocksCmsServiceProvider::ROOT_PUBLIC_ASSET_COMPATIBILITY_FILES);
    }
}
