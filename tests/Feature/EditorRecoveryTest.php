<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Queries\EditorRecovery\EditorRecoveryQuery;
use WebBlocks\Cms\Support\EditorRecovery\EditorRecovery;
use WebBlocks\Cms\Support\EditorRecovery\RecoveryFields;
use WebBlocks\Cms\Support\EditorRecovery\RecoverySchema;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class EditorRecoveryTest extends TestCase
{
  private User $actor;

  private Page $page;

  private Block $block;

  private string $editingSession;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function defineEnvironment($app): void
  {
    parent::defineEnvironment($app);
    $app['config']->set('webblocks-cms.routes.admin', true);
  }

  protected function setUp(): void
  {
    parent::setUp();
    if (! class_exists(User::class)) {
      class_alias(EditorRecoveryTestUser::class, User::class);
    }
    $this->withoutMiddleware([RedirectIfNotInstalled::class]);
    $this->actor = User::forceCreate(['name' => 'Recovery administrator', 'email' => 'recovery@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->actingAs($this->actor);
    $locale = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $site = Site::create(['name' => 'Recovery studio', 'handle' => 'recovery']);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $this->page = Page::create(['site_id' => $site->id, 'title' => 'Saved title', 'slug' => 'saved-title', 'status' => 'draft']);
    $type = BlockType::firstOrCreate(['slug' => 'rich-text'], ['name' => 'Rich Text', 'category' => 'content', 'source_type' => 'static', 'status' => 'published']);
    $slot = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $this->page->id, 'slot_type_id' => $slot->id, 'source_type' => 'page']);
    $this->block = Block::create(['page_id' => $this->page->id, 'block_type_id' => $type->id, 'slot_type_id' => $slot->id, 'type' => 'rich-text', 'content' => 'Saved block', 'status' => 'draft']);
    $this->editingSession = (string) Str::uuid();
  }

  private function url(?string $session = null, array $context = []): string
  {
    return route('admin.pages.recovery.show', ['page' => $this->page, 'editingSession' => $session ?? $this->editingSession, 'locale' => 'en', ...$context]);
  }

  private function payload(array $values = ['title' => 'Unfinished title'], array $extra = []): array
  {
    return ['locale' => 'en', '_checkpoint' => '1', 'sequence' => 1, 'base_fingerprint' => app(EditorRecovery::class)->fingerprint($this->page), 'entries' => collect($values)->map(fn ($value, $name) => ['name' => $name, 'present' => $value !== null, 'value' => $value === null ? null : base64_encode($value)])->values()->all(), ...$extra];
  }

  #[Test]
  public function checkpoints_and_restore_leave_saved_content_workflow_and_revisions_untouched(): void
  {
    $before = app(EditorRecovery::class)->fingerprint($this->page);
    $revisions = DB::table('wbcms_page_revisions')->count();
    $this->putJson($this->url(), $this->payload())->assertOk()->assertJsonPath('sequence', 1);
    $this->getJson($this->url())->assertOk()->assertHeader('Cache-Control', 'no-store, private')->assertJsonPath('exists', true)->assertJsonPath('conflict', false);
    $restore = $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en'])->assertOk()->assertSessionMissing('_old_input');
    $this->get('/webadmin/access/roles')->assertOk()->assertSessionMissing('_old_input');
    $this->get($restore->json('redirect'))->assertOk()->assertSee('value="Unfinished title"', false)->assertSessionMissing('_old_input');
    $this->get($restore->json('redirect'))->assertGone();
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($this->page));
    $this->assertSame($revisions, DB::table('wbcms_page_revisions')->count());
    $this->assertDatabaseCount('wbcms_editor_recoveries', 1);
  }

  #[Test]
  public function rich_text_media_references_and_structured_values_are_relational_and_restored(): void
  {
    $richText = "  <p>Türkçe <strong>taslak</strong></p>\n";
    $this->putJson($this->url(), $this->payload(['content' => $richText, 'media_id' => '42', 'gallery_items[0][media_id]' => '43', 'settings' => '{"layout":{"columns":2,"enabled":true},"empty":[]}', 'gallery_media_ids[]' => null], ['block_id' => $this->block->id]))->assertOk();
    $this->assertDatabaseHas('wbcms_editor_recovery_values', ['field_name' => 'settings', 'format' => 'json', 'text_value' => null]);
    $this->assertGreaterThan(3, DB::table('wbcms_editor_recovery_nodes')->count());
    $restore = $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en', 'block_id' => $this->block->id])->assertOk()->assertSessionMissing('_old_input');
    $this->get($restore->json('redirect'))->assertOk()->assertSee(e($richText), false)->assertSessionMissing('_old_input');
    $entries = app(EditorRecoveryQuery::class)->entries(DB::table('wbcms_editor_recoveries')->value('id'));
    $values = app(RecoveryFields::class)->input($entries);
    $this->assertSame('42', $values['media_id']);
    $this->assertSame('43', $values['gallery_items'][0]['media_id']);
    $this->assertSame([], $values['gallery_media_ids']);
    $this->assertSame('Saved block', $this->block->fresh()->content);
  }

  #[Test]
  public function user_page_locale_block_and_editing_sessions_cannot_cross_contexts(): void
  {
    $this->putJson($this->url(), $this->payload())->assertOk();
    $otherSession = (string) Str::uuid();
    $this->putJson($this->url($otherSession), $this->payload(['title' => 'Second tab']))->assertOk();
    $this->getJson($this->url())->assertOk()->assertJsonPath('entries.0.value', base64_encode('Unfinished title'));
    $this->getJson($this->url(null, ['block_id' => $this->block->id]))->assertNotFound();
    $this->getJson($this->url(null, ['locale' => 'tr']))->assertNotFound();
    $otherPage = Page::create(['site_id' => $this->page->site_id, 'title' => 'Other', 'status' => 'draft']);
    $this->getJson(route('admin.pages.recovery.show', ['page' => $otherPage, 'editingSession' => $this->editingSession, 'locale' => 'en']))->assertNotFound();
    $other = User::forceCreate(['name' => 'Other administrator', 'email' => 'other-recovery@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->actingAs($other)->getJson($this->url())->assertOk()->assertJsonPath('exists', false);
    $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en'])->assertGone();
    $this->actingAs($this->actor)->getJson($this->url())->assertOk()->assertJsonPath('exists', true);
  }

  #[Test]
  public function conflicts_require_review_and_a_later_save_checks_the_current_saved_record_again(): void
  {
    $payload = $this->payload();
    $this->putJson($this->url(), $payload)->assertOk();
    $this->page->forceFill(['status' => 'draft', 'settings' => ['public_shell' => 'docs']])->save();
    $this->getJson($this->url())->assertOk()->assertJsonPath('conflict', true);
    $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en'])->assertConflict();
    $response = $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en', 'reviewed_conflict' => true])->assertOk();
    $this->get($response->json('redirect'))->assertOk()->assertSee('value="Unfinished title"', false);
    $this->assertNotSame($payload['base_fingerprint'], DB::table('wbcms_editor_recoveries')->value('base_fingerprint'));
    $this->put('/webadmin/pages/'.$this->page->id, ['title' => 'Stale overwrite', 'slug' => 'stale', 'site_id' => $this->page->site_id, '_editor_recovery_session' => $this->editingSession, '_editor_recovery_base' => $payload['base_fingerprint']])->assertSessionHasErrors('_editor_recovery_base');
    $this->assertDatabaseHas('wbcms_editor_recoveries', ['cleared' => false]);
  }

  #[Test]
  public function revoked_permissions_published_pages_and_deleted_blocks_cannot_restore(): void
  {
    $role = app(WriteCmsAccess::class)->role($this->actor, ['name' => 'Recovery writer', 'permissions' => ['cms.panel.access', 'cms.pages.read', 'cms.pages.update-draft']]);
    $writer = User::forceCreate(['name' => 'Writer', 'email' => 'writer-recovery@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $writer->sites()->attach($this->page->site_id, ['role_id' => $role->id]);
    $this->actingAs($writer)->putJson($this->url(), $this->payload())->assertOk();
    DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->where('permission', 'cms.pages.update-draft')->delete();
    $this->getJson($this->url())->assertForbidden();
    $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en', 'reviewed_conflict' => true])->assertForbidden();
    $this->actingAs($this->actor);
    $this->putJson($this->url((string) Str::uuid()), $this->payload(['content' => 'Work'], ['block_id' => $this->block->id]))->assertOk();
    $this->block->delete();
    $this->getJson($this->url(null, ['block_id' => $this->block->id]))->assertNotFound();
    $this->page->forceFill(['status' => 'published'])->save();
    $this->getJson($this->url())->assertForbidden();
  }

  #[Test]
  public function successful_save_and_explicit_discard_clear_only_the_applicable_checkpoint_and_block_late_writes(): void
  {
    $payload = $this->payload();
    $otherSession = (string) Str::uuid();
    $this->putJson($this->url(), $payload)->assertOk();
    $this->putJson($this->url($otherSession), $payload)->assertOk();
    $this->put('/webadmin/pages/'.$this->page->id, ['title' => 'Explicitly saved', 'slug' => 'explicitly-saved', 'site_id' => $this->page->site_id, '_editor_recovery_session' => $this->editingSession, '_editor_recovery_base' => $payload['base_fingerprint']])->assertRedirect()->assertSessionHasNoErrors();
    $this->getJson($this->url())->assertGone();
    $this->putJson($this->url(), [...$payload, 'sequence' => 2])->assertGone();
    $this->getJson($this->url($otherSession))->assertOk()->assertJsonPath('exists', true);
    $this->deleteJson($this->url($otherSession), ['locale' => 'en'])->assertOk();
    $this->putJson($this->url($otherSession), [...$payload, 'sequence' => 3])->assertGone();
    $this->assertDatabaseCount('wbcms_editor_recovery_values', 0);
  }

  #[Test]
  public function failed_saves_keep_checkpoints_and_expiry_cleans_their_values_and_nodes(): void
  {
    $payload = $this->payload(['settings' => '{"layout":{"columns":2}}'], ['block_id' => $this->block->id]);
    $this->putJson($this->url(), $payload)->assertOk();
    $this->put('/webadmin/pages/'.$this->page->id, ['title' => '', 'site_id' => $this->page->site_id])->assertSessionHasErrors();
    $this->getJson($this->url(null, ['block_id' => $this->block->id]))->assertOk()->assertJsonPath('exists', true);
    $this->travel(8)->days();
    $this->getJson($this->url(null, ['block_id' => $this->block->id]))->assertGone();
    $this->artisan('webblocks:editor-recovery:prune')->assertSuccessful();
    $this->assertDatabaseCount('wbcms_editor_recoveries', 0);
    $this->assertDatabaseCount('wbcms_editor_recovery_values', 0);
    $this->assertDatabaseCount('wbcms_editor_recovery_nodes', 0);
  }

  #[Test]
  public function older_requests_cannot_overwrite_a_newer_checkpoint_and_limits_are_enforced(): void
  {
    $payload = $this->payload();
    $this->putJson($this->url(), [...$payload, 'sequence' => 2])->assertOk();
    $this->putJson($this->url(), $this->payload(['title' => 'Old response']))->assertOk()->assertJsonPath('sequence', 2);
    $this->getJson($this->url())->assertOk()->assertJsonPath('entries.0.value', base64_encode('Unfinished title'));
    foreach ([['password' => 'private'], ['title' => str_repeat('x', 262145)], ['settings[api_key]' => 'private'], ['settings' => '{"private_key":"private"}']] as $values) {
      $this->putJson($this->url((string) Str::uuid()), $this->payload($values, ['block_id' => $this->block->id]))->assertUnprocessable();
    }
    $this->putJson($this->url((string) Str::uuid()), $this->payload(['title' => 'Other'], ['_checkpoint' => null]))->assertUnprocessable();
    RecoverySchema::migrate();
    $this->assertDatabaseCount('wbcms_editor_recoveries', 1);
  }

  #[Test]
  public function translated_blocks_and_staged_drafts_recover_without_touching_the_published_source(): void
  {
    $locale = Locale::firstOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'is_enabled' => true, 'is_default' => false]);
    $this->page->site->locales()->attach($locale->id, ['is_enabled' => true]);
    $published = Page::create(['site_id' => $this->page->site_id, 'title' => 'Published source', 'status' => 'published']);
    $this->page->forceFill(['settings' => ['staged_update' => ['source_page_id' => $published->id]]])->save();
    $before = app(EditorRecovery::class)->fingerprint($published);
    $this->putJson($this->url(), $this->payload(['content' => '<p>Yarım kalan içerik</p>'], ['block_id' => $this->block->id, 'locale' => 'tr']))->assertOk();
    $this->getJson($this->url(null, ['block_id' => $this->block->id, 'locale' => 'en']))->assertNotFound();
    $restore = $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'tr', 'block_id' => $this->block->id])->assertOk();
    $this->get($restore->json('redirect'))->assertOk()->assertSee('value="tr"', false)->assertSee(e('<p>Yarım kalan içerik</p>'), false);
    $this->assertSame($before, app(EditorRecovery::class)->fingerprint($published));
    $this->assertNull(app(EditorRecovery::class)->form($this->actor, $published));
    $this->assertNull(app(EditorRecovery::class)->form($this->actor, new Page));
  }

  #[Test]
  public function reauthentication_preserves_the_same_users_checkpoint_and_messages_cover_all_panel_languages(): void
  {
    $this->putJson($this->url(), $this->payload())->assertOk();
    auth()->logout();
    $response = $this->getJson($this->url());
    $this->assertContains($response->status(), [302, 401, 403]);
    $response->assertDontSee(base64_encode('Unfinished title'));
    $this->actingAs($this->actor)->getJson($this->url())->assertOk()->assertJsonPath('exists', true);
    $source = require dirname(__DIR__, 2).'/resources/lang/en/recovery.php';
    foreach (['en', 'de', 'tr', 'es', 'it', 'fr'] as $locale) {
      $messages = require dirname(__DIR__, 2).'/resources/lang/'.$locale.'/recovery.php';
      $this->assertSame(array_keys($source), array_keys($messages));
      foreach ($messages as $value) {
      $this->assertNotSame('', trim($value));
      }
      $this->assertStringContainsString(':count', $messages['pruned']);
    }
  }

  #[Test]
  public function restored_media_choices_show_the_current_authorized_asset_and_keep_explicitly_cleared_choices_empty(): void
  {
    $media = [];
    foreach (['Saved blue image', 'Recovered green image'] as $index => $title) {
      $media[] = Media::create(['disk' => 'public', 'path' => 'recovery-'.$index.'.svg', 'filename' => 'recovery-'.$index.'.svg', 'mime_type' => 'image/svg+xml', 'size' => 100, 'kind' => 'image', 'visibility' => 'public', 'title' => $title]);
    }
    $type = BlockType::firstOrCreate(['slug' => 'image'], ['name' => 'Image', 'category' => 'media', 'source_type' => 'static', 'status' => 'published']);
    $this->block->forceFill(['type' => 'image', 'block_type_id' => $type->id, 'media_id' => $media[0]->id])->save();
    $this->putJson($this->url(), $this->payload(['asset_id' => (string) $media[1]->id], ['block_id' => $this->block->id]))->assertOk();
    $restore = $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en', 'block_id' => $this->block->id])->assertOk();
    $this->get($restore->json('redirect'))->assertOk()->assertSee('data-wb-picker-selected-title="Recovered green image"', false);
    $this->assertSame($media[0]->id, $this->block->fresh()->media_id);
    $this->putJson($this->url(), $this->payload(['asset_id' => ''], ['block_id' => $this->block->id, 'sequence' => 3]))->assertOk();
    $restore = $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en', 'block_id' => $this->block->id])->assertOk();
    $response = $this->get($restore->json('redirect'))->assertOk();
    $document = new \DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new \DOMXPath($document);
    $this->assertSame('', $xpath->query('//*[@id="asset_id"]')->item(0)->getAttribute('value'));
    $this->assertSame('', $xpath->query('//*[@id="asset_id"]')->item(0)->getAttribute('data-wb-picker-selected-title'));
  }

  #[Test]
  public function authority_is_rechecked_after_waiting_for_the_acl_lock(): void
  {
    $role = app(WriteCmsAccess::class)->role($this->actor, ['name' => 'Writer', 'permissions' => ['cms.panel.access', 'cms.pages.read', 'cms.pages.update-draft']]);
    $writer = User::forceCreate(['name' => 'Lock writer', 'email' => 'lock-recovery@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $writer->sites()->attach($this->page->site_id, ['role_id' => $role->id]);
    $payload = $this->payload();
    $revoked = false;
    DB::connection()->beforeExecuting(function ($sql) use ($role, &$revoked): void {
      if (! $revoked && str_contains($sql, 'wbcms_acl_locks')) {
        $revoked = true;
        DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->where('permission', 'cms.pages.update-draft')->delete();
      }
    });
    $this->actingAs($writer)->putJson($this->url(), $payload)->assertForbidden();
    $this->assertTrue($revoked);
    $this->assertDatabaseCount('wbcms_editor_recoveries', 0);
  }

  #[Test]
  public function active_session_field_count_and_structured_node_limits_are_bounded(): void
  {
    $payload = $this->payload();
    $entries = array_fill(0, 513, ['name' => 'title', 'present' => true, 'value' => base64_encode('text')]);
    $this->putJson($this->url(), [...$payload, 'entries' => $entries])->assertUnprocessable();
    $this->putJson($this->url(), $this->payload(['settings' => '['.implode(',', array_fill(0, 4096, '1')).']'], ['block_id' => $this->block->id]))->assertUnprocessable();
    foreach (range(1, 32) as $index) {
      DB::table('wbcms_editor_recoveries')->insert(['user_id' => $this->actor->id, 'page_id' => $this->page->id, 'site_id' => $this->page->site_id, 'editing_session' => (string) Str::uuid(), 'locale' => 'en', 'base_fingerprint' => $payload['base_fingerprint'], 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
    }
    $this->putJson($this->url(), $payload)->assertStatus(429);
    $this->assertDatabaseCount('wbcms_editor_recoveries', 32);
  }

  #[Test]
  public function asynchronous_saves_confirm_success_without_following_a_redirect_and_clear_only_the_matching_checkpoint(): void
  {
    $payload = $this->payload();
    $this->putJson($this->url(), $payload)->assertOk();
    $otherSession = (string) Str::uuid();
    $this->putJson($this->url($otherSession), $payload)->assertOk();
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->putJson(route('admin.pages.update', $this->page), [
      'title' => 'Confirmed page', 'slug' => 'confirmed-page', 'site_id' => $this->page->site_id,
      '_editor_recovery_session' => $this->editingSession, '_editor_recovery_base' => $payload['base_fingerprint'],
    ])->assertOk()->assertJsonPath('saved', true)->assertJsonPath('redirect', route('admin.pages.edit', $this->page))->assertHeader('Cache-Control', 'no-store, private');
    $this->assertSame('Confirmed page', $this->page->fresh()->title);
    $this->getJson($this->url())->assertGone();
    $this->getJson($this->url($otherSession))->assertOk()->assertJsonPath('exists', true);
  }

  #[Test]
  public function asynchronous_block_validation_keeps_the_checkpoint_and_success_returns_an_explicit_receipt(): void
  {
    $content = '<p>Latest <strong>Türkçe</strong> work</p>';
    $payload = $this->payload(['content' => $content], ['block_id' => $this->block->id]);
    $this->putJson($this->url(), $payload)->assertOk();
    $input = ['page_id' => $this->page->id, 'block_type_id' => $this->block->block_type_id, 'slot_type_id' => $this->block->slot_type_id,
      'sort_order' => -1, 'status' => 'draft', 'content' => $content,
      '_editor_recovery_session' => $this->editingSession, '_editor_recovery_base' => $payload['base_fingerprint']];
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->putJson(route('admin.blocks.update', $this->block), $input)->assertUnprocessable()->assertJsonValidationErrors('sort_order');
    $this->assertSame('Saved block', $this->block->fresh()->content);
    $this->getJson($this->url(null, ['block_id' => $this->block->id]))->assertOk()->assertJsonPath('entries.0.value', base64_encode($content));
    $this->putJson(route('admin.blocks.update', $this->block), [...$input, 'sort_order' => 0])->assertOk()->assertJsonPath('saved', true);
    $this->assertSame($content, $this->block->fresh()->textTranslations()->firstOrFail()->content);
    $this->getJson($this->url(null, ['block_id' => $this->block->id]))->assertGone();
  }

  #[Test]
  public function runtime_save_failure_rolls_back_both_editorial_changes_and_checkpoint_cleanup(): void
  {
    $payload = $this->payload();
    $this->putJson($this->url(), $payload)->assertOk();
    $armed = true;
    DB::connection()->beforeExecuting(function ($sql) use (&$armed): void {
      if ($armed && str_starts_with(strtolower(ltrim($sql)), 'insert') && str_contains($sql, 'wbcms_page_revisions')) {
        $armed = false;
        throw new \RuntimeException('Synthetic revision write failure');
      }
    });
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->putJson(route('admin.pages.update', $this->page), [
      'title' => 'Failed title', 'slug' => 'failed-title', 'site_id' => $this->page->site_id,
      '_editor_recovery_session' => $this->editingSession, '_editor_recovery_base' => $payload['base_fingerprint'],
    ])->assertStatus(500);
    $this->assertFalse($armed);
    $this->assertSame('Saved title', $this->page->fresh()->title);
    $this->getJson($this->url())->assertOk()->assertJsonPath('exists', true);
  }

  #[Test]
  public function recovery_reads_supply_the_current_csrf_token_after_reauthentication(): void
  {
    $this->putJson($this->url(), $this->payload())->assertOk();
    $token = 'synthetic-refreshed-token';
    $this->withSession(['_token' => $token])->getJson($this->url())->assertOk()->assertJsonPath('csrf_token', $token)->assertHeader('Cache-Control', 'no-store, private');
    auth()->logout();
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->putJson(route('admin.pages.update', $this->page), ['title' => 'Not saved'])->assertUnauthorized()->assertJsonMissing(['saved' => true]);
    $this->actingAs($this->actor)->getJson($this->url())->assertOk()->assertJsonPath('exists', true);
  }

  #[Test]
  public function asynchronous_save_keeps_the_existing_csrf_boundary_and_checkpoint(): void
  {
    $this->putJson($this->url(), $this->payload())->assertOk();
    if (class_exists(PreventRequestForgery::class)) {
      $this->app->bind(PreventRequestForgery::class, fn ($app) => new class($app, $app['encrypter']) extends PreventRequestForgery
      {
        protected function runningUnitTests()
        {
        return false;
        }
      });
    } else {
      $this->app->bind(ValidateCsrfToken::class, fn ($app) => new class($app, $app['encrypter']) extends ValidateCsrfToken
      {
        protected function runningUnitTests()
        {
        return false;
        }
      });
    }
    $this->withSession(['_token' => 'synthetic-current-token'])->withHeader('X-WebBlocks-Editor-Save', '1')->putJson(route('admin.pages.update', $this->page), ['_token' => 'synthetic-expired-token', 'title' => 'Not saved', 'slug' => 'not-saved', 'site_id' => $this->page->site_id])->assertStatus(419);
    $this->assertSame('Saved title', $this->page->fresh()->title);
    $this->getJson($this->url())->assertOk()->assertJsonPath('exists', true);
  }

  #[Test]
  public function empty_gallery_survives_native_validation_recovery_and_explicit_save(): void
  {
    $this->galleryFixture();
    $slot = $this->page->slots()->firstOrFail();
    $edit = route('admin.pages.slots.blocks', [$this->page, $slot, 'edit' => $this->block->id]);
    $input = ['page_id' => $this->page->id, 'block_type_id' => $this->block->block_type_id, 'slot_type_id' => $this->block->slot_type_id,
      'sort_order' => -1, 'status' => 'draft', 'gallery_items' => '', '_slot_block_mode' => 'edit', '_slot_block_id' => $this->block->id];
    $this->from($edit)->put(route('admin.blocks.update', $this->block), $input)->assertRedirect($edit)->assertSessionHasErrors('sort_order');
    $this->assertSame([], $this->renderedGalleryIds($this->get($edit)->assertOk()->getContent()));
    $payload = $this->payload(['gallery_items[]' => null], ['block_id' => $this->block->id]);
    $this->putJson($this->url(), $payload)->assertOk();
    $restore = $this->postJson(strtok($this->url(), '?').'/restore', ['locale' => 'en', 'block_id' => $this->block->id])->assertOk();
    $this->assertSame([], $this->renderedGalleryIds($this->get($restore->json('redirect'))->assertOk()->getContent()));
    $this->assertCount(2, $this->block->fresh()->galleryItems());
    $this->put(route('admin.blocks.update', $this->block), [...$input, 'sort_order' => 0])->assertRedirect()->assertSessionHasNoErrors();
    $this->assertCount(0, $this->block->fresh()->galleryItems());
  }

  #[Test]
  public function gallery_validation_retains_partial_removal_order_and_metadata(): void
  {
    $media = $this->galleryFixture();
    $slot = $this->page->slots()->firstOrFail();
    $edit = route('admin.pages.slots.blocks', [$this->page, $slot, 'edit' => $this->block->id]);
    $items = [['media_id' => $media[1]->id, 'sort_order' => 0, 'alt_text' => 'Second visual', 'caption' => 'Independent caption']];
    $input = ['page_id' => $this->page->id, 'block_type_id' => $this->block->block_type_id, 'slot_type_id' => $this->block->slot_type_id,
      'sort_order' => -1, 'status' => 'draft', 'gallery_items' => $items, '_slot_block_mode' => 'edit', '_slot_block_id' => $this->block->id];
    $this->from($edit)->put(route('admin.blocks.update', $this->block), $input)->assertSessionHasErrors('sort_order');
    $response = $this->get($edit)->assertOk()->assertSee('Independent caption');
    $this->assertSame([$media[1]->id], $this->renderedGalleryIds($response->getContent()));
    $items[] = ['media_id' => $media[0]->id, 'sort_order' => 1];
    $this->from($edit)->put(route('admin.blocks.update', $this->block), [...$input, 'gallery_items' => $items])->assertSessionHasErrors('sort_order');
    $this->assertSame([$media[1]->id, $media[0]->id], $this->renderedGalleryIds($this->get($edit)->assertOk()->getContent()));
  }

  private function galleryFixture(): array
  {
    $type = BlockType::firstOrCreate(['slug' => 'gallery'], ['name' => 'Gallery', 'category' => 'media', 'source_type' => 'static', 'status' => 'published']);
    $this->block->forceFill(['type' => 'gallery', 'block_type_id' => $type->id])->save();
    $media = [];
    foreach (range(0, 1) as $position) {
      $media[$position] = Media::create(['disk' => 'public', 'path' => 'synthetic-gallery-'.$position.'.svg', 'filename' => 'synthetic-gallery-'.$position.'.svg', 'mime_type' => 'image/svg+xml', 'size' => 100, 'kind' => 'image', 'visibility' => 'public', 'title' => 'Synthetic gallery '.$position]);
      $this->block->blockMedia()->create(['media_id' => $media[$position]->id, 'role' => 'gallery_item', 'position' => $position]);
    }

    return $media;
  }

  private function renderedGalleryIds(string $html): array
  {
    $document = new \DOMDocument;
    @$document->loadHTML($html);
    $rows = (new \DOMXPath($document))->query('//*[@data-wb-gallery-item-row and not(ancestor::template)]');

    return array_map(fn ($row) => (int) $row->getAttribute('data-media-id'), iterator_to_array($rows));
  }

  #[Test]
  public function editor_save_browser_fixture(): void
  {
    $response = $this->get(route('admin.pages.edit', [$this->page, 'tab' => 'settings']))->assertOk();
    $document = new \DOMDocument;
    @$document->loadHTML($response->getContent());
    $form = (new \DOMXPath($document))->query('//*[@data-wb-editor-recovery]/ancestor::form')->item(0);
    $this->assertNotNull($form);
    $blockResponse = $this->get(route('admin.pages.slots.blocks', [$this->page, $this->page->slots()->firstOrFail(), 'edit' => $this->block->id]))->assertOk();
    $blockDocument = new \DOMDocument;
    @$blockDocument->loadHTML($blockResponse->getContent());
    $blockForm = (new \DOMXPath($blockDocument))->query('//*[@data-wb-editor-recovery]/ancestor::form')->item(0);
    $this->assertNotNull($blockForm);
    $turkish = Locale::firstOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'is_enabled' => true, 'is_default' => false]);
    $this->page->site->locales()->syncWithoutDetaching([$turkish->id => ['is_enabled' => true]]);
    $otherSite = Site::create(['name' => 'Other synthetic studio', 'handle' => 'other-recovery-fixture']);
    $otherSite->locales()->syncWithoutDetaching([Locale::where('code', 'en')->value('id') => ['is_enabled' => true]]);
    $newForms = '';
    $routes = [
      'new-page' => route('admin.pages.create', ['site' => $this->page->site_id]),
      'new-block' => route('admin.pages.slots.blocks', [$this->page, $this->page->slots()->firstOrFail(), 'block_type_id' => $this->block->block_type_id]),
      'new-block-unbound' => route('admin.blocks.create', ['block_type_id' => $this->block->block_type_id]),
      'new-translation' => route('admin.pages.translations.create', [$this->page, $turkish]),
      'translation' => route('admin.pages.translations.edit', [$this->page, $this->page->defaultTranslation()]),
    ];
    foreach ($routes as $kind => $url) {
      $html = $this->get($url)->assertOk()->getContent();
      $newDocument = new \DOMDocument;
      @$newDocument->loadHTML($html);
      $newForm = (new \DOMXPath($newDocument))->query('//*[@data-wb-editor-recovery]/ancestor::form')->item(0);
      $this->assertNotNull($newForm, $kind.' must render recovery.');
      $newForms .= '<template id="save-test-'.$kind.'">'.$newDocument->saveHTML($newForm).'</template>';
    }
    if ($path = getenv('WEBBLOCKS_EDITOR_SAVE_FIXTURE')) {
      file_put_contents($path, '<!doctype html><html lang="en"><body><h1>Editor save failure tests</h1><button type="button" id="run-save-tests">Run save tests</button><output id="save-test-results" aria-live="polite">Ready</output><div id="save-test-mount"></div><template id="save-test-form">'.$document->saveHTML($form).'</template><template id="save-test-block-form">'.$blockDocument->saveHTML($blockForm).'</template>'.$newForms.'</body></html>');
    }
  }
}

class EditorRecoveryTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey()
  {
    return 'user_id';
  }
}
