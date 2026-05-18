<?php

namespace Tests\Feature;

use App\Support\WebBlocks;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class PackageServiceProviderBootstrapTest extends TestCase
{
    #[Test]
    public function discovered_package_provider_loads_without_changing_current_root_runtime_behavior(): void
    {
        $this->assertTrue(class_exists(WebBlocksCmsServiceProvider::class));
        $this->assertTrue($this->app->providerIsLoaded(WebBlocksCmsServiceProvider::class));

        $router = $this->app['router'];

        $this->assertNull($router->getRoutes()->getByName('webblocks-cms.health'));
        $this->assertFalse(view()->exists(WebBlocksCmsServiceProvider::VIEW_NAMESPACE.'::health'));
        $this->assertSame([], config('webblocks-cms', []));
    }

    #[Test]
    public function package_default_update_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/webblocks-updates.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame('https://updates.webblocksui.com', config('webblocks-updates.server_url'));
        $this->assertSame('stable', config('webblocks-updates.channel'));
        $this->assertSame('1', config('webblocks-updates.api_version'));
        $this->assertSame(WebBlocks::HANDLE, config('webblocks-updates.product'));
        $this->assertSame(WebBlocks::VERSION, config('webblocks-updates.current_version'));
    }

    #[Test]
    public function package_default_contact_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/contact.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame(3, config('contact.minimum_submit_seconds'));
        $this->assertSame(5, config('contact.rate_limit_per_minute'));
        $this->assertSame(
            'Thanks for your message. We will get back to you soon.',
            config('contact.success_message')
        );
    }

    #[Test]
    public function package_default_demo_media_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/demo_media.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertCount(9, config('demo_media.items', []));
        $this->assertSame('home-hero-01', config('demo_media.items.0.key'));
        $this->assertSame('gallery-04', config('demo_media.items.8.key'));
    }

    #[Test]
    public function package_default_cms_config_is_available_under_the_existing_config_key(): void
    {
        $packageConfigPath = base_path('packages/webblocks-cms/config/cms.php');

        $this->assertFileExists($packageConfigPath);
        $this->assertSame('DISABLED', config('cms.install.git_protection.disabled_push_url'));
        $this->assertSame(15, config('cms.install.git_protection.timeout_seconds'));
        $this->assertSame('auto', config('cms.backup.execution'));
        $this->assertSame('webblocks_visitor_consent', config('cms.visitor_reports.consent_cookie_name'));
        $this->assertContains('googleother', config('cms.visitor_reports.ignored_user_agents', []));
    }

    #[Test]
    public function root_update_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('webblocks-updates.php'));

        config()->set('webblocks-updates.server_url', 'https://override.example.test');
        config()->set('webblocks-updates.installer.lock_name', 'custom-system-updates-lock');

        $this->assertSame('https://override.example.test', config('webblocks-updates.server_url'));
        $this->assertSame('custom-system-updates-lock', config('webblocks-updates.installer.lock_name'));
        $this->assertSame(WebBlocks::VERSION, config('webblocks-updates.current_version'));
    }

    #[Test]
    public function root_contact_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('contact.php'));

        config()->set('contact.rate_limit_per_minute', 9);
        config()->set('contact.success_message', 'Custom success message');

        $this->assertSame(9, config('contact.rate_limit_per_minute'));
        $this->assertSame('Custom success message', config('contact.success_message'));
    }

    #[Test]
    public function root_demo_media_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('demo_media.php'));

        config()->set('demo_media.items', [
            [
                'key' => 'custom-demo-item',
                'topic' => 'custom',
                'title' => 'Custom demo item',
                'folder' => 'Custom',
                'source_url' => 'https://example.test/custom-demo-item.jpg',
                'alt' => 'Custom demo item',
            ],
        ]);

        $this->assertCount(1, config('demo_media.items', []));
        $this->assertSame('custom-demo-item', config('demo_media.items.0.key'));
    }

    #[Test]
    public function root_cms_config_remains_the_install_override_after_package_merge(): void
    {
        $this->assertFileExists(config_path('cms.php'));

        config()->set('cms.backup.execution', 'mysqldump');
        config()->set('cms.visitor_reports.enabled', false);
        config()->set('cms.install.git_protection.disabled_push_url', 'NO-PUSH');

        $this->assertSame('mysqldump', config('cms.backup.execution'));
        $this->assertFalse(config('cms.visitor_reports.enabled'));
        $this->assertSame('NO-PUSH', config('cms.install.git_protection.disabled_push_url'));
    }
}
