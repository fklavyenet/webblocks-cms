<?php

namespace WebBlocks\Cms\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Actions\Access\WriteCmsAccess;
use WebBlocks\Cms\Http\Middleware\RedirectIfNotInstalled;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageLayout;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\InternalApiTokens\CmsApiTokenIssuer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class EditorFormRecoveryTest extends TestCase
{
  private User $actor;

  private Site $site;

  private Page $page;

  private BlockType $type;

  private SlotType $slot;

  private Locale $english;

  private Locale $turkish;

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
    if (! class_exists('App\\Models\\User')) {
    class_alias(EditorFormRecoveryTestUser::class, 'App\\Models\\User');
    }
    $this->withoutMiddleware(RedirectIfNotInstalled::class);
    $this->actor = User::forceCreate(['name' => 'Synthetic form operator', 'email' => 'forms@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->actingAs($this->actor);
    $this->english = Locale::firstOrCreate(['code' => 'en'], ['name' => 'English', 'is_enabled' => true, 'is_default' => true]);
    $this->turkish = Locale::firstOrCreate(['code' => 'tr'], ['name' => 'Turkish', 'is_enabled' => true, 'is_default' => false]);
    $this->site = Site::create(['name' => 'Synthetic studio', 'handle' => 'form-recovery']);
    $this->site->locales()->syncWithoutDetaching([$this->english->id => ['is_enabled' => true], $this->turkish->id => ['is_enabled' => true]]);
    $this->page = Page::create(['site_id' => $this->site->id, 'title' => 'Saved page', 'slug' => 'saved-page', 'status' => 'draft']);
    $this->type = BlockType::firstOrCreate(['slug' => 'rich-text'], ['name' => 'Rich Text', 'category' => 'content', 'source_type' => 'static', 'status' => 'published']);
    $this->slot = SlotType::firstOrCreate(['slug' => 'main'], ['name' => 'Main', 'status' => 'published']);
    PageSlot::create(['page_id' => $this->page->id, 'slot_type_id' => $this->slot->id, 'source_type' => 'page']);
  }

  private function identity(string $kind = 'new-page'): array
  {
    return match ($kind) {
      'new-page' => ['form_kind' => $kind, 'site_id' => $this->site->id, 'locale' => 'en'],
      'new-block' => ['form_kind' => $kind, 'page_id' => $this->page->id, 'block_type_id' => $this->type->id, 'slot_type_id' => $this->slot->id, 'locale' => 'en'],
      'new-translation' => ['form_kind' => $kind, 'page_id' => $this->page->id, 'locale' => 'tr'],
      default => ['form_kind' => $kind, 'page_id' => $this->page->id, 'translation_id' => $this->page->defaultTranslation()->id, 'locale' => 'en'],
    };
  }

  private function url(string $session, array $identity): string
  {
    return route('admin.editor-forms.recovery.show', ['editingSession' => $session, ...$identity]);
  }

  private function checkpoint(string $session, array $identity, array $values): array
  {
    $base = $this->getJson($this->url($session, $identity))->assertOk()->assertHeader('Cache-Control', 'no-store, private')->json('base_fingerprint');
    $payload = [...$identity, '_checkpoint' => '1', 'sequence' => 1, 'base_fingerprint' => $base,
      'entries' => collect($values)->map(fn ($value, $name) => ['name' => $name, 'present' => $value !== null, 'value' => $value === null ? null : base64_encode($value)])->values()->all()];
    $this->putJson($this->url($session, $identity), $payload)->assertOk();

    return ['_editor_form_kind' => $identity['form_kind'], '_editor_recovery_session' => $session, '_editor_recovery_base' => $base];
  }

  #[Test]
  public function new_page_checkpoint_restores_without_creating_content_then_save_is_replay_safe(): void
  {
    $identity = $this->identity();
    $session = (string) Str::uuid();
    $pages = Page::count();
    $revisions = DB::table('wbcms_page_revisions')->count();
    $metadata = $this->checkpoint($session, $identity, ['title' => 'Unfinished new page', 'slug' => 'unfinished-new-page', 'public_shell' => '']);
    $this->assertSame($pages, Page::count());
    $this->assertSame($revisions, DB::table('wbcms_page_revisions')->count());
    $this->assertNull(DB::table('wbcms_editor_recoveries')->value('page_id'));
    $restore = $this->postJson(strtok($this->url($session, $identity), '?').'/restore', $identity)->assertOk()->assertSessionMissing('_old_input');
    $this->get($restore->json('redirect'))->assertOk()->assertSee('value="Unfinished new page"', false)->assertSessionMissing('_old_input');
    $this->get($restore->json('redirect'))->assertGone();
    $input = ['site_id' => $this->site->id, 'title' => 'Unfinished new page', 'slug' => 'unfinished-new-page', 'public_shell' => '', ...$metadata];
    $response = $this->withHeader('X-WebBlocks-Editor-Save', '1')->postJson(route('admin.pages.store'), $input)->assertOk()->assertJsonPath('saved', true);
    $this->assertSame($pages + 1, Page::count());
    $this->postJson(route('admin.pages.store'), [...$input, 'slug' => 'different-replay-slug'])->assertGone();
    $this->assertSame($pages + 1, Page::count());
    $this->getJson($this->url($session, $identity))->assertOk()->assertJsonPath('completed', true)->assertJsonPath('redirect', $response->json('redirect'));
    $this->assertDatabaseCount('wbcms_editor_recovery_values', 0);
  }

  #[Test]
  public function page_validation_and_transaction_failure_keep_values_and_do_not_create_a_partial_page(): void
  {
    $layout = PageLayout::create(['handle' => 'draft-test', 'name' => 'Draft layout', 'is_active' => true]);
    $layout->layoutSlots()->create(['slot_type_id' => $this->slot->id, 'slot_name' => 'main', 'sort_order' => 2, 'is_active' => true]);
    $identity = $this->identity();
    $session = (string) Str::uuid();
    $metadata = $this->checkpoint($session, $identity, ['title' => 'Retained draft']);
    $input = ['site_id' => $this->site->id, 'title' => 'Retained draft', 'slug' => 'retained-draft', 'public_shell' => $layout->handle, ...$metadata];
    $input['blocks'] = [['block_type_id' => $this->type->id, 'slot_type_id' => $this->slot->id, 'content' => '<p>Initial draft copy</p>', 'status' => 'draft']];
    $tables = ['wbcms_pages', 'wbcms_page_translations', 'wbcms_page_slots', 'wbcms_blocks', 'wbcms_block_text_translations', 'wbcms_page_revisions'];
    $counts = array_map(fn (string $table) => DB::table($table)->count(), $tables);
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->postJson(route('admin.pages.store'), [...$input, 'title' => ''])
      ->assertUnprocessable()->assertJsonValidationErrors('title');
    $armed = true;
    DB::connection()->beforeExecuting(function ($sql) use (&$armed): void {
      if ($armed && str_starts_with(strtolower(ltrim($sql)), 'insert') && str_contains($sql, 'wbcms_page_revisions')) {
        $armed = false;
        throw new \RuntimeException('Synthetic creation failure');
      }
    });
    $this->postJson(route('admin.pages.store'), $input)->assertStatus(500);
    $this->assertFalse($armed);
    foreach ($tables as $index => $table) {
      $this->assertDatabaseCount($table, $counts[$index]);
    }
    $this->getJson($this->url($session, $identity))->assertOk()->assertJsonPath('exists', true)->assertJsonPath('entries.0.value', base64_encode('Retained draft'));
    $this->assertDatabaseHas('wbcms_editor_recoveries', ['editing_session' => $session, 'cleared' => false, 'completed_page_id' => null]);

    $this->postJson(route('admin.pages.store'), $input)->assertOk()->assertJsonPath('saved', true);
    $draft = Page::query()->whereKeyNot($this->page->id)->sole();
    $this->assertSame(Page::STATUS_DRAFT, $draft->status);
    $this->assertSame($this->actor->id, $draft->created_by_user_id);
    $translation = $draft->translations()->sole();
    $this->assertSame([$this->english->id, 'Retained draft', '/retained-draft'], [$translation->locale_id, $translation->name, $translation->path]);
    $slot = $draft->slots()->sole();
    $this->assertSame([$this->slot->id, 2, PageSlot::SOURCE_TYPE_PAGE], [$slot->slot_type_id, $slot->sort_order, $slot->source_type]);
    $this->assertSame('<p>Initial draft copy</p>', $draft->blocks()->sole()->textTranslations()->sole()->content);
    $this->assertSame('page_created', $draft->revisions()->sole()->event);
    $this->assertDatabaseHas('wbcms_editor_recoveries', ['editing_session' => $session, 'cleared' => true, 'completed_page_id' => $draft->id]);
  }

  #[Test]
  public function page_settings_keep_the_assigned_inactive_layout_without_allowing_it_on_new_pages(): void
  {
    PageLayout::query()->updateOrCreate(['handle' => 'docs'], ['name' => 'Docs', 'is_active' => false]);
    $this->page->update(['settings' => ['public_shell' => 'docs']]);
    $input = ['site_id' => $this->site->id, 'title' => 'Retitled page', 'public_shell' => 'dashboard'];

    $this->put(route('admin.pages.update', $this->page), $input + ['slug' => $this->page->slug])->assertRedirect();
    $this->assertSame('Retitled page', $this->page->fresh()->defaultTranslation()->name);
    $this->assertSame('docs', $this->page->fresh()->publicShellPreset());

    $this->postJson(route('admin.pages.store'), $input + ['slug' => 'other-page'])
      ->assertUnprocessable()->assertJsonValidationErrors('public_shell');
  }

  #[Test]
  public function panel_and_api_only_add_children_under_a_parent_in_the_destination_slot(): void
  {
    $aside = SlotType::create(['slug' => 'aside', 'name' => 'Aside', 'status' => 'published']);
    $this->page->slots()->create(['slot_type_id' => $aside->id, 'source_type' => PageSlot::SOURCE_TYPE_PAGE]);
    $container = BlockType::create(['slug' => 'container', 'name' => 'Container', 'status' => 'published']);
    $parent = $this->page->blocks()->create([
      'slot_type_id' => $this->slot->id, 'block_type_id' => $container->id, 'type' => 'container', 'status' => 'draft',
    ]);
    $form = [
      'page_id' => $this->page->id, 'slot_type_id' => $aside->id, 'block_type_id' => $this->type->id,
      'parent_id' => $parent->id, 'sort_order' => 0, 'status' => 'draft', 'content' => '<p>Panel child</p>',
    ];
    $this->postJson(route('admin.blocks.store'), $form)->assertUnprocessable()->assertJsonValidationErrors('parent_id');

    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic placement test', capabilities: ['content.apply'])->plainToken;
    $payload = ['type' => 'rich-text', 'parent_id' => $parent->id, 'translations' => ['content' => '<p>API child</p>']];
    $this->withToken($token)->postJson(route('internal-content-api.pages.slots.blocks.store', [$this->page, 'aside', 'locale' => 'en']), $payload)
      ->assertUnprocessable()->assertJsonPath('errors.0.path', 'block.parent_id');

    $this->assertDatabaseCount('wbcms_blocks', 1);
    $this->assertDatabaseEmpty('wbcms_page_revisions');

    $api = $this->postJson(route('internal-content-api.pages.slots.blocks.store', [$this->page, 'main', 'locale' => 'en']), $payload)->assertCreated();
    $this->flushHeaders();
    $this->post(route('admin.blocks.store'), ['slot_type_id' => $this->slot->id] + $form)->assertRedirect();
    $this->assertSame([$this->slot->id, $this->slot->id], $parent->children()->pluck('slot_type_id')->all());
    $this->assertTrue($parent->children()->whereKey($api->json('block.id'))->exists());
  }

  #[Test]
  public function panel_and_api_share_split_capacity_and_the_panel_can_retype_an_existing_child(): void
  {
    $split = BlockType::create(['slug' => 'split', 'name' => 'Split', 'status' => 'published']);
    $header = BlockType::create(['slug' => 'header', 'name' => 'Header', 'status' => 'published']);
    $this->post(route('admin.blocks.store'), [
      'page_id' => $this->page->id, 'slot_type_id' => $this->slot->id,
      'block_type_id' => $split->id, 'sort_order' => 0, 'status' => 'draft',
    ])->assertRedirect()->assertSessionHasNoErrors();
    $parent = $this->page->blocks()->where('block_type_id', $split->id)->sole();
    $this->assertSame(0, $parent->children()->count());
    $form = [
      'page_id' => $this->page->id, 'slot_type_id' => $this->slot->id, 'block_type_id' => $this->type->id,
      'parent_id' => $parent->id, 'sort_order' => 0, 'status' => 'draft', 'content' => '<p>Panel child</p>',
    ];
    $this->post(route('admin.blocks.store'), $form)->assertRedirect()->assertSessionHasNoErrors();

    $token = app(CmsApiTokenIssuer::class)->issue('Synthetic Split test', capabilities: ['content.apply'])->plainToken;
    $endpoint = route('internal-content-api.pages.slots.blocks.store', [$this->page, 'main', 'locale' => 'en']);
    $payload = ['type' => 'rich-text', 'parent_id' => $parent->id, 'translations' => ['content' => '<p>API child</p>']];
    $child = $this->withToken($token)->postJson($endpoint, $payload)->assertCreated()->json('block.id');
    $this->postJson($endpoint, $payload)->assertUnprocessable()->assertJsonPath('errors.0.path', 'block.parent_id');
    $this->flushHeaders();
    $this->postJson(route('admin.blocks.store'), $form)->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    $this->assertSame(2, $parent->children()->count());
    $this->assertSame(3, $this->page->revisions()->count());

    $this->put(route('admin.blocks.update', $child), [
      'block_type_id' => $header->id, 'text' => 'Retyped child', 'level' => 'h2', 'sort_order' => 1,
    ] + $form)->assertRedirect()->assertSessionHasNoErrors();
    $this->assertSame($header->id, $parent->children()->findOrFail($child)->block_type_id);
    $this->assertSame(2, $parent->children()->count());
  }

  #[Test]
  public function the_panel_exchanges_children_between_full_splits_in_one_save(): void
  {
    $type = BlockType::create(['slug' => 'split', 'name' => 'Split', 'status' => 'published']);
    $slot = $this->page->slots()->sole();
    $block = ['slot_type_id' => $this->slot->id, 'status' => 'draft'];
    $source = $this->page->blocks()->create(['block_type_id' => $type->id] + $block);
    $destination = $this->page->blocks()->create(['block_type_id' => $type->id] + $block);
    $child = ['block_type_id' => $this->type->id] + $block;
    $outgoing = $source->children()->create(['page_id' => $this->page->id, 'sort_order' => 1] + $child);
    $staying = $source->children()->create(['page_id' => $this->page->id, 'sort_order' => 0] + $child);
    $incoming = $destination->children()->create(['page_id' => $this->page->id, 'sort_order' => 0] + $child);
    $destination->children()->create(['page_id' => $this->page->id, 'sort_order' => 1] + $child);

    $this->get(route('admin.pages.slots.blocks', [$this->page, $slot, 'edit' => $outgoing->id]))
      ->assertOk()->assertSee('name="replacement_block_id"', false);
    $url = route('admin.blocks.update', $outgoing);
    $form = [
      'page_id' => $this->page->id, 'slot_type_id' => $this->slot->id, 'block_type_id' => $this->type->id,
      'parent_id' => null, 'sort_order' => 9, 'status' => 'draft', 'content' => '<p>Moving side</p>',
    ];
    $this->putJson($url, $form)->assertUnprocessable()->assertJsonValidationErrors('parent_id');
    $form['parent_id'] = $destination->id;
    $this->putJson($url, $form + ['replacement_block_id' => $staying->id])
      ->assertUnprocessable()->assertJsonValidationErrors('replacement_block_id');
    $this->assertSame($source->id, $outgoing->fresh()->parent_id);
    $this->assertSame($destination->id, $incoming->fresh()->parent_id);
    $this->assertSame(0, $this->page->revisions()->count());

    $exchange = $form + ['replacement_block_id' => $incoming->id];
    $interrupted = false;
    DB::connection()->beforeExecuting(function ($sql, $bindings) use ($outgoing, &$interrupted): void {
      if (! $interrupted && str_starts_with($sql, 'update "wbcms_blocks"') && end($bindings) === $outgoing->id) {
        $interrupted = true;
        throw new \RuntimeException('Synthetic exchange interruption');
      }
    });
    $this->putJson($url, $exchange)->assertInternalServerError();
    $this->assertTrue($interrupted);
    $this->assertSame($source->id, $outgoing->fresh()->parent_id);
    $this->assertSame($destination->id, $incoming->fresh()->parent_id);
    $this->assertSame(0, $this->page->revisions()->count());

    $this->put($url, $exchange)->assertRedirect()->assertSessionHasNoErrors();
    $this->assertSame([$destination->id, 0], [$outgoing->fresh()->parent_id, $outgoing->fresh()->sort_order]);
    $this->assertSame([$source->id, 1], [$incoming->fresh()->parent_id, $incoming->fresh()->sort_order]);
    $this->assertSame(2, $source->children()->count());
    $this->assertSame(2, $destination->children()->count());
    $this->assertSame(1, $this->page->revisions()->count());
  }

  #[Test]
  public function page_settings_validate_new_paths_and_keep_an_existing_custom_path(): void
  {
    $this->page->defaultTranslation()->update(['path' => '/occupied']);
    $input = ['site_id' => $this->site->id, 'title' => 'New page'];

    $this->postJson(route('admin.pages.store'), [...$input, 'slug' => 'webadmin'])
      ->assertUnprocessable()->assertJsonValidationErrors('path');
    $this->postJson(route('admin.pages.store'), [...$input, 'slug' => 'occupied'])
      ->assertUnprocessable()->assertJsonValidationErrors('path');
    $this->postJson(route('admin.pages.store'), [...$input, 'slug' => $this->page->slug])
      ->assertUnprocessable()->assertJsonValidationErrors('slug');
    $this->postJson(route('admin.pages.store'), [...$input, 'slug' => str_repeat('a', 255)])
      ->assertUnprocessable()->assertJsonValidationErrors('path');
    $this->assertSame(1, Page::query()->where('site_id', $this->site->id)->count());

    $this->put(route('admin.pages.update', $this->page), [...$input, 'slug' => $this->page->slug])->assertRedirect();
    $this->assertSame('New page', $this->page->fresh()->defaultTranslation()->name);
    $this->assertSame('/occupied', $this->page->fresh()->defaultTranslation()->path);
  }

  #[Test]
  public function a_new_block_preserves_rich_text_and_creates_only_once(): void
  {
    $identity = $this->identity('new-block');
    $session = (string) Str::uuid();
    $content = '<p>Türkçe <strong>yeni içerik</strong></p>';
    $metadata = $this->checkpoint($session, $identity, ['content' => $content, 'block_type_id' => (string) $this->type->id, 'slot_type_id' => (string) $this->slot->id]);
    $this->assertSame(0, Block::count());
    $restore = $this->postJson(strtok($this->url($session, $identity), '?').'/restore', $identity)->assertOk();
    $this->get($restore->json('redirect'))->assertOk()->assertSee(e($content), false)->assertSee('data-form-context=', false);
    $input = ['page_id' => $this->page->id, 'block_type_id' => $this->type->id, 'slot_type_id' => $this->slot->id, 'sort_order' => 0, 'status' => 'draft', 'content' => $content, ...$metadata];
    $metadata['_editor_recovery_base'] = DB::table('wbcms_editor_recoveries')->where('editing_session', $session)->value('base_fingerprint');
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->postJson(route('admin.blocks.store'), [...$input, ...$metadata])->assertOk()->assertJsonPath('saved', true);
    $this->assertSame(1, Block::count());
    $this->assertSame($content, Block::firstOrFail()->textTranslations()->firstOrFail()->content);
    $this->postJson(route('admin.blocks.store'), [...$input, ...$metadata])->assertGone();
    $this->assertSame(1, Block::count());
    $this->getJson($this->url($session, $identity))->assertOk()->assertJsonPath('completed', true);
  }

  #[Test]
  public function translation_creation_and_seo_editing_preserve_each_locale_and_clear_only_their_form(): void
  {
    $identity = $this->identity('new-translation');
    $session = (string) Str::uuid();
    $values = ['name' => 'Yeni sayfa', 'slug' => 'yeni-sayfa', 'path' => '/tr/yeni-sayfa', 'seo_description' => 'Türkçe açıklama', 'list_excerpt' => 'Bağımsız liste metni'];
    $metadata = $this->checkpoint($session, $identity, $values);
    $restore = $this->postJson(strtok($this->url($session, $identity), '?').'/restore', $identity)->assertOk();
    $this->get($restore->json('redirect'))->assertOk()->assertSee('Türkçe açıklama')->assertSee('value="Yeni sayfa"', false);
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->postJson(route('admin.pages.translations.store', [$this->page, $this->turkish]), [...$values, ...$metadata])->assertOk()->assertJsonPath('saved', true);
    $this->assertSame('Saved page', $this->page->fresh()->defaultTranslation()->name);
    $this->assertSame('Türkçe açıklama', $this->page->translations()->where('locale_id', $this->turkish->id)->firstOrFail()->seo_description);
    $this->postJson(route('admin.pages.translations.store', [$this->page, $this->turkish]), [...$values, ...$metadata, 'slug' => 'replayed-translation', 'path' => '/tr/replayed-translation'])->assertGone();
    $identity = $this->identity('translation');
    $session = (string) Str::uuid();
    $metadata = $this->checkpoint($session, $identity, ['name' => 'Updated English', 'seo_description' => 'English search copy']);
    $translation = $this->page->fresh()->defaultTranslation();
    $input = ['name' => 'Updated English', 'slug' => 'updated-english', 'path' => '/updated-english', 'seo_description' => 'English search copy', ...$metadata];
    $this->putJson(route('admin.pages.translations.update', [$this->page, $translation]), [...$input, 'path' => '/webadmin'])->assertUnprocessable();
    $this->getJson($this->url($session, $identity))->assertOk()->assertJsonPath('exists', true);
    $this->putJson(route('admin.pages.translations.update', [$this->page, $translation]), $input)->assertOk()->assertJsonPath('saved', true);
    $this->assertSame('English search copy', $translation->fresh()->seo_description);
    $this->assertSame('Türkçe açıklama', $this->page->translations()->where('locale_id', $this->turkish->id)->firstOrFail()->seo_description);
  }

  #[Test]
  public function explicit_social_image_removal_is_restored_as_empty(): void
  {
    $media = Media::create(['disk' => 'public', 'path' => 'synthetic-og.svg', 'filename' => 'synthetic-og.svg', 'mime_type' => 'image/svg+xml', 'size' => 100, 'kind' => 'image', 'visibility' => 'public', 'title' => 'Saved social image']);
    $this->page->defaultTranslation()->update(['og_image_media_id' => $media->id]);
    $identity = $this->identity('translation');
    $session = (string) Str::uuid();
    $this->checkpoint($session, $identity, ['og_image_media_id' => null, 'seo_description' => 'Kept description']);
    $restore = $this->postJson(strtok($this->url($session, $identity), '?').'/restore', $identity)->assertOk();
    $response = $this->get($restore->json('redirect'))->assertOk();
    $document = new \DOMDocument;
    @$document->loadHTML($response->getContent());
    $input = (new \DOMXPath($document))->query('//*[@id="og_image_media_id"]')->item(0);
    $this->assertSame('', $input->getAttribute('value'));
    $this->assertSame('', $input->getAttribute('data-wb-picker-selected-title'));
    $this->assertSame($media->id, $this->page->defaultTranslation()->fresh()->og_image_media_id);
  }

  #[Test]
  public function a_translation_form_reports_invalid_path_and_seo_fields_without_saving(): void
  {
    $translation = $this->page->defaultTranslation();
    $media = Media::create([
      'disk' => 'public', 'path' => 'synthetic.pdf', 'filename' => 'synthetic.pdf',
      'mime_type' => 'application/pdf', 'size' => 100, 'kind' => 'document', 'visibility' => 'public',
    ]);

    $this->putJson(route('admin.pages.translations.update', [$this->page, $translation]), [
      'name' => 'Changed name', 'slug' => 'changed-name', 'path' => '/invalid path',
      'seo_description' => str_repeat('x', 1001), 'og_image_media_id' => $media->id,
    ])->assertUnprocessable()->assertJsonValidationErrors(['path', 'seo_description', 'og_image_media_id']);

    $this->assertSame('Saved page', $translation->refresh()->name);
    $this->assertNull($translation->og_image_media_id);
  }

  #[Test]
  public function owner_site_kind_language_and_legacy_contexts_cannot_cross(): void
  {
    $session = (string) Str::uuid();
    $identity = $this->identity();
    $this->checkpoint($session, $identity, ['title' => 'Private unfinished value']);
    $otherSite = Site::create(['name' => 'Other synthetic site', 'handle' => 'other-forms']);
    $otherSite->locales()->syncWithoutDetaching([$this->english->id => ['is_enabled' => true]]);
    $this->getJson($this->url($session, [...$identity, 'site_id' => $otherSite->id]))->assertNotFound();
    $this->getJson($this->url($session, $this->identity('new-block')))->assertNotFound();
    $this->getJson(route('admin.pages.recovery.show', [$this->page, $session, 'locale' => 'en']))->assertNotFound();
    $other = User::forceCreate(['name' => 'Other operator', 'email' => 'other-forms@example.test', 'password' => 'unused', 'role' => 'super_admin', 'is_active' => true]);
    $this->actingAs($other)->getJson($this->url($session, $identity))->assertOk()->assertJsonPath('exists', false)->assertDontSee(base64_encode('Private unfinished value'));
    $this->actingAs($this->actor);
    $restore = $this->postJson(strtok($this->url($session, $identity), '?').'/restore', $identity)->assertOk();
    $url = str_replace('site='.$this->site->id, 'site='.$otherSite->id, $restore->json('redirect'));
    $this->get($url)->assertNotFound()->assertSessionMissing('_old_input');
    $this->get($restore->json('redirect'))->assertOk();
  }

  #[Test]
  public function changed_page_content_requires_conflict_review_before_new_block_save(): void
  {
    $identity = $this->identity('new-block');
    $session = (string) Str::uuid();
    $metadata = $this->checkpoint($session, $identity, ['content' => '<p>Retained new block</p>']);
    $this->page->forceFill(['settings' => ['public_shell' => 'docs']])->save();
    $this->getJson($this->url($session, $identity))->assertOk()->assertJsonPath('conflict', true);
    $this->postJson(strtok($this->url($session, $identity), '?').'/restore', $identity)->assertConflict();
    $this->postJson(strtok($this->url($session, $identity), '?').'/restore', [...$identity, 'reviewed_conflict' => true])->assertOk();
    $input = ['page_id' => $this->page->id, 'block_type_id' => $this->type->id, 'slot_type_id' => $this->slot->id, 'sort_order' => 0, 'status' => 'draft', 'content' => '<p>Retained new block</p>', ...$metadata];
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->postJson(route('admin.blocks.store'), $input)->assertUnprocessable()->assertJsonValidationErrors('_editor_recovery_base');
    $this->assertSame(0, Block::count());
  }

  #[Test]
  public function field_allowlists_and_recovery_size_limits_are_enforced(): void
  {
    $identity = $this->identity();
    $session = (string) Str::uuid();
    $base = $this->getJson($this->url($session, $identity))->assertOk()->json('base_fingerprint');
    foreach (['password', 'site_id', 'seo_description', 'title'] as $name) {
      $value = $name === 'title' ? str_repeat('x', 262145) : 'not allowed';
      $this->putJson($this->url($session, $identity), [...$identity, '_checkpoint' => '1', 'sequence' => 1, 'base_fingerprint' => $base, 'entries' => [['name' => $name, 'present' => true, 'value' => base64_encode($value)]]])->assertUnprocessable();
    }
    $this->assertDatabaseCount('wbcms_editor_recoveries', 0);
  }

  #[Test]
  public function revoked_create_and_seo_permissions_prevent_checkpoint_writes(): void
  {
    $role = app(WriteCmsAccess::class)->role($this->actor, ['name' => 'Synthetic form writer', 'permissions' => ['cms.panel.access', 'cms.pages.read', 'cms.pages.create', 'cms.pages.update-draft', 'cms.translations.update']]);
    $writer = User::forceCreate(['name' => 'Restricted writer', 'email' => 'restricted-forms@example.test', 'password' => 'unused', 'role' => 'editor', 'is_active' => true]);
    $writer->sites()->attach($this->site->id, ['role_id' => $role->id]);
    $this->actingAs($writer);
    $identity = $this->identity('translation');
    $session = (string) Str::uuid();
    $base = $this->getJson($this->url($session, $identity))->assertOk()->json('base_fingerprint');
    $this->putJson($this->url($session, $identity), [...$identity, '_checkpoint' => '1', 'sequence' => 1, 'base_fingerprint' => $base, 'entries' => [['name' => 'seo_description', 'present' => true, 'value' => base64_encode('Unauthorized SEO')]]])->assertForbidden();
    $identity = $this->identity();
    $base = $this->getJson($this->url($session, $identity))->assertOk()->json('base_fingerprint');
    $revoked = false;
    DB::connection()->beforeExecuting(function ($sql) use ($role, &$revoked): void {
      if (! $revoked && str_contains($sql, 'wbcms_acl_locks')) {
        $revoked = true;
        DB::table('wbcms_acl_role_permissions')->where('role_id', $role->id)->where('permission', 'cms.pages.create')->delete();
      }
    });
    $this->putJson($this->url($session, $identity), [...$identity, '_checkpoint' => '1', 'sequence' => 1, 'base_fingerprint' => $base, 'entries' => [['name' => 'title', 'present' => true, 'value' => base64_encode('Revoked value')]]])->assertForbidden();
    $this->assertTrue($revoked);
    $this->assertDatabaseCount('wbcms_editor_recoveries', 0);
  }

  #[Test]
  public function deleted_or_disabled_contexts_and_mismatched_block_fields_cannot_be_recovered(): void
  {
    $identity = $this->identity('new-block');
    $session = (string) Str::uuid();
    $base = $this->getJson($this->url($session, $identity))->assertOk()->json('base_fingerprint');
    $payload = [...$identity, '_checkpoint' => '1', 'sequence' => 1, 'base_fingerprint' => $base, 'entries' => [['name' => 'slot_type_id', 'present' => true, 'value' => base64_encode('999999')]]];
    $this->putJson($this->url($session, $identity), $payload)->assertUnprocessable();
    $this->assertDatabaseCount('wbcms_editor_recoveries', 0);
    $this->checkpoint($session, $identity, ['content' => '<p>Retained</p>']);
    $this->slot->update(['status' => 'draft']);
    $this->getJson($this->url($session, $identity))->assertNotFound();
    $this->slot->update(['status' => 'published']);
    $translationIdentity = $this->identity('new-translation');
    $translationSession = (string) Str::uuid();
    $this->checkpoint($translationSession, $translationIdentity, ['name' => 'Saklanan çeviri']);
    $this->site->locales()->updateExistingPivot($this->turkish->id, ['is_enabled' => false]);
    $this->getJson($this->url($translationSession, $translationIdentity))->assertNotFound();
    $this->page->delete();
    $this->getJson($this->url($session, $identity))->assertNotFound();
  }

  #[Test]
  public function native_create_requests_keep_redirects_and_discarded_intents_cannot_be_reused(): void
  {
    $this->post(route('admin.pages.store'), ['site_id' => $this->site->id, 'title' => 'Native page', 'slug' => 'native-page', 'public_shell' => ''])->assertRedirect();
    $identity = $this->identity();
    $session = (string) Str::uuid();
    $metadata = $this->checkpoint($session, $identity, ['title' => 'Discarded form']);
    $this->deleteJson($this->url($session, $identity), $identity)->assertOk();
    $this->getJson($this->url($session, $identity))->assertGone();
    $count = Page::count();
    $this->withHeader('X-WebBlocks-Editor-Save', '1')->postJson(route('admin.pages.store'), ['site_id' => $this->site->id, 'title' => 'Discarded form', 'slug' => 'discarded-form', ...$metadata])->assertGone();
    $this->assertSame($count, Page::count());
    $this->assertDatabaseCount('wbcms_editor_recovery_values', 0);
  }

  #[Test]
  public function new_contexts_share_the_existing_session_budget_and_expiry(): void
  {
    $identity = $this->identity();
    $session = (string) Str::uuid();
    $this->checkpoint($session, $identity, ['title' => 'Expiring new page']);
    $base = DB::table('wbcms_editor_recoveries')->value('base_fingerprint');
    for ($index = 0; $index < 31; $index++) {
      DB::table('wbcms_editor_recoveries')->insert(['user_id' => $this->actor->id, 'page_id' => $this->page->id, 'site_id' => $this->site->id, 'editing_session' => (string) Str::uuid(), 'locale' => 'en', 'base_fingerprint' => $base, 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now()]);
    }
    $other = (string) Str::uuid();
    $this->putJson($this->url($other, $identity), [...$identity, '_checkpoint' => '1', 'sequence' => 1, 'base_fingerprint' => $base, 'entries' => [['name' => 'title', 'present' => true, 'value' => base64_encode('Over budget')]]])->assertStatus(429);
    $this->assertDatabaseCount('wbcms_editor_recoveries', 32);
    $this->travel(8)->days();
    $this->getJson($this->url($session, $identity))->assertGone();
    $this->artisan('webblocks:editor-recovery:prune')->assertSuccessful();
    $this->assertDatabaseCount('wbcms_editor_recoveries', 0);
    $this->assertDatabaseCount('wbcms_editor_recovery_values', 0);
    $this->assertTrue(Schema::hasColumn('wbcms_editor_recoveries', 'form_kind'));
  }
}

class EditorFormRecoveryTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey()
  {
  return 'user_id';
  }
}
