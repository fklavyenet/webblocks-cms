<?php

namespace Tests\Unit\System;

use App\Support\WebBlocks;
use App\Support\System\InstalledVersionStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstalledVersionStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function current_version_comes_from_webblocks_source_of_truth_even_when_a_value_is_persisted(): void
    {
        $store = app(InstalledVersionStore::class);
        $store->persist('0.1.4');

        $this->assertSame(WebBlocks::version(), $store->currentVersion());
    }

    #[Test]
    public function fallback_version_matches_webblocks_source_of_truth(): void
    {
        config()->set('app.version', '0.1.8');
        config()->set('webblocks-updates.current_version', '0.1.8');

        $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->currentVersion());
    }

    #[Test]
    public function display_version_matches_webblocks_source_of_truth(): void
    {
        $this->assertSame(WebBlocks::version(), app(InstalledVersionStore::class)->displayVersion());
    }
}
