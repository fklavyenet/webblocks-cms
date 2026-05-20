<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\WebBlocksCmsServiceProvider;

class ComposerPackageMetadataTest extends TestCase
{
    #[Test]
    public function root_composer_json_exposes_installable_package_metadata(): void
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('fklavyenet/webblocks-cms', $composer['name'] ?? null);
        $this->assertSame('library', $composer['type'] ?? null);
        $this->assertSame('packages/webblocks-cms/src/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\'] ?? null);
        $this->assertSame('packages/webblocks-cms/database/seeders/', $composer['autoload']['psr-4']['WebBlocks\\Cms\\Database\\Seeders\\'] ?? null);
        $this->assertContains(WebBlocksCmsServiceProvider::class, $composer['extra']['laravel']['providers'] ?? []);
    }

    #[Test]
    public function maintenance_repository_explicitly_loads_the_package_provider_locally(): void
    {
        $providers = require base_path('bootstrap/providers.php');

        $this->assertContains(WebBlocksCmsServiceProvider::class, $providers);
    }
}
