<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalContentApi\InternalContentPlanService;
use WebBlocks\Cms\Support\System\SystemSettings;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class RemoteMcpTest extends TestCase
{
  private const ORIGIN = 'https://cms.example.test';

  private const MCP = self::ORIGIN.'/webadmin/mcp';

  private const REDIRECT = 'https://client.example.test/oauth/callback';

  private ?string $defaultClient = null;

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('app.maintenance', ['driver' => 'cache', 'store' => 'array']);
    $app['config']->set('app.url', self::ORIGIN);
  }

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(CmsSqlTestUser::class, User::class);
    }
    Auth::provider('mcp-test-users', fn ($app) => new class($app['hash'], User::class) extends EloquentUserProvider
    {
      public function createModel()
      {
        return parent::createModel()->setTable('users');
      }
    });
    config(['auth.providers.users.driver' => 'mcp-test-users', 'auth.providers.users.model' => User::class]);
    $this->setMcp(true);
  }

  public function test_discovery_is_opt_in_and_advertises_pkce_and_the_three_tools(): void
  {
    $this->get(self::ORIGIN.'/.well-known/oauth-protected-resource/webadmin/mcp')
      ->assertOk()->assertJsonPath('resource', self::MCP);
    $this->get(self::ORIGIN.'/.well-known/oauth-authorization-server/webadmin/mcp')
      ->assertOk()->assertJsonPath('issuer', self::MCP)->assertJsonPath('registration_endpoint', self::MCP.'/register')
      ->assertJsonPath('code_challenge_methods_supported', ['S256']);
    $this->rpc('initialize', ['protocolVersion' => '2025-03-26'])->assertJsonPath('result.protocolVersion', '2025-03-26');
    $this->rpc('tools/list')->assertJsonCount(3, 'result.tools')
      ->assertJsonPath('result.tools.1.annotations.readOnlyHint', false);
    $this->tool('find_page', ['path' => '/', 'locale' => 'en'], status: 401)
      ->assertHeader('WWW-Authenticate')->assertJsonPath('result.structuredContent.code', 'auth_required')
      ->assertJsonPath('result._meta.mcp/www_authenticate.0', fn ($header) => str_contains($header, 'resource_metadata='));
    $this->get(self::MCP, ['Origin' => 'https://untrusted.example.test'])->assertForbidden();
    $this->get(self::MCP)->assertStatus(405);
    $this->postJson('http://cms.example.test/webadmin/mcp', [])->assertNotFound();
    $this->setMcp(false);
    $this->postJson(self::MCP, [])->assertNotFound();
  }

  public function test_oauth_binds_codes_to_pkce_callback_resource_and_one_site(): void
  {
    [$page] = $this->pageFixture();
    $other = Site::create(['handle' => 'other', 'name' => 'Other']);
    $otherPage = Page::create(['site_id' => $other->id, 'title' => 'Other', 'slug' => 'other', 'status' => 'published']);
    $user = $this->operator();
    $auth = $this->authorizationRequest();
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertRedirect();
    $this->actingAs($user, 'web');
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()->assertSee('Choose a site')
      ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self' https://client.example.test; base-uri 'none'");
    $this->post(self::MCP.'/authorize', array_replace($auth, ['redirect_uri' => self::REDIRECT.'/unexpected', 'site_id' => $page->site_id, 'decision' => 'allow']))
      ->assertStatus(400);
    $code = $this->consent($page->site_id, $auth);
    $this->exchange($code, ['code_verifier' => str_repeat('z', 43)])->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, ['resource' => self::MCP.'/other'])->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, ['client_secret' => 'unexpected'])->assertBadRequest()->assertJsonPath('error', 'invalid_client');
    $token = $this->exchange($code)->assertOk()->json('access_token');
    $this->assertDatabaseMissing('wbcms_mcp_grants', ['token_hash' => $token]);
    $this->assertDatabaseHas('wbcms_mcp_grants', ['token_hash' => hash('sha256', $token), 'code_hash' => null]);
    $this->exchange($code)->assertJsonPath('error', 'invalid_grant');
    $this->tool('find_page', ['page_id' => $otherPage->id, 'locale' => 'en'], $token)
      ->assertJsonPath('result.structuredContent.code', 'page_missing');
    $this->tool('update_draft_heading', [], $token, 403)->assertJsonPath('result.structuredContent.code', 'auth_required');
    $this->travel(61)->minutes();
    $this->tool('find_page', ['page_id' => $page->id, 'locale' => 'en'], $token, 401)
      ->assertJsonPath('result.structuredContent.code', 'auth_required');
  }

  public function test_panel_controls_public_registration_without_a_configured_client(): void
  {
    [$page] = $this->pageFixture();
    DB::table('wbcms_system_settings')->where('key', SystemSettings::MCP_ENABLED)->delete();
    $settingsUrl = self::ORIGIN.'/webadmin/system/settings';
    $this->postJson(self::MCP.'/register', ['redirect_uris' => [self::REDIRECT]])->assertNotFound();
    $editor = $this->cmsActor((new User)->forceFill(['role' => 'editor', 'is_active' => true]), [$page->site_id]);
    $this->actingAs($editor, 'web');
    $this->put($settingsUrl.'/mcp', ['mcp_enabled' => '1'])->assertForbidden();
    $this->get(self::ORIGIN.'/webadmin/profile/mcp-connections')->assertOk();
    $this->actingAs($this->operator(), 'web');
    $this->get($settingsUrl.'?tab=mcp')->assertOk()->assertSee(self::MCP);
    $this->put($settingsUrl.'/mcp', ['mcp_enabled' => '1'])->assertRedirect(route('admin.system.settings.edit', ['tab' => 'mcp']));
    $this->get(self::ORIGIN.'/.well-known/oauth-authorization-server/webadmin/mcp')
      ->assertOk()->assertJsonPath('token_endpoint_auth_methods_supported', ['none', 'client_secret_post']);
    $this->registerClient('Client A', [self::REDIRECT]);
    $this->put($settingsUrl.'/mcp', ['mcp_enabled' => '0'])->assertRedirect();
    $this->postJson(self::MCP.'/register', ['redirect_uris' => [self::REDIRECT]])->assertNotFound();
    $this->postJson(self::MCP, [])->assertNotFound();
  }

  public function test_registered_clients_require_consent_and_use_exact_callbacks_and_pkce(): void
  {
    [$page] = $this->pageFixture();
    $alternate = 'http://127.0.0.1:49200/callback?profile=example';
    $first = $this->registerClient('Client A', [self::REDIRECT, $alternate]);
    $second = $this->registerClient('Client B', [self::REDIRECT]);
    $this->assertNotSame($first, $second);
    $this->assertDatabaseEmpty('wbcms_mcp_grants');
    $this->postJson(self::MCP.'/register', ['redirect_uris' => ['http://external.test/']])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
    $this->postJson(self::MCP.'/register', ['redirect_uris' => ['https://client.example.test/callback?%63ode=forged']])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_redirect_uri');
    $this->postJson(self::MCP.'/register', ['redirect_uris' => [self::REDIRECT], 'token_endpoint_auth_method' => 'client_secret_basic'])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_client_metadata');

    $this->actingAs($this->operator(), 'web');
    $auth = array_replace($this->authorizationRequest($first), ['redirect_uri' => $alternate]);
    $this->get(self::MCP.'/authorize?'.http_build_query($auth))->assertOk()->assertSee('Client A')->assertSee('127.0.0.1')
      ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'; form-action 'self' http://127.0.0.1:49200; base-uri 'none'");
    $this->post(self::MCP.'/authorize', array_replace($auth, ['redirect_uri' => $alternate.'1', 'decision' => 'deny']))->assertBadRequest();
    $this->post(self::MCP.'/authorize', $auth + ['decision' => 'deny'])->assertStatus(303)
      ->assertRedirect($alternate.'&error=access_denied&iss='.rawurlencode(self::MCP).'&state=state-from-client');
    $this->assertDatabaseEmpty('wbcms_mcp_grants');

    $code = $this->consent($page->site_id, $auth);
    $credentials = ['client_id' => $first, 'redirect_uri' => $alternate];
    $this->exchange($code, array_replace($credentials, ['client_id' => $second, 'redirect_uri' => self::REDIRECT]))
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, array_replace($credentials, ['redirect_uri' => self::REDIRECT]))
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $this->exchange($code, $credentials + ['code_verifier' => str_repeat('x', 43)])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $token = $this->exchange($code, $credentials)->assertOk()->json('access_token');
    $this->tool('find_page', ['path' => '/', 'locale' => 'en'], $token)->assertJsonPath('result.structuredContent.page_id', $page->id);
  }

  public function test_dynamic_registration_accepts_a_confidential_pkce_client_requesting_refresh(): void
  {
    [$page] = $this->pageFixture();
    $registration = $this->postJson(self::MCP.'/register', [
      'client_name' => 'ChatGPT',
      'redirect_uris' => ['https://chatgpt.com/connector_platform_oauth_redirect'],
      'grant_types' => ['authorization_code', 'refresh_token'],
      'response_types' => ['code'],
      'token_endpoint_auth_method' => 'client_secret_post',
    ])->assertCreated()
      ->assertJsonPath('grant_types', ['authorization_code'])
      ->assertJsonPath('token_endpoint_auth_method', 'client_secret_post')
      ->assertJsonPath('client_secret_expires_at', 0)
      ->assertHeader('Cache-Control', 'no-store, private');
    $id = $registration->json('client_id');
    $secret = $registration->json('client_secret');
    $this->assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $secret);
    $this->assertDatabaseHas('wbcms_mcp_oauth_clients', ['id' => $id, 'client_secret_hash' => hash('sha256', $secret)]);
    $this->assertDatabaseEmpty('wbcms_mcp_grants');

    $this->actingAs($this->operator(), 'web');
    $redirect = $registration->json('redirect_uris.0');
    $auth = array_replace($this->authorizationRequest($id), ['redirect_uri' => $redirect]);
    $code = $this->consent($page->site_id, $auth);
    $credentials = ['client_id' => $id, 'redirect_uri' => $redirect];
    $this->exchange($code, $credentials)->assertBadRequest()->assertJsonPath('error', 'invalid_client');
    $this->exchange($code, $credentials + ['client_secret' => 'incorrect'])->assertBadRequest()->assertJsonPath('error', 'invalid_client');
    $this->exchange($code, $credentials + ['client_secret' => $secret, 'code_verifier' => str_repeat('z', 43)])
      ->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $token = $this->exchange($code, $credentials + ['client_secret' => $secret])->assertOk()->assertJsonMissingPath('refresh_token')->json('access_token');
    $this->tool('find_page', ['path' => '/', 'locale' => 'en'], $token)->assertJsonPath('result.structuredContent.page_id', $page->id);
  }

  public function test_profile_revocation_is_personal_and_ends_pending_and_active_grants_across_sites(): void
  {
    [$page] = $this->pageFixture();
    $otherSite = Site::create(['handle' => 'another', 'name' => 'Another site']);
    $client = $this->registerClient('Client A', [self::REDIRECT]);
    $otherClient = $this->registerClient('Client B', [self::REDIRECT]);
    $firstUser = $this->operator();
    $secondUser = $this->operator();
    $this->actingAs($firstUser, 'web');
    $auth = $this->authorizationRequest($client);
    $credentials = ['client_id' => $client];
    $first = $this->exchange($this->consent($page->site_id, $auth), $credentials)->assertOk()->json('access_token');
    $second = $this->exchange($this->consent($otherSite->id, $auth), $credentials)->assertOk()->json('access_token');
    $pending = $this->consent($otherSite->id, $auth);
    $otherCode = $this->consent($page->site_id, array_replace($auth, ['client_id' => $otherClient]));
    $other = $this->exchange($otherCode, array_replace($credentials, ['client_id' => $otherClient]))->assertOk()->json('access_token');
    $profileUrl = self::ORIGIN.'/webadmin/profile/mcp-connections';
    $this->get($profileUrl)->assertOk()->assertSee('Client A')->assertSee('Client B');

    $this->actingAs($secondUser, 'web');
    $this->get($profileUrl)->assertOk()->assertDontSee('Client A')->assertDontSee('Client B');
    $this->delete($profileUrl.'/'.$client)->assertNotFound();
    $separate = $this->exchange($this->consent($page->site_id, $auth), $credentials)->assertOk()->json('access_token');
    $this->actingAs($firstUser, 'web');
    $this->setMcp(false);
    $this->delete($profileUrl.'/'.$client)->assertRedirect();
    $this->get($profileUrl)->assertOk()->assertDontSee('Client A')->assertSee('Client B');
    $this->setMcp(true);

    $arguments = ['path' => '/', 'locale' => 'en'];
    $this->tool('find_page', $arguments, $first, 401);
    $this->tool('find_page', $arguments, $second, 401);
    $this->exchange($pending, $credentials)->assertBadRequest()->assertJsonPath('error', 'invalid_grant');
    $this->tool('find_page', $arguments, $other);
    $this->tool('find_page', $arguments, $separate);
  }

  public function test_a_localized_heading_changes_in_the_same_draft_and_renders_in_private_preview(): void
  {
    [$page, $heading] = $this->pageFixture();
    $user = $this->operator();
    $token = $this->connect($user, $page->site_id);
    $reference = ['page_id' => $page->id, 'locale' => 'tr'];
    $read = $this->tool('find_page', ['path' => '/', 'locale' => 'tr'], $token)
      ->assertJsonPath('result.structuredContent.headings.0.title', 'Türkçe başlık')->json('result.structuredContent');
    $input = $reference + ['block_id' => $heading->id, 'version' => $read['version'], 'title' => 'Taslak başlığı'];
    $this->tool('update_draft_heading', array_replace($input, ['locale' => 'en']), $token)
      ->assertJsonPath('result.structuredContent.code', 'conflict');
    $written = $this->tool('update_draft_heading', $input, $token)->assertJsonPath('result.structuredContent.title', 'Taslak başlığı')
      ->json('result.structuredContent');

    $this->tool('update_draft_heading', $input, $token)->assertJsonPath('result.structuredContent.code', 'conflict');
    $read = $this->tool('find_page', $reference, $token)
      ->assertJsonPath('result.structuredContent.headings.0.draft_title', 'Taslak başlığı')->json('result.structuredContent');
    $this->tool('update_draft_heading', array_replace($input, ['version' => $read['version'], 'title' => 'İkinci taslak başlığı']), $token)
      ->assertJsonPath('result.structuredContent.draft_id', $written['draft_id']);
    $this->tool('get_draft_preview', $reference, $token)->assertJsonPath('result.structuredContent.preview_url', $written['preview_url']);

    $draft = Page::findOrFail($written['draft_id']);
    $this->assertSame(Page::STATUS_DRAFT, $draft->status);
    $this->assertSame($user->id, $draft->updated_by_user_id);
    $this->assertSame(1, app(InternalContentPlanService::class)->stagedDrafts($page)->count());
    $this->assertGreaterThanOrEqual(3, $draft->revisions()->count());
    $this->assertSame(['English heading', 'Türkçe başlık'], $heading->textTranslations()->orderBy('locale_id')->pluck('title')->all());
    $this->assertSame(['English heading', 'İkinci taslak başlığı'], $draft->blocks()->firstOrFail()->textTranslations()->orderBy('locale_id')->pluck('title')->all());
    $this->assertSame('Türkçe metin', $draft->blocks()->firstOrFail()->textTranslations()->whereNotNull('content')->value('content'));

    $this->get($written['preview_url'])->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')
      ->assertSee('<h1 class="wb-promo-title">İkinci taslak başlığı</h1>', false);
    app('auth')->forgetGuards();
    $this->get($written['preview_url'])->assertRedirect();
  }

  public function test_a_draft_conflict_and_a_changed_published_source_do_not_rebase_or_overwrite(): void
  {
    [$page, $heading] = $this->pageFixture();
    $user = $this->operator();
    $draft = app(InternalContentPlanService::class)->prepareAuthoringDraft($page, $user);
    $token = $this->connect($user, $page->site_id);
    $ref = ['page_id' => $page->id, 'locale' => 'en'];
    $read = $this->tool('find_page', $ref, $token)->assertJsonPath('result.structuredContent.draft.source_verified', true)->json('result.structuredContent');
    $draft->blocks()->firstOrFail()->textTranslations()->orderBy('id')->firstOrFail()->update(['title' => 'Human draft edit']);
    $input = $ref + ['block_id' => $heading->id, 'version' => $read['version'], 'title' => 'MCP edit'];
    $this->tool('update_draft_heading', $input, $token)->assertJsonPath('result.structuredContent.code', 'conflict');

    $heading->textTranslations()->orderBy('id')->firstOrFail()->update(['title' => 'Human live edit']);
    $result = app(InternalContentPlanService::class)->apply(['plan' => [
      'mode' => InternalContentPlanService::MODE_CREATE_STAGED_UPDATE,
      'page_id' => $page->id, 'expected_source_path' => '/', 'managed_slots' => ['main'],
    ]]);
    $this->assertTrue($result->ok);
    $this->assertTrue($result->data['reused_staged_update']);
    $read = $this->tool('find_page', $ref, $token)->assertJsonPath('result.structuredContent.draft.source_verified', false)->json('result.structuredContent');
    $this->tool('update_draft_heading', array_replace($input, ['version' => $read['version']]), $token)
      ->assertJsonPath('result.structuredContent.code', 'unverified_base');
    $this->assertSame('Human draft edit', $draft->blocks()->firstOrFail()->textTranslations()->orderBy('id')->value('title'));
    $this->assertSame('Human live edit', $heading->textTranslations()->orderBy('id')->value('title'));
  }

  #[DataProvider('headingTypes')]
  public function test_a_new_localized_heading_keeps_inherited_copy_and_revoked_access_stops_the_token(string $type): void
  {
    [$page, $heading] = $this->pageFixture($type, '/editorial');
    $heading->textTranslations()->where('locale_id', Locale::where('code', 'tr')->value('id'))->delete();
    $heading->textTranslations()->firstOrFail()->update(['subtitle' => 'Shared fallback copy']);
    $user = $this->cmsActor((new User)->forceFill(['role' => 'editor', 'is_active' => true]), [$page->site_id]);
    $token = $this->connect($user, $page->site_id);
    $ref = ['page_id' => $page->id, 'locale' => 'tr'];
    $read = $this->tool('find_page', ['path' => '/editorial', 'locale' => 'tr'], $token)
      ->assertJsonPath('result.structuredContent.headings.0.title', 'English heading')->json('result.structuredContent');
    $write = $this->tool('update_draft_heading', $ref + [
      'version' => $read['version'], 'block_id' => $heading->id, 'title' => 'Yerelleştirilmiş başlık',
    ], $token)->json('result.structuredContent');

    $this->get($write['preview_url'])->assertOk()->assertSee('Yerelleştirilmiş başlık');
    $draftBlock = Block::findOrFail($write['block_id']);
    $this->assertSame(['Shared fallback copy', 'Shared fallback copy'], $draftBlock->textTranslations()->orderBy('locale_id')->pluck('subtitle')->all());
    $this->assertSame(1, $heading->textTranslations()->count());
    DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
    $this->tool('find_page', $ref, $token, 401)->assertJsonPath('result.structuredContent.code', 'auth_required');
  }

  public static function headingTypes(): array
  {
    return ['header' => ['header'], 'content header' => ['content_header']];
  }

  private function operator(): User
  {
    return $this->cmsActor((new User)->forceFill(['role' => 'super_admin', 'is_active' => true]));
  }

  private function pageFixture(string $type = 'hero', string $path = '/'): array
  {
    $site = Site::create(['handle' => 'test', 'name' => 'Test site', 'is_primary' => true]);
    $english = Locale::create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $turkish = Locale::create(['code' => 'tr', 'name' => 'Turkish', 'is_enabled' => true]);
    $site->locales()->sync([$english->id => ['is_enabled' => true], $turkish->id => ['is_enabled' => true]]);
    $page = Page::create(['site_id' => $site->id, 'title' => 'Home', 'slug' => 'home', 'status' => Page::STATUS_PUBLISHED]);
    $page->translations()->where('locale_id', $english->id)->firstOrFail()->update(['path' => $path]);
    $page->translations()->create(['locale_id' => $turkish->id, 'name' => 'Ana sayfa', 'slug' => 'home', 'path' => $path]);
    $slot = SlotType::create(['slug' => 'main', 'name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page->slots()->create(['slot_type_id' => $slot->id, 'source_type' => 'page', 'sort_order' => 0]);
    $heading = Block::create(['page_id' => $page->id, 'type' => $type, 'variant' => $type === 'header' ? 'h1' : null, 'slot_type_id' => $slot->id, 'status' => 'published', 'sort_order' => 0]);
    $heading->textTranslations()->create(['locale_id' => $english->id, 'title' => 'English heading']);
    $heading->textTranslations()->create(['locale_id' => $turkish->id, 'title' => 'Türkçe başlık', 'content' => 'Türkçe metin']);

    return [$page, $heading];
  }

  private function connect(User $user, int $siteId): string
  {
    $this->actingAs($user, 'web');
    $auth = array_replace($this->authorizationRequest(), ['scope' => 'pages:read pages:write-draft']);

    return $this->exchange($this->consent($siteId, $auth))->assertOk()->json('access_token');
  }

  private function registerClient(string $name, array $redirectUris): string
  {
    $response = $this->postJson(self::MCP.'/register', [
      'client_name' => $name,
      'redirect_uris' => $redirectUris,
      'token_endpoint_auth_method' => 'none',
    ])->assertCreated()
      ->assertJsonPath('client_name', $name)
      ->assertJsonPath('redirect_uris', $redirectUris)
      ->assertJsonPath('token_endpoint_auth_method', 'none')
      ->assertJsonMissingPath('client_secret');

    return $response->json('client_id');
  }

  private function setMcp(bool $enabled): void
  {
    app(SystemSettings::class)->save([SystemSettings::MCP_ENABLED => $enabled ? '1' : '0']);
  }

  private function defaultClient(): string
  {
    return $this->defaultClient ??= $this->registerClient('Synthetic client', [self::REDIRECT]);
  }

  private function authorizationRequest(?string $client = null): array
  {
    return [
      'client_id' => $client ?? $this->defaultClient(),
      'response_type' => 'code',
      'redirect_uri' => self::REDIRECT,
      'resource' => self::MCP,
      'code_challenge_method' => 'S256',
      'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', str_repeat('v', 43), true)), '+/', '-_'), '='),
      'state' => 'state-from-client',
      'scope' => 'pages:read',
    ];
  }

  private function consent(int $siteId, array $auth): string
  {
    $redirect = $this->post(self::MCP.'/authorize', $auth + ['site_id' => $siteId, 'decision' => 'allow'])
      ->assertStatus(303)->headers->get('Location');
    $separator = str_contains($auth['redirect_uri'], '?') ? '&' : '?';
    $this->assertStringStartsWith($auth['redirect_uri'].$separator, $redirect);
    parse_str(parse_url($redirect, PHP_URL_QUERY), $query);
    $this->assertSame('state-from-client', $query['state']);
    $this->assertSame(self::MCP, $query['iss']);

    return $query['code'];
  }

  private function exchange(string $code, array $overrides = []): TestResponse
  {
    return $this->post(self::MCP.'/token', array_replace([
      'grant_type' => 'authorization_code',
      'code' => $code,
      'code_verifier' => str_repeat('v', 43),
      'client_id' => $overrides['client_id'] ?? $this->defaultClient(),
      'redirect_uri' => self::REDIRECT,
      'resource' => self::MCP,
    ], $overrides));
  }

  private function tool(string $name, array $arguments, ?string $token = null, int $status = 200): TestResponse
  {
    return $this->rpc('tools/call', ['name' => $name, 'arguments' => (object) $arguments], $token)->assertStatus($status);
  }

  private function rpc(string $method, array $params = [], ?string $token = null): TestResponse
  {
    return $this->postJson(self::MCP, [
      'jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => (object) $params,
    ], $token ? ['Authorization' => 'Bearer '.$token] : []);
  }
}
