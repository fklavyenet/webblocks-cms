<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use ReflectionMethod;
use WebBlocks\Cms\Http\Controllers\Admin\CmsApiTokenController;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

class EmbeddedApplicationUpdateMigrationTest extends TestCase
{
  #[Test]
  public function system_update_creates_the_embedded_applications_table_idempotently(): void
  {
    Schema::create('users', function (Blueprint $table): void {
      $table->id();
    });

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_08_19_170000_ensure_embedded_applications_table.php';
    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasTable('wbcms_embedded_applications'));
    $this->assertTrue(Schema::hasColumns('wbcms_embedded_applications', [
      'handle', 'name', 'render_mode', 'entry_url', 'css_assets', 'js_assets', 'settings_schema', 'is_enabled',
    ]));
  }

  #[Test]
  public function api_token_editor_groups_every_embedded_application_capability(): void
  {
    $method = new ReflectionMethod(CmsApiTokenController::class, 'capabilityGroups');
    $groups = $method->invoke(app(CmsApiTokenController::class));
    $capabilities = collect($groups)->pluck('capabilities')->flatten()->all();

    $this->assertContains(CmsApiTokenCapabilities::APPLICATIONS_READ, $capabilities);
    $this->assertContains(CmsApiTokenCapabilities::APPLICATIONS_WRITE, $capabilities);
    $this->assertContains(CmsApiTokenCapabilities::APPLICATIONS_DELETE, $capabilities);
  }
}
