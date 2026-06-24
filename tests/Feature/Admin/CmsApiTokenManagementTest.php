<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;

class CmsApiTokenManagementTest extends TestCase
{
  use RefreshDatabase;

  #[Test]
  public function super_admin_can_view_token_management_ui(): void
  {
    $user = User::factory()->superAdmin()->create();
    $token = $this->createToken('secret-token', ['name' => 'Local AI - Test MacBook']);

    $response = $this->actingAs($user)->get(route('admin.system.api-tokens.index'));

    $response->assertOk();
    $response->assertSee('CMS API Tokens');
    $response->assertSee('Create Token');
    $response->assertSee('API Discovery Quick Start');
    $response->assertSee('GET /webadmin/api');
    $response->assertSee('Local AI - Test MacBook');
    $response->assertSee($token->token_preview);
    $response->assertSee('<td class="wb-table-actions">', false);
    $response->assertSee('<div class="wb-action-group">', false);
    $response->assertSee('title="Delete token"', false);
    $response->assertSee('aria-label="Delete token"', false);
    $response->assertSee('data-wb-target="#delete-cms-api-token-'.$token->id.'"', false);
    $response->assertSee('wb-icon wb-icon-trash', false);
    $response->assertSee('class="wb-modal wb-modal-lg"', false);
    $response->assertDontSee('confirm(');
    $response->assertDontSee($token->token_hash);
  }

  #[Test]
  public function non_super_admin_cannot_manage_tokens(): void
  {
    $user = User::factory()->siteAdmin()->create();
    $token = $this->createToken('secret-token');

    $this->actingAs($user)->get(route('admin.system.api-tokens.index'))->assertForbidden();

    $this->actingAs($user)
      ->post(route('admin.system.api-tokens.store'), ['name' => 'Local AI'])
      ->assertForbidden();

    $this->actingAs($user)
      ->delete(route('admin.system.api-tokens.destroy', $token))
      ->assertForbidden();
  }

  #[Test]
  public function schema_missing_state_shows_controlled_setup_guidance(): void
  {
    $user = User::factory()->superAdmin()->create();
    Schema::dropIfExists('cms_api_tokens');

    $response = $this->actingAs($user)->get(route('admin.system.api-tokens.index'));

    $response->assertOk();
    $response->assertSee('API token storage is not ready.');
    $response->assertSee('Run System Update again');
    $response->assertSee('disabled', false);
  }

  #[Test]
  public function token_creation_stores_only_hash_and_shows_plain_token_once(): void
  {
    $user = User::factory()->superAdmin()->create();

    $response = $this->followingRedirects()
      ->actingAs($user)
      ->post(route('admin.system.api-tokens.store'), ['name' => 'Local AI - Osman MacBook']);

    $response->assertOk();
    $response->assertSee('Copy this token now');
    $response->assertSee('WEBBLOCKS_CMS_API_TOKEN=wbcms_', false);
    $response->assertSee('How to use this token');
    $response->assertSee('GET /webadmin/api');
    $response->assertSee('OpenAPI');
    $response->assertSee('AI Guide');
    $response->assertSee('Use this WebBlocks CMS API base URL and token.');

    preg_match('/WEBBLOCKS_CMS_API_TOKEN=(wbcms_[A-Za-z0-9]+)/', $response->getContent(), $matches);
    $plainToken = $matches[1] ?? '';

    $this->assertStringStartsWith('wbcms_', $plainToken);
    $this->assertSame(70, strlen($plainToken));

    $record = CmsApiToken::query()->firstOrFail();

    $this->assertSame('Local AI - Osman MacBook', $record->name);
    $this->assertSame(hash('sha256', $plainToken), $record->token_hash);
    $this->assertNotSame($plainToken, $record->token_hash);
    $this->assertDatabaseMissing('cms_api_tokens', ['token_hash' => $plainToken]);

    $followUp = $this->actingAs($user)->get(route('admin.system.api-tokens.index'));

    $followUp->assertOk();
    $followUp->assertSee($record->token_preview);
    $followUp->assertDontSee($plainToken);
  }

  #[Test]
  public function token_list_never_exposes_the_full_token(): void
  {
    $user = User::factory()->superAdmin()->create();
    $plainToken = 'wbcms_'.str_repeat('a', 64);
    $token = $this->createToken($plainToken, ['name' => 'Local AI']);

    $response = $this->actingAs($user)->get(route('admin.system.api-tokens.index'));

    $response->assertOk();
    $response->assertSee($token->token_preview);
    $response->assertDontSee($plainToken);
  }

  #[Test]
  public function valid_token_updates_last_used_at_and_invalid_missing_or_revoked_tokens_return_json_401(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $plainToken = 'secret-token';
    $token = $this->createToken($plainToken);

    $this->getJson('/webadmin/api/sites')
      ->assertUnauthorized()
      ->assertJsonPath('code', 'invalid_internal_api_token');

    $this->withHeader('Authorization', 'Bearer wrong-token')
      ->getJson('/webadmin/api/sites')
      ->assertUnauthorized()
      ->assertJsonPath('code', 'invalid_internal_api_token');

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api/sites')
      ->assertOk()
      ->assertJsonPath('ok', true);

    $this->assertNotNull($token->fresh()->last_used_at);
    $this->assertNotNull($token->fresh()->last_used_user_agent);

    $token->forceFill(['revoked_at' => now()])->save();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api/sites')
      ->assertUnauthorized()
      ->assertJsonPath('code', 'invalid_internal_api_token');
  }

  #[Test]
  public function revoke_action_disables_api_access_and_keeps_audit_record_visible(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $user = User::factory()->superAdmin()->create();
    $plainToken = 'secret-token';
    $token = $this->createToken($plainToken, ['name' => 'Local AI']);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api/sites')
      ->assertOk();

    $this->actingAs($user)
      ->post(route('admin.system.api-tokens.revoke', $token))
      ->assertRedirect(route('admin.system.api-tokens.index'));

    $this->assertNotNull($token->fresh()->revoked_at);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api/sites')
      ->assertUnauthorized();

    $this->actingAs($user)
      ->get(route('admin.system.api-tokens.index'))
      ->assertOk()
      ->assertSee('Local AI')
      ->assertSee('Revoked');
  }

  #[Test]
  public function delete_action_removes_active_token_and_disables_api_access(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $user = User::factory()->superAdmin()->create();
    $plainToken = 'secret-token';
    $token = $this->createToken($plainToken, ['name' => 'Token To Delete']);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api/sites')
      ->assertOk();

    $this->actingAs($user)
      ->delete(route('admin.system.api-tokens.destroy', $token))
      ->assertRedirect(route('admin.system.api-tokens.index'));

    $this->assertDatabaseMissing('cms_api_tokens', ['id' => $token->id]);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api/sites')
      ->assertUnauthorized();

    $this->actingAs($user)
      ->get(route('admin.system.api-tokens.index'))
      ->assertOk()
      ->assertDontSee('Token To Delete');
  }

  #[Test]
  public function delete_action_removes_revoked_token_from_the_list(): void
  {
    $user = User::factory()->superAdmin()->create();
    $token = $this->createToken('secret-token', [
      'name' => 'Revoked Local AI',
      'revoked_at' => now(),
    ]);

    $this->actingAs($user)
      ->delete(route('admin.system.api-tokens.destroy', $token))
      ->assertRedirect(route('admin.system.api-tokens.index'));

    $this->assertDatabaseMissing('cms_api_tokens', ['id' => $token->id]);

    $this->actingAs($user)
      ->get(route('admin.system.api-tokens.index'))
      ->assertOk()
      ->assertDontSee('Revoked Local AI');
  }

  private function createToken(string $plainToken, array $attributes = []): CmsApiToken
  {
    return CmsApiToken::query()->create($attributes + [
      'name' => 'Test token',
      'token_hash' => app(CmsApiTokenIssuer::class)->hash($plainToken),
      'token_preview' => app(CmsApiTokenIssuer::class)->preview($plainToken),
    ]);
  }
}
