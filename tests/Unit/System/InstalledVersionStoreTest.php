<?php

namespace Tests\Unit\System;

use App\Support\System\InstalledVersionStore;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class InstalledVersionStoreTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function persisted_version_is_returned_when_present(): void
    {
        $store = app(InstalledVersionStore::class);
        $store->persist('0.1.4');

        $this->assertSame('0.1.4', $store->currentVersion());
    }

    #[Test]
    public function fallback_version_is_returned_when_no_persisted_version_exists(): void
    {
        config()->set('webblocks-updates.client.current_version', '0.1.5');

        $this->assertSame('0.1.5', app(InstalledVersionStore::class)->currentVersion());
    }
}
