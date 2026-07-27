<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use WebBlocks\Cms\Support\System\Updates\AdminUpdateIndicator;
use WebBlocks\Cms\Support\WebBlocks;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The navbar badge is cached for an hour. The update controller clears it on a
 * successful run, but a request served between the apply and the worker
 * recycling still runs the pre-update code: it re-checks, still reports itself
 * as the old version, and re-caches the finished update for another hour.
 */
class AdminUpdateIndicatorGuardTest extends TestCase
{
    private function indicator(): AdminUpdateIndicator
    {
        return app(AdminUpdateIndicator::class);
    }

    public function test_a_cached_badge_for_the_installed_version_is_dropped_and_recomputed(): void
    {
        $installed = WebBlocks::version();

        Http::fake(['*' => Http::response(['data' => ['version' => $installed]])]);

        $indicator = $this->indicator();
        $indicator->storeVersionStatus([
            'state' => 'update_available',
            'label' => 'Update '.$installed.' available',
            'latest_version' => $installed,
            'update_available' => true,
        ]);

        $payload = $indicator->payload();

        $this->assertFalse($payload['visible']);
        // Replaced by a fresh check, not merely hidden for this one request.
        $this->assertNotSame('update_available', Cache::get(AdminUpdateIndicator::CACHE_KEY)['state'] ?? null);
    }

    public function test_the_guard_ignores_a_v_prefix(): void
    {
        $installed = WebBlocks::version();

        Http::fake(['*' => Http::response(['data' => ['version' => $installed]])]);

        $indicator = $this->indicator();
        $indicator->storeVersionStatus([
            'state' => 'update_available',
            'label' => 'Update available',
            'latest_version' => 'v'.$installed,
            'update_available' => true,
        ]);

        $this->assertFalse($indicator->payload()['visible']);
    }

    public function test_a_genuinely_newer_cached_badge_survives(): void
    {
        // No usable HTTP response: a kept cache entry must not re-check at all.
        Http::fake(['*' => Http::response('boom', 500)]);

        $indicator = $this->indicator();
        $indicator->storeVersionStatus([
            'state' => 'update_available',
            'label' => 'Update 999.0.0 available',
            'latest_version' => '999.0.0',
            'update_available' => true,
        ]);

        $payload = $indicator->payload();

        $this->assertTrue($payload['visible']);
        $this->assertSame('999.0.0', $payload['latest_version']);
    }
}
