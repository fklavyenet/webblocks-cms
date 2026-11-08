<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockTextTranslation;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\EmbeddedApplication;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;
use WebBlocks\Cms\Support\Blocks\BlockTranslationWriter;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Support\PublicRendering\RequestMemo;
use WebBlocks\Cms\Support\Relational\StructuredSettings;
use WebBlocks\Cms\Support\Revisions\RestoredBlockReads;
use WebBlocks\Cms\Support\Search\PublicSearchIndexer;
use WebBlocks\Cms\Tests\CmsSqlTestUser;
use WebBlocks\Cms\Tests\TestCase;

class CanonicalNormalizationNoopTest extends TestCase
{
  private bool $watch = false;

  private array $writes = [];

  private int $blockReads = 0;

  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  protected function setUp(): void
  {
    if (! class_exists('App\\Models\\User')) {
      class_alias(CanonicalNoopTestUser::class, 'App\\Models\\User');
    }
    parent::setUp();
    DB::listen(function ($query): void {
      if (! $this->watch) {
        return;
      }
      $this->blockReads += (int) preg_match('/^select .*from ["`]wbcms_blocks["`]/i', $query->sql);
      if (preg_match('/^(?:insert into|update|delete from) ["`]?([a-z_]+)/i', $query->sql, $match)) {
        $this->writes[$match[1]] = ($this->writes[$match[1]] ?? 0) + 1;
      }
    });
  }

  public static function families(): array
  {
    return [['plain_text'], ['button'], ['image'], ['contact_form'], ['example-plugin-banner']];
  }

  #[Test]
  #[DataProvider('families')]
  public function clean_canonical_families_do_not_write_or_repeat_the_block_lock(string $type): void
  {
    [, $block] = $this->fixture($type);
    $this->watch = true;
    $this->normalize($block, $type === 'example-plugin-banner');
    $this->watch = false;
    $this->assertSame([], $this->writes);
    $this->assertSame(1, $this->blockReads, 'Only the authoritative restore hydration remains.');
  }

  public static function missingDefaultCopy(): array
  {
    return [[null], ['0']];
  }

  #[Test]
  #[DataProvider('missingDefaultCopy')]
  public function missing_default_rows_still_run_model_events_and_preserve_legacy_zero(?string $legacy): void
  {
    [, $block, $locales] = $this->fixture();
    $block->textTranslations()->where('locale_id', $locales[0]->id)->delete();
    DB::table('wbcms_blocks')->where('id', $block->id)->update(['content' => $legacy]);
    $events = [];
    foreach (['saving', 'creating', 'created', 'saved'] as $event) {
      BlockTextTranslation::{$event}(function () use (&$events, $event): void {
        $events[] = $event;
      });
    }
    $this->normalize($block);
    $row = $block->textTranslations()->where('locale_id', $locales[0]->id)->sole();
    $this->assertSame($legacy, $row->content);
    $this->assertSame(['saving', 'creating', 'created', 'saved'], $events);
    $this->assertNull($block->fresh()->getRawOriginal('content'));
    $this->assertSame('Saved tr', $block->textTranslations()->where('locale_id', $locales[1]->id)->value('content'));
  }

  #[Test]
  public function an_unavailable_default_locale_still_fails_validation_before_any_canonical_save(): void
  {
    [$page, $block, $locales] = $this->fixture();
    $block->textTranslations()->where('locale_id', $locales[0]->id)->delete();
    DB::table('wbcms_site_locales')->where('site_id', $page->site_id)->where('locale_id', $locales[0]->id)->update(['is_enabled' => false]);
    try {
      $this->normalize($block);
      $this->fail('A clean canonical block cannot bypass default translation validation.');
    } catch (RuntimeException $exception) {
      $this->assertSame('Block translation locale must be enabled for the block page site.', $exception->getMessage());
    }
    $this->assertFalse($block->textTranslations()->where('locale_id', $locales[0]->id)->exists());
  }

  #[Test]
  public function dirty_in_memory_canonical_values_keep_the_original_save_path(): void
  {
    [, $block] = $this->fixture();
    DB::table('wbcms_blocks')->where('id', $block->id)->update(['content' => 'Legacy']);
    $reads = app(RestoredBlockReads::class);
    $this->watch = true;
    DB::transaction(fn () => $reads->duringBlock($block->id, function (Block $restored) use ($reads): void {
      $target = $reads->forNormalization($restored, false);
      $target->content = null;
      $this->assertTrue($target->isDirty('content'));
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($target);
    }));
    $this->watch = false;
    $this->assertGreaterThan(0, $this->writes['wbcms_block_application_references'] ?? 0);
    $this->assertNull($block->fresh()->getRawOriginal('content'));
  }

  #[Test]
  public function sql_changes_during_default_resolution_cannot_be_hidden_by_the_noop_guard(): void
  {
    [, $block] = $this->fixture();
    $changed = false;
    app(RequestMemo::class)->clear();
    DB::listen(function ($query) use (&$changed, $block): void {
      if (! $changed && str_starts_with($query->sql, 'select * from "wbcms_locales"') && str_contains($query->sql, 'order by case')) {
        $changed = true;
        DB::table('wbcms_blocks')->where('id', $block->id)->update(['content' => 'Observer SQL']);
      }
    });
    $this->watch = true;
    $this->normalize($block);
    $this->watch = false;
    $this->assertTrue($changed);
    $this->assertSame(1, $this->writes['wbcms_block_application_references'] ?? 0, 'The native quiet-save path remains active after the observer SQL.');
    $this->assertSame('Observer SQL', $block->fresh()->getRawOriginal('content'));
  }

  #[Test]
  public function existing_application_handles_still_refresh_their_resolved_sql_reference(): void
  {
    [, $block] = $this->fixture('plain_text', '{"application_handle":"canonical-app"}');
    $application = EmbeddedApplication::create(['name' => 'Canonical app', 'handle' => 'canonical-app', 'version' => '1.0.0', 'render_mode' => 'inline']);
    $this->assertNull(DB::table('wbcms_block_application_references')->where('block_id', $block->id)->value('resolved_application_id'));
    $this->normalize($block);
    $this->assertSame($application->id, DB::table('wbcms_block_application_references')->where('block_id', $block->id)->value('resolved_application_id'));
  }

  public static function settingsFamilies(): array
  {
    return [['contact_form'], ['example-plugin-banner']];
  }

  #[Test]
  #[DataProvider('settingsFamilies')]
  public function translated_settings_are_removed_before_later_normalizations_become_noops(string $type): void
  {
    $settings = ['alignment' => 'left', 'opaque' => [false, null, 1, '1']];
    $translated = $type === 'contact_form'
      ? ['submit_label' => 'Send', 'success_message' => 'Thanks', 'consent_label' => 'Accept']
      : ['heading' => 'Heading', 'intro' => 'Intro'];
    [, $block] = $this->fixture($type, json_encode($settings + $translated));
    $this->watch = true;
    $this->normalize($block, $type === 'example-plugin-banner');
    $this->watch = false;
    $this->assertNotSame([], $this->writes);
    $this->assertSame($settings, $block->fresh()->decodedSettings());
    $this->writes = [];
    $this->watch = true;
    $this->normalize($block, $type === 'example-plugin-banner');
    $this->watch = false;
    $this->assertSame([], $this->writes);
  }

  public static function arraySettingsFamilies(): array
  {
    return [['plain_text'], ['contact_form']];
  }

  #[Test]
  #[DataProvider('arraySettingsFamilies')]
  public function an_array_settings_format_still_converts_through_the_normal_relational_writer(string $type): void
  {
    [, $block] = $this->fixture($type, ['alignment' => 'left']);
    app(StructuredSettings::class)->write($block, 'settings', ['alignment' => 'left']);
    $this->assertSame('value', DB::table('wbcms_setting_documents')->where('block_id', $block->id)->value('format'));
    $this->normalize($block);
    $this->assertSame('json', DB::table('wbcms_setting_documents')->where('block_id', $block->id)->value('format'));
    $this->assertSame(['alignment' => 'left'], $block->fresh()->decodedSettings());
  }

  #[Test]
  public function read_observers_and_unscoped_calls_retain_the_original_quiet_save(): void
  {
    [, $block] = $this->fixture();
    $retrieved = 0;
    Block::retrieved(function () use (&$retrieved): void {
      $retrieved++;
    });
    $this->watch = true;
    $this->normalize($block);
    $this->watch = false;
    $this->assertSame(2, $retrieved);
    $this->assertSame(3, $this->blockReads);
    $this->assertSame(1, $this->writes['wbcms_block_application_references'] ?? 0);
    $this->writes = [];
    $this->watch = true;
    app(BlockTranslationWriter::class)->normalizeCanonicalStorage($block->fresh());
    $this->watch = false;
    $this->assertSame(1, $this->writes['wbcms_block_application_references'] ?? 0);
  }

  private function normalize(Block $block, bool $legacyFallback = false): void
  {
    $reads = app(RestoredBlockReads::class);
    DB::transaction(fn () => $reads->duringBlock($block->id, function (Block $restored) use ($reads, $legacyFallback): void {
      app(BlockTranslationWriter::class)->normalizeCanonicalStorage($reads->forNormalization($restored, $legacyFallback));
    }));
  }

  private function fixture(string $type = 'plain_text', mixed $settings = '{"alignment":"left","opaque":[false,null,1,"1"]}'): array
  {
    if ($type === 'example-plugin-banner') {
      $registry = new PluginRegistry(['example-plugin' => true]);
      $registry->register(PluginDefinition::make('example-plugin')->label('Example')->version('1.0.0')->blockTypes([
        PluginBlockTypeDefinition::make('example-plugin::banner')->label('Banner')->translatedFields(['heading', 'intro']),
      ]));
      $this->app->forgetInstance(PluginRegistry::class);
      $this->app->singleton(PluginRegistry::class, fn () => $registry);
      $this->app->forgetInstance(PluginBlockCatalog::class);
      $this->app->forgetInstance(BlockTranslationRegistry::class);
    }

    return PublicSearchIndexer::coalescing(function () use ($type, $settings): array {
      $site = Site::create(['name' => 'Canonical fixture', 'handle' => 'canonical-fixture']);
      $locales = collect(['en', 'tr'])->map(fn ($code) => Locale::create(['code' => $code, 'name' => $code, 'is_default' => $code === 'en', 'is_enabled' => true]));
      $site->locales()->sync($locales->mapWithKeys(fn ($locale) => [$locale->id => ['is_enabled' => true]])->all());
      $blockType = BlockType::create(['name' => $type, 'slug' => $type, 'source_type' => 'static']);
      $page = Page::create(['site_id' => $site->id, 'title' => 'Canonical page', 'status' => 'draft']);
      $block = Block::create(['page_id' => $page->id, 'block_type_id' => $blockType->id, 'type' => $type, 'settings' => $settings, 'title' => null, 'subtitle' => null, 'content' => null, 'meta' => null]);
      foreach ($locales as $locale) {
        $payload = match ($type) {
          'button' => ['title' => 'Open '.$locale->code],
          'image' => ['caption' => 'Caption '.$locale->code, 'alt_text' => 'Alt '.$locale->code],
          'contact_form' => ['title' => 'Contact '.$locale->code, 'content' => 'Body', 'submit_label' => 'Send', 'success_message' => 'Thanks', 'consent_label' => 'Consent'],
          'example-plugin-banner' => ['field' => 'heading', 'value' => 'Heading '.$locale->code],
          default => ['content' => 'Saved '.$locale->code],
        };
        $relation = match ($type) {
          'button' => 'buttonTranslations', 'image' => 'imageTranslations', 'contact_form' => 'contactFormTranslations', 'example-plugin-banner' => 'pluginTranslations', default => 'textTranslations',
        };
        $block->{$relation}()->create(['locale_id' => $locale->id] + $payload);
      }

      return [$page, $block, $locales];
    });
  }
}

class CanonicalNoopTestUser extends CmsSqlTestUser
{
  protected $table = 'users';

  public function getForeignKey(): string
  {
    return 'user_id';
  }
}
