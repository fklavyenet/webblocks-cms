<?php

namespace Tests\Unit\System;

use WebBlocks\Cms\Support\System\InstalledVersionStore;
use WebBlocks\Cms\Support\WebBlocks;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

    #[Test]
    public function diagnostic_can_run_inside_an_existing_transaction(): void
    {
        DB::transaction(function (): void {
            $diagnostic = app(InstalledVersionStore::class)->diagnostic();

            $this->assertSame('pass', $diagnostic['status']);
            $this->assertSame('Installed version can be read and persisted in system settings.', $diagnostic['message']);
        });
    }
}
