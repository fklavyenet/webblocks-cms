<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\FoundationSiteLocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use WebBlocks\Cms\Models\CmsApiToken;
use WebBlocks\Cms\Models\CmsApiTokenActivityLog;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenCapabilities;
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
    $response->assertSee('placeholder="Example: Local AI, Homepage Builder, Operator Tool"', false);
    $response->assertDontSee('Local AI - Osman MacBook');
    $response->assertSee('Choose what this token is allowed to do.');
    $response->assertSee('Advanced capabilities');
    $response->assertSee('Grant only to trusted operator tools.');
    $response->assertSee('content.read');
    $response->assertSee('content.validate');
    $response->assertSee('content.apply');
    $response->assertSee('navigation.write');
    $response->assertSee('shared-slots.write');
    $response->assertSee('media.read');
    $response->assertSee('site-assets.read');
    $response->assertSee('site-assets.write');
    $response->assertSee('media.write');
    $response->assertSee('content.publish');
    $response->assertSee('pages.delete');
    $response->assertSee('Local AI - Test MacBook');
    $response->assertSee('content.read, content.validate, content.apply +4');
    $response->assertSee($token->token_preview);
    $response->assertSee('<td class="wb-table-actions">', false);
    $response->assertSee('<div class="wb-action-group">', false);
    $response->assertSee('title="Edit token"', false);
    $response->assertSee('aria-label="Edit token"', false);
    $response->assertSee('data-wb-target="#edit-cms-api-token-'.$token->id.'"', false);
    $response->assertSee('wb-icon wb-icon-pencil', false);
    $response->assertSee('title="View API activity"', false);
    $response->assertSee('aria-label="View API activity"', false);
    $response->assertSee('data-wb-target="#activity-cms-api-token-'.$token->id.'"', false);
    $response->assertSee('wb-icon wb-icon-history', false);
    $response->assertSee('Recent API Activity');
    $response->assertSee('Edit API Token');
    $response->assertSee('value="Local AI - Test MacBook"', false);
    $response->assertSee('title="Delete token"', false);
    $response->assertSee('aria-label="Delete token"', false);
    $response->assertSee('data-wb-target="#delete-cms-api-token-'.$token->id.'"', false);
    $response->assertSee('wb-icon wb-icon-trash', false);
    $response->assertSee('class="wb-modal wb-modal-lg"', false);
    $response->assertDontSee('confirm(');
    $response->assertDontSee($token->token_hash);

    $content = $response->getContent();

    $this->assertStringContainsString('id="api_token_name" name="name" type="text" class="wb-input" value="" placeholder="Example: Local AI, Homepage Builder, Operator Tool"', $content);
    $this->assertMatchesRegularExpression('/value="content\.read"[^>]*checked/s', $content);
    $this->assertMatchesRegularExpression('/value="content\.validate"[^>]*checked/s', $content);
    $this->assertMatchesRegularExpression('/value="content\.apply"[^>]*checked/s', $content);
    $this->assertMatchesRegularExpression('/value="navigation\.write"[^>]*checked/s', $content);
    $this->assertMatchesRegularExpression('/value="shared-slots\.write"[^>]*checked/s', $content);
    $this->assertMatchesRegularExpression('/value="media\.read"[^>]*checked/s', $content);
    $this->assertDoesNotMatchRegularExpression('/value="site-assets\.read"[^>]*checked/s', $content);
    $this->assertDoesNotMatchRegularExpression('/value="site-assets\.write"[^>]*checked/s', $content);
    $this->assertDoesNotMatchRegularExpression('/value="media\.write"[^>]*checked/s', $content);
    $this->assertDoesNotMatchRegularExpression('/value="content\.publish"[^>]*checked/s', $content);
    $this->assertDoesNotMatchRegularExpression('/value="pages\.delete"[^>]*checked/s', $content);
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
      ->put(route('admin.system.api-tokens.update', $token), [
        'name' => 'Blocked Update',
        'capabilities' => [CmsApiTokenCapabilities::CONTENT_READ],
      ])
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
    $capabilities = [
      CmsApiTokenCapabilities::CONTENT_READ,
      CmsApiTokenCapabilities::CONTENT_VALIDATE,
      CmsApiTokenCapabilities::CONTENT_APPLY,
    ];

    $response = $this->followingRedirects()
      ->actingAs($user)
      ->post(route('admin.system.api-tokens.store'), [
        'name' => 'Homepage Builder',
        'capabilities' => $capabilities,
      ]);

    $response->assertOk();
    $response->assertSee('Copy this token now');
    $response->assertSee('WEBBLOCKS_CMS_API_URL=https://webblocks-cms.test/webadmin/api', false);
    $response->assertSee('WEBBLOCKS_CMS_API_TOKEN=wbcms_', false);
    $response->assertDontSee('WEBBLOCKS_CMS_URL=https://webblocks-cms.test', false);
    $response->assertSee('How to use this token');
    $response->assertSee('<code>https://webblocks-cms.test/webadmin/api</code>', false);
    $response->assertSee('GET /webadmin/api');
    $response->assertSee('OpenAPI');
    $response->assertSee('AI Guide');
    $response->assertSee('Use this WebBlocks CMS API base URL and token.');

    preg_match('/WEBBLOCKS_CMS_API_TOKEN=(wbcms_[A-Za-z0-9]+)/', $response->getContent(), $matches);
    $plainToken = $matches[1] ?? '';

    $this->assertStringStartsWith('wbcms_', $plainToken);
    $this->assertSame(70, strlen($plainToken));

    $record = CmsApiToken::query()->firstOrFail();

    $this->assertSame('Homepage Builder', $record->name);
    $this->assertSame($capabilities, $record->capabilities);
    $this->assertSame(hash('sha256', $plainToken), $record->token_hash);
    $this->assertNotSame($plainToken, $record->token_hash);
    $this->assertDatabaseMissing('cms_api_tokens', ['token_hash' => $plainToken]);

    $followUp = $this->actingAs($user)->get(route('admin.system.api-tokens.index'));

    $followUp->assertOk();
    $followUp->assertSee($record->token_preview);
    $followUp->assertSee('content.read, content.validate, content.apply');
    $followUp->assertDontSee($plainToken);
  }

  #[Test]
  public function token_creation_rejects_unknown_or_empty_capabilities(): void
  {
    $user = User::factory()->superAdmin()->create();

    $this->actingAs($user)
      ->from(route('admin.system.api-tokens.index'))
      ->post(route('admin.system.api-tokens.store'), [
        'name' => 'Operator Tool',
        'capabilities' => [
          CmsApiTokenCapabilities::CONTENT_READ,
          'unknown.capability',
        ],
      ])
      ->assertRedirect(route('admin.system.api-tokens.index'))
      ->assertSessionHasErrors('capabilities.1');

    $this->actingAs($user)
      ->from(route('admin.system.api-tokens.index'))
      ->post(route('admin.system.api-tokens.store'), [
        'name' => 'Operator Tool',
        'capabilities' => [],
      ])
      ->assertRedirect(route('admin.system.api-tokens.index'))
      ->assertSessionHasErrors('capabilities');

    $this->assertDatabaseCount('cms_api_tokens', 0);
  }

  #[Test]
  public function created_token_discovery_reports_saved_capabilities(): void
  {
    $user = User::factory()->superAdmin()->create();
    $capabilities = [
      CmsApiTokenCapabilities::CONTENT_READ,
      CmsApiTokenCapabilities::CONTENT_VALIDATE,
      CmsApiTokenCapabilities::MEDIA_WRITE,
      CmsApiTokenCapabilities::CONTENT_PUBLISH,
    ];

    $response = $this->actingAs($user)
      ->post(route('admin.system.api-tokens.store'), [
        'name' => 'Publisher Tool',
        'capabilities' => $capabilities,
      ]);

    $plainToken = (string) $response->getSession()->get('created_cms_api_token');

    $this->assertStringStartsWith('wbcms_', $plainToken);
    $this->assertSame($capabilities, CmsApiToken::query()->firstOrFail()->capabilities);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api')
      ->assertOk()
      ->assertJsonPath('authenticated', true)
      ->assertJsonPath('token.capabilities', $capabilities)
      ->assertJsonPath('token.destructive_capabilities', [CmsApiTokenCapabilities::CONTENT_PUBLISH])
      ->assertJsonPath('token.can.write_media_metadata', true)
      ->assertJsonMissingPath('token.token_hash')
      ->assertJsonMissingPath('token.token_preview');
  }

  #[Test]
  public function active_token_name_and_capabilities_can_be_edited_without_rotating_token_or_preview(): void
  {
    $user = User::factory()->superAdmin()->create();
    $plainToken = 'secret-token';
    $token = $this->createToken($plainToken, [
      'name' => 'Old Builder',
      'capabilities' => [
        CmsApiTokenCapabilities::CONTENT_READ,
        CmsApiTokenCapabilities::CONTENT_VALIDATE,
        CmsApiTokenCapabilities::CONTENT_APPLY,
      ],
    ]);
    $originalHash = $token->token_hash;
    $originalPreview = $token->token_preview;
    $updatedCapabilities = [
      CmsApiTokenCapabilities::CONTENT_READ,
      CmsApiTokenCapabilities::CONTENT_PUBLISH,
    ];

    $this->actingAs($user)
      ->put(route('admin.system.api-tokens.update', $token), [
        'name' => 'Publisher Operator',
        'capabilities' => $updatedCapabilities,
      ])
      ->assertRedirect(route('admin.system.api-tokens.index'));

    $token->refresh();

    $this->assertSame('Publisher Operator', $token->name);
    $this->assertSame($updatedCapabilities, $token->capabilities);
    $this->assertNull($token->revoked_at);
    $this->assertSame($originalHash, $token->token_hash);
    $this->assertSame($originalPreview, $token->token_preview);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api')
      ->assertOk()
      ->assertJsonPath('token.capabilities', $updatedCapabilities)
      ->assertJsonPath('token.destructive_capabilities', [CmsApiTokenCapabilities::CONTENT_PUBLISH])
      ->assertJsonMissingPath('token.token_hash')
      ->assertJsonMissingPath('token.token_preview');
  }

  #[Test]
  public function revoked_token_can_be_edited_without_becoming_active(): void
  {
    $user = User::factory()->superAdmin()->create();
    $revokedAt = now()->subMinute();
    $token = $this->createToken('secret-token', [
      'name' => 'Revoked Builder',
      'capabilities' => [CmsApiTokenCapabilities::CONTENT_READ],
      'revoked_at' => $revokedAt,
    ]);
    $originalHash = $token->token_hash;
    $originalPreview = $token->token_preview;
    $updatedCapabilities = [
      CmsApiTokenCapabilities::CONTENT_READ,
      CmsApiTokenCapabilities::CONTENT_VALIDATE,
    ];

    $this->actingAs($user)
      ->put(route('admin.system.api-tokens.update', $token), [
        'name' => 'Revoked Builder Updated',
        'capabilities' => $updatedCapabilities,
      ])
      ->assertRedirect(route('admin.system.api-tokens.index'));

    $token->refresh();

    $this->assertSame('Revoked Builder Updated', $token->name);
    $this->assertSame($updatedCapabilities, $token->capabilities);
    $this->assertTrue($token->isRevoked());
    $this->assertSame($revokedAt->timestamp, $token->revoked_at?->timestamp);
    $this->assertSame($originalHash, $token->token_hash);
    $this->assertSame($originalPreview, $token->token_preview);
  }

  #[Test]
  public function token_edit_rejects_unknown_or_empty_capabilities_without_changing_secret_fields(): void
  {
    $user = User::factory()->superAdmin()->create();
    $token = $this->createToken('secret-token', [
      'name' => 'Editable Token',
      'capabilities' => [CmsApiTokenCapabilities::CONTENT_READ],
    ]);
    $originalHash = $token->token_hash;
    $originalPreview = $token->token_preview;

    $this->actingAs($user)
      ->from(route('admin.system.api-tokens.index'))
      ->put(route('admin.system.api-tokens.update', $token), [
        'name' => 'Bad Capability',
        'capabilities' => [
          CmsApiTokenCapabilities::CONTENT_READ,
          'unknown.capability',
        ],
      ])
      ->assertRedirect(route('admin.system.api-tokens.index'))
      ->assertSessionHasErrors('capabilities.1');

    $this->actingAs($user)
      ->from(route('admin.system.api-tokens.index'))
      ->put(route('admin.system.api-tokens.update', $token), [
        'name' => 'No Capability',
        'capabilities' => [],
      ])
      ->assertRedirect(route('admin.system.api-tokens.index'))
      ->assertSessionHasErrors('capabilities');

    $token->refresh();

    $this->assertSame('Editable Token', $token->name);
    $this->assertSame([CmsApiTokenCapabilities::CONTENT_READ], $token->capabilities);
    $this->assertSame($originalHash, $token->token_hash);
    $this->assertSame($originalPreview, $token->token_preview);
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
    $this->assertDatabaseHas('cms_api_token_activity_logs', [
      'cms_api_token_id' => $token->id,
      'status' => 'authenticated',
      'method' => 'GET',
      'path' => '/webadmin/api/sites',
    ]);

    $token->forceFill(['revoked_at' => now()])->save();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->getJson('/webadmin/api/sites')
      ->assertUnauthorized()
      ->assertJsonPath('code', 'invalid_internal_api_token');
  }

  #[Test]
  public function token_activity_modal_lists_recent_api_usage_without_exposing_secret_values(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $user = User::factory()->superAdmin()->create();
    $plainToken = 'secret-token';
    $token = $this->createToken($plainToken, ['name' => 'Activity Token']);
    $media = Media::query()->create([
      'disk' => 'public',
      'path' => 'media/cms-logo.png',
      'filename' => 'cms-logo.png',
      'original_name' => 'cms-logo.png',
      'extension' => 'png',
      'mime_type' => 'image/png',
      'size' => 1024,
      'kind' => Media::KIND_IMAGE,
      'visibility' => 'public',
      'title' => 'CMS logo',
    ]);

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->withHeader('User-Agent', 'WebBlocks Test Agent')
      ->getJson('/webadmin/api/sites')
      ->assertOk();

    $this->withHeader('Authorization', 'Bearer '.$plainToken)
      ->patchJson('/webadmin/api/media/'.$media->id, ['title' => 'Blocked'])
      ->assertForbidden()
      ->assertJsonPath('required_capability', CmsApiTokenCapabilities::MEDIA_WRITE);

    $response = $this->actingAs($user)->get(route('admin.system.api-tokens.index'));

    $response->assertOk();
    $response->assertSee('Recent API Activity');
    $response->assertSee('Latest 10 requests for Activity Token');
    $response->assertSee('GET /webadmin/api/sites');
    $response->assertSee('PATCH /webadmin/api/media/'.$media->id);
    $response->assertSee('Authenticated');
    $response->assertSee('Denied');
    $response->assertSee(CmsApiTokenCapabilities::MEDIA_WRITE);
    $response->assertSee('WebBlocks Test Agent');
    $response->assertDontSee($plainToken);
    $response->assertDontSee($token->token_hash);
  }

  #[Test]
  public function token_activity_logs_keep_only_the_latest_ten_records_per_token(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $plainToken = 'secret-token';
    $token = $this->createToken($plainToken);

    for ($index = 1; $index <= 12; $index++) {
      $this->withHeader('Authorization', 'Bearer '.$plainToken)
        ->getJson('/webadmin/api/sites')
        ->assertOk();
    }

    $logs = CmsApiTokenActivityLog::query()
      ->where('cms_api_token_id', $token->id)
      ->latest('occurred_at')
      ->latest('id')
      ->get();

    $this->assertCount(10, $logs);
    $this->assertSame('/webadmin/api/sites', $logs->first()->path);
    $this->assertDatabaseMissing('cms_api_token_activity_logs', ['id' => 1]);
    $this->assertDatabaseMissing('cms_api_token_activity_logs', ['id' => 2]);
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
