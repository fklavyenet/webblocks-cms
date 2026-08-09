<?php

namespace WebBlocks\Cms\Tests\Feature;

use WebBlocks\Cms\Support\Blocks\BlockTranslationRegistry;
use WebBlocks\Cms\Support\Plugins\PluginBlockCatalog;
use WebBlocks\Cms\Support\Plugins\PluginBlockTypeDefinition;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

/**
 * Core gap 0.4: plugin blocks could not own translatable fields.
 *
 * `BlockTranslationRegistry::familyFor()` was a fixed `match` over core slugs, so a
 * plugin's block had no translation family and no field map — its copy lived in the
 * settings column and was shared across every locale. The appointments plugin worked
 * around it by putting visitor-facing copy in its own translation namespace, which
 * gives a plugin *release* several languages but never gives an *operator* a second
 * language for one block they placed.
 *
 * Every other family is a table of columns because core knows the fields before the
 * migration is written. A plugin's are declared at install time by a package core has
 * never seen, so the plugin family is rows — and these cover the consequences of that
 * choice.
 */
class PluginBlockTranslationTest extends TestCase
{
  protected function setUp(): void
  {
    parent::setUp();

    $this->registerPlugin();
  }

  public function test_a_plugin_block_declaring_fields_gets_a_translation_family(): void
  {
    $registry = app(BlockTranslationRegistry::class);

    $this->assertSame(BlockTranslationRegistry::PLUGIN_FAMILY, $registry->familyFor('example-plugin-banner'));
    $this->assertTrue($registry->isTranslatable('example-plugin-banner'));
  }

  public function test_the_declared_fields_are_what_the_registry_reports(): void
  {
    /*
     * The reason `translatedFieldsFor()` exists alongside `translatedFieldMap()`:
     * two plugin blocks share one family and have different fields, so the answer
     * depends on the block rather than on the family.
     */
    $this->assertSame(
      ['heading', 'intro', 'button_label'],
      app(BlockTranslationRegistry::class)->translatedFieldsFor('example-plugin-banner'),
    );
  }

  public function test_a_plugin_block_declaring_nothing_stays_untranslated(): void
  {
    // Declaring fields is what opts a block in. Most plugin blocks want their copy
    // shared across locales and must not silently acquire per-locale storage.
    $this->assertNull(app(BlockTranslationRegistry::class)->familyFor('example-plugin-plain'));
    $this->assertSame([], app(BlockTranslationRegistry::class)->translatedFieldsFor('example-plugin-plain'));
  }

  public function test_a_disabled_plugins_block_reports_no_family(): void
  {
    /*
     * A disabled plugin contributes nothing anywhere else either. A block still
     * reporting a family would send writes to storage that nothing would ever read
     * back, because the field list that gives those rows meaning is gone with it.
     */
    $this->registerPlugin(enabled: false);

    $this->assertNull(app(BlockTranslationRegistry::class)->familyFor('example-plugin-banner'));
  }

  public function test_core_block_families_are_unchanged(): void
  {
    // The plugin check runs first, so this is the assertion that it did not shadow
    // anything: every core slug must still answer exactly as before.
    $registry = app(BlockTranslationRegistry::class);

    $this->assertSame('text', $registry->familyFor('hero'));
    $this->assertSame('button', $registry->familyFor('button'));
    $this->assertSame('image', $registry->familyFor('image'));
    $this->assertSame('contact_form', $registry->familyFor('contact_form'));
    // 'section' is a real core slug with no family — 'columns' is in the text
    // family, which is what this assertion originally got wrong.
    $this->assertNull($registry->familyFor('section'));

    $this->assertSame(
      ['title', 'eyebrow', 'subtitle', 'content', 'meta'],
      $registry->translatedFieldsFor('hero'),
    );
  }

  public function test_malformed_declared_field_names_are_dropped(): void
  {
    /*
     * A field name becomes a database key, a form input name and part of a public
     * contract. A bad entry in a third-party manifest is dropped rather than
     * rejected, because refusing would stop the whole plugin loading over a typo.
     */
    $block = PluginBlockTypeDefinition::make('example-plugin::banner')
      ->translatedFields(['ok_field', 'Bad-Field', '', '1leading', str_repeat('x', 200), 'ok_field']);

    $this->assertSame(['ok_field'], $block->translatedFieldNames());
  }

  private function registerPlugin(bool $enabled = true): void
  {
    $plugin = PluginDefinition::make('example-plugin')
      ->label('Example')
      ->version('1.0.0')
      ->blockTypes([
        PluginBlockTypeDefinition::make('example-plugin::banner')
          ->label('Banner')
          ->translatedFields(['heading', 'intro', 'button_label']),
        PluginBlockTypeDefinition::make('example-plugin::plain')
          ->label('Plain'),
      ]);

    /*
     * Bound as a singleton factory rather than an instance, matching the block-type
     * catalog tests: a runtime refresh forgets the resolved registry, and an
     * instance binding would not survive it.
     */
    $registry = new PluginRegistry(['example-plugin' => $enabled]);
    $registry->register($plugin);

    $this->app->forgetInstance(PluginRegistry::class);
    $this->app->singleton(PluginRegistry::class, fn (): PluginRegistry => $registry);
    $this->app->forgetInstance(PluginBlockCatalog::class);
    $this->app->forgetInstance(BlockTranslationRegistry::class);
  }
}
