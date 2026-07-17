<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalSiteController;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Tests\TestCase;

/**
 * The per-site custom head HTML endpoint: operator-authored raw markup (verification meta
 * tags, SEO, analytics) stored on the site and emitted into the public <head>. Written
 * through the internal content API under site-settings.write.
 */
class SiteCustomHeadApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  private function controller(): InternalSiteController
  {
    return app(InternalSiteController::class);
  }

  private function site(): Site
  {
    return Site::create(['name' => 'Play', 'handle' => 'play', 'is_primary' => true]);
  }

  #[Test]
  public function it_stores_custom_head_html_and_returns_it(): void
  {
    $site = $this->site();
    $tag = '<meta name="webads-site-verification" content="abc123">';

    $response = $this->controller()->updateCustomHead(
      Request::create('/', 'PATCH', ['custom_head_html' => $tag]),
      $site,
    );

    $this->assertSame(200, $response->getStatusCode());
    $payload = $response->getData(true);
    $this->assertTrue($payload['ok']);
    $this->assertSame($tag, $payload['site']['custom_head_html']);
    $this->assertSame('site_custom_head_html', $payload['writes'][0]['type']);
    $this->assertSame($tag, $site->fresh()->custom_head_html);
  }

  #[Test]
  public function an_empty_value_clears_the_custom_head_html(): void
  {
    $site = $this->site();
    $site->forceFill(['custom_head_html' => '<meta name="x" content="y">'])->save();

    $response = $this->controller()->updateCustomHead(
      Request::create('/', 'PATCH', ['custom_head_html' => '   ']),
      $site,
    );

    $this->assertSame(200, $response->getStatusCode());
    $this->assertNull($site->fresh()->custom_head_html);
  }

  #[Test]
  public function it_rejects_a_missing_field(): void
  {
    $site = $this->site();

    $response = $this->controller()->updateCustomHead(Request::create('/', 'PATCH', []), $site);

    $this->assertSame(422, $response->getStatusCode());
    $this->assertFalse($response->getData(true)['ok']);
  }

  #[Test]
  public function it_rejects_markup_over_the_size_cap(): void
  {
    $site = $this->site();

    $response = $this->controller()->updateCustomHead(
      Request::create('/', 'PATCH', ['custom_head_html' => str_repeat('a', 65001)]),
      $site,
    );

    $this->assertSame(422, $response->getStatusCode());
    $this->assertSame('custom_head_html', $response->getData(true)['errors'][0]['path']);
    $this->assertNull($site->fresh()->custom_head_html);
  }

  #[Test]
  public function the_public_layout_emits_the_custom_head_html_unescaped_before_head_close(): void
  {
    // Verification/analytics markup must be emitted verbatim: an escaped `{{ }}` echo would
    // turn the meta tag into inert text and silently break ownership verification. Guard the
    // raw echo and its placement inside <head>.
    $layout = file_get_contents(dirname(__DIR__, 2).'/resources/views/layouts/public.blade.php');

    $this->assertStringContainsString('{!! $customHeadHtml !!}', $layout);
    $this->assertStringNotContainsString('{{ $customHeadHtml }}', $layout);

    $headClose = strpos($layout, '</head>');
    $this->assertNotFalse($headClose);
    $this->assertLessThan($headClose, strpos($layout, '{!! $customHeadHtml !!}'));
  }

  #[Test]
  public function the_package_update_migration_adds_the_column_to_an_existing_install(): void
  {
    // Existing installs never run `database/migrations` (historical) or `fresh`; System Update
    // only runs `database/migrations/updates`. A column that ships without an ensure-migration
    // there reaches consumer installs as code-without-schema, which is exactly how the endpoint
    // 500'd/422'd on the live site. Prove the upgrade path, not just the fresh schema.
    Schema::table('wbcms_sites', function (Blueprint $table): void {
      $table->dropColumn('custom_head_html');
    });
    $this->assertFalse(Schema::hasColumn('wbcms_sites', 'custom_head_html'));

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_07_13_120000_ensure_site_custom_head_html.php';
    $migration->up();

    $this->assertTrue(Schema::hasColumn('wbcms_sites', 'custom_head_html'));

    // Idempotent: re-running is a no-op rather than a duplicate-column error.
    $migration->up();
    $this->assertTrue(Schema::hasColumn('wbcms_sites', 'custom_head_html'));
  }
}
