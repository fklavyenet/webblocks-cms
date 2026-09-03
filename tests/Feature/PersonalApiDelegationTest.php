<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
use WebBlocks\Cms\Tests\TestCase;

class PersonalApiDelegationTest extends TestCase
{
  #[Test]
  public function update_migration_adds_personal_delegation_fields_idempotently(): void
  {
    Schema::create('users', function (Blueprint $table): void {
      $table->id();
    });
    Schema::create('wbcms_cms_api_tokens', function (Blueprint $table): void {
      $table->id();
      $table->string('token_hash');
    });

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_09_03_120000_add_personal_delegation_to_cms_api_tokens.php';
    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasColumns('wbcms_cms_api_tokens', [
      'token_type', 'allowed_site_ids', 'expires_at',
    ]));
  }

  #[Test]
  public function network_controls_migration_is_idempotent(): void
  {
    Schema::create('wbcms_cms_api_tokens', function (Blueprint $table): void {
      $table->id();
    });

    $migration = require dirname(__DIR__, 2).'/database/migrations/updates/2026_09_03_170000_add_personal_token_network_controls.php';
    $migration->up();
    $migration->up();

    $this->assertTrue(Schema::hasColumns('wbcms_cms_api_tokens', ['allowed_ip_ranges', 'requests_per_minute']));
  }

  #[Test]
  public function personal_policy_keeps_system_authority_out_of_user_tokens(): void
  {
    $source = file_get_contents(dirname(__DIR__, 2).'/src/Support/InternalApiTokens/PersonalApiTokenPolicy.php');

    foreach ([
      CmsApiTokenCapabilities::BACKUPS_CREATE,
      CmsApiTokenCapabilities::MAINTENANCE_DELETE,
      CmsApiTokenCapabilities::PLUGINS_INSTALL,
      CmsApiTokenCapabilities::APPLICATIONS_WRITE,
      CmsApiTokenCapabilities::DOMAINS_WRITE,
      CmsApiTokenCapabilities::SITE_ASSETS_WRITE,
    ] as $capability) {
      $this->assertStringContainsString('CmsApiTokenCapabilities::'.$this->constantName($capability), $source);
    }
  }

  #[Test]
  public function internal_api_authentication_runs_live_delegation_checks(): void
  {
    $middleware = file_get_contents(dirname(__DIR__, 2).'/src/Http/Middleware/RequireInternalApiToken.php');
    $delegation = file_get_contents(dirname(__DIR__, 2).'/src/Http/Middleware/AuthorizePersonalApiDelegation.php');

    $this->assertStringContainsString('$this->delegation->handle($request, $next)', $middleware);
    $this->assertStringContainsString('intersect($user->accessibleSiteIds()', $delegation);
    $this->assertStringContainsString('Auth::setUser($user)', $delegation);
    $this->assertStringContainsString('delegated_site_access_denied', $delegation);
  }

  #[Test]
  public function personal_delegation_closes_global_workflow_and_aggregate_fallbacks(): void
  {
    $root = dirname(__DIR__, 2);
    $delegation = file_get_contents($root.'/src/Http/Middleware/AuthorizePersonalApiDelegation.php');
    $engagement = file_get_contents($root.'/src/Http/Controllers/InternalContentApi/InternalEngagementController.php');
    $resources = file_get_contents($root.'/src/Http/Controllers/InternalContentApi/InternalContentResourceController.php');

    $this->assertStringContainsString('internal-content-api.locales.store', $delegation);
    $this->assertStringContainsString('internal-content-api.locales.update', $delegation);
    $this->assertStringContainsString('internal-content-api.navigation-menus.show', $delegation);
    $this->assertStringContainsString('PageWorkflowManager $workflow', $delegation);
    $this->assertStringContainsString('delegated_workflow_access_denied', $delegation);
    $this->assertStringContainsString('delegated_network_access_denied', $delegation);
    $this->assertStringContainsString('personal_api_token_rate_limit_exceeded', $delegation);
    $this->assertGreaterThanOrEqual(5, substr_count($engagement, 'cms_api_allowed_site_ids'));
    $this->assertStringContainsString("'approved_by_user_id' => \$request->user()?->id", $engagement);
    $this->assertStringContainsString('scopeMediaForUser($mediaQuery, $user)', $resources);
  }

  private function constantName(string $capability): string
  {
    return strtoupper(str_replace(['.', '-'], '_', $capability));
  }
}
