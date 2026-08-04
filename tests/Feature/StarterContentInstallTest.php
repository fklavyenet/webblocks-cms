<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Console\InstallWebBlocksCmsCommand;
use WebBlocks\Cms\Database\Seeders\CoreCatalogSeeder;
use WebBlocks\Cms\Database\Seeders\DatabaseSeeder;
use WebBlocks\Cms\Database\Seeders\FoundationSiteLocaleSeeder;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\Layout;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Media;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageTranslation;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Blocks\BlockTranslationResolver;
use WebBlocks\Cms\Support\Install\DefaultHomepageProvisioner;
use WebBlocks\Cms\Support\Install\StarterContentInstaller;
use WebBlocks\Cms\Support\Install\StarterContentResult;
use WebBlocks\Cms\Support\Install\StarterMediaImporter;
use WebBlocks\Cms\Tests\TestCase;

/**
 * A fresh install used to publish an empty home page: real page, real slots,
 * no blocks. Nothing rendered at `/` and a new admin had nothing to edit.
 * Starter content fills that page once, and only while it is still empty.
 */
class StarterContentInstallTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function the_provisioned_home_page_is_filled_with_published_starter_blocks(): void
  {
    $result = $this->installStarterContent();

    $this->assertTrue($result->installed);
    $this->assertSame([], $result->skippedBlockTypes);

    $blocks = Block::query()->get();

    $this->assertCount($result->blocksCreated, $blocks);
    $this->assertNotEmpty($blocks);

    foreach (['section', 'container', 'image', 'hero', 'feature-grid', 'feature-item', 'cta', 'button_link'] as $type) {
      $this->assertTrue($blocks->contains('type', $type), 'Expected a ['.$type.'] starter block.');
    }

    $this->assertTrue($blocks->every(fn (Block $block) => $block->status === 'published'));
    $this->assertTrue($blocks->every(fn (Block $block) => $block->slot === 'main'));
  }

  #[Test]
  public function starter_copy_is_written_as_locale_owned_translations_not_block_columns(): void
  {
    $this->installStarterContent();

    $hero = Block::query()->where('type', 'hero')->firstOrFail();
    $translation = $hero->textTranslations()->firstOrFail();

    $this->assertNull($hero->getRawOriginal('title'));
    $this->assertNull($hero->getRawOriginal('subtitle'));
    // The brand is the hero's headline, so it renders at title size rather
    // than as the small eyebrow the contract keeps in the subtitle field.
    $this->assertSame('WebBlocks CMS', $translation->title);
    $this->assertSame('Your site is installed', $translation->subtitle);
    $this->assertNotEmpty($translation->content);
  }

  #[Test]
  public function the_starter_hero_renders_its_copy_and_its_action_links(): void
  {
    $this->installStarterContent();

    $hero = Block::query()
      ->where('type', 'hero')
      ->with(['textTranslations', 'children.textTranslations'])
      ->firstOrFail();

    $html = view('webblocks-cms::pages.partials.block', [
      'block' => app(BlockTranslationResolver::class)->resolve($hero),
    ])->render();

    $this->assertStringContainsString('<h1 class="wb-promo-title">WebBlocks CMS</h1>', $html);
    $this->assertStringContainsString('Your site is installed', $html);
    $this->assertStringContainsString('href="/webadmin"', $html);
    $this->assertStringContainsString('Open the admin', $html);
    $this->assertStringContainsString('href="https://cms.webblocksui.com"', $html);
    $this->assertStringContainsString('Read the docs', $html);
  }

  #[Test]
  public function the_whole_starter_page_renders_through_the_public_block_partials(): void
  {
    $page = $this->provisionHomePage();
    app(StarterContentInstaller::class)->install($page);

    $html = '';

    foreach ($this->renderableRootBlocks($page) as $block) {
      $html .= view('webblocks-cms::pages.partials.block', ['block' => $block])->render();
    }

    $this->assertStringContainsString('WebBlocks CMS', $html);
    $this->assertStringContainsString('Where to start', $html);
    $this->assertStringContainsString('Edit this page', $html);
    $this->assertStringContainsString('Add your media', $html);
    $this->assertStringContainsString('Ready to replace this page?', $html);
    $this->assertStringContainsString('href="/webadmin/pages"', $html);
  }

  #[Test]
  public function the_hero_logo_is_served_from_the_sites_own_media_library_not_a_remote_url(): void
  {
    $this->installStarterContent();

    $logo = Block::query()->where('type', 'image')->firstOrFail();
    $media = Media::query()->findOrFail($logo->media_id);

    $this->assertSame('public', $media->disk);
    $this->assertSame('assets/starter/logo-mark.png', $media->path);
    $this->assertSame('image/png', $media->mime_type);
    $this->assertSame(Media::KIND_IMAGE, $media->kind);
    // The image block renders at the file's own pixel size, so the shipped
    // artwork is what keeps the mark at brand size instead of page-wide.
    $this->assertSame(96, (int) $media->width);
    $this->assertSame(96, (int) $media->height);
    $this->assertTrue(Storage::disk('public')->exists($media->path));

    $html = view('webblocks-cms::pages.partials.block', [
      'block' => app(BlockTranslationResolver::class)->resolve($logo),
    ])->render();

    // Served from this site's own origin: a CDN hot-link would make every
    // public visitor issue a third-party request.
    $this->assertStringContainsString('/storage/assets/starter/logo-mark.png', $html);
    $this->assertStringContainsString('width="96"', $html);
    $this->assertStringNotContainsString('webblocksui.com/logo', $html);
  }

  #[Test]
  public function importing_the_same_starter_image_twice_reuses_one_media_record(): void
  {
    $importer = app(StarterMediaImporter::class);
    $source = dirname(__DIR__, 2).'/database/content/starter/media/logo-mark.png';

    $first = $importer->import($source, 'WebBlocks CMS');
    $second = $importer->import($source, 'WebBlocks CMS');

    $this->assertNotNull($first);
    $this->assertSame($first->id, $second?->id);
    $this->assertSame(1, Media::query()->count());
  }

  #[Test]
  public function a_missing_starter_image_leaves_the_block_without_media_instead_of_failing(): void
  {
    $this->assertNull(app(StarterMediaImporter::class)->import('/no/such/file.png', 'Missing'));
    $this->assertSame(0, Media::query()->count());
  }

  #[Test]
  public function nested_blueprint_children_keep_their_parent_and_their_order(): void
  {
    $this->installStarterContent();

    $hero = Block::query()->where('type', 'hero')->firstOrFail();
    $container = Block::query()->findOrFail($hero->parent_id);
    $section = Block::query()->findOrFail($container->parent_id);

    $this->assertSame('container', $container->type);
    $this->assertSame('section', $section->type);
    $this->assertNull($section->parent_id);

    $buttons = Block::query()->where('parent_id', $hero->id)->orderBy('sort_order')->pluck('type')->all();

    $this->assertSame(['button_link', 'button_link'], $buttons);
  }

  #[Test]
  public function a_second_run_does_not_write_the_starter_content_again(): void
  {
    $page = $this->provisionHomePage();
    $installer = app(StarterContentInstaller::class);

    $first = $installer->install($page);
    $blockCount = Block::query()->count();

    $second = $installer->install($page->fresh());

    $this->assertTrue($first->installed);
    $this->assertFalse($second->installed);
    $this->assertSame('The page already has blocks.', $second->reason);
    $this->assertSame($blockCount, Block::query()->count());
  }

  #[Test]
  public function a_page_that_already_has_content_is_never_touched(): void
  {
    $page = $this->provisionHomePage();

    $existing = Block::query()->create([
      'page_id' => $page->id,
      'type' => 'plain_text',
      'source_type' => 'static',
      'slot' => 'main',
      'sort_order' => 0,
      'status' => 'published',
    ]);

    $result = app(StarterContentInstaller::class)->install($page->fresh());

    $this->assertFalse($result->installed);
    $this->assertSame([$existing->id], Block::query()->pluck('id')->all());
  }

  #[Test]
  public function starter_content_can_be_turned_off_without_losing_the_home_page(): void
  {
    config()->set('webblocks-cms.install.starter_content', false);

    $page = $this->provisionHomePage();
    $result = app(StarterContentInstaller::class)->install($page);

    $this->assertFalse($result->installed);
    $this->assertSame(0, Block::query()->count());
    $this->assertSame(Page::STATUS_PUBLISHED, $page->status);
    $this->assertSame('/', PageTranslation::query()->where('page_id', $page->id)->value('path'));
  }

  #[Test]
  public function the_manual_db_seed_install_path_also_lands_on_a_filled_home_page(): void
  {
    $this->seed(DatabaseSeeder::class);

    $homePage = Page::query()->where('status', Page::STATUS_PUBLISHED)->firstOrFail();

    $this->assertSame('/', PageTranslation::query()->where('page_id', $homePage->id)->value('path'));
    $this->assertGreaterThan(0, $homePage->blocks()->count());
    $this->assertTrue($homePage->blocks()->where('type', 'hero')->exists());
  }

  #[Test]
  public function provisioning_a_missing_home_page_never_adopts_a_live_page(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(CoreCatalogSeeder::class);

    $site = Site::query()->firstOrFail();
    $locale = Locale::query()->where('is_default', true)->firstOrFail();

    // A published page an operator made, on a site with no page at "/" — the
    // shape a seeder run on a live site can meet. Matching "first published
    // default page" would adopt this one and rewrite its slug and path.
    $about = Page::query()->create([
      'site_id' => $site->id,
      'page_type' => Page::TYPE_DEFAULT,
      'status' => Page::STATUS_PUBLISHED,
      'layout_id' => Layout::query()->firstOrCreate(['slug' => 'default-layout'], ['name' => 'Default Layout'])->id,
      'published_at' => now(),
    ]);
    PageTranslation::query()->create([
      'page_id' => $about->id,
      'site_id' => $site->id,
      'locale_id' => $locale->id,
      'name' => 'About',
      'slug' => 'about',
      'path' => '/about',
    ]);

    $homePage = app(DefaultHomepageProvisioner::class)->provision($site);

    $this->assertNotSame($about->id, $homePage->id);
    $this->assertSame('/about', PageTranslation::query()->where('page_id', $about->id)->value('path'));
    $this->assertSame('about', PageTranslation::query()->where('page_id', $about->id)->value('slug'));
    $this->assertSame('/', PageTranslation::query()->where('page_id', $homePage->id)->value('path'));
  }

  #[Test]
  public function the_operator_command_fills_the_home_page_without_naming_a_class(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(CoreCatalogSeeder::class);

    $this->artisan('webblocks:starter-content')
      ->assertSuccessful();

    $this->assertTrue(Block::query()->where('type', 'hero')->exists());
    $this->assertSame('/', PageTranslation::query()->where('path', '/')->value('path'));
  }

  #[Test]
  public function the_operator_command_is_a_reportable_no_op_on_a_page_that_has_content(): void
  {
    $page = $this->provisionHomePage();
    app(StarterContentInstaller::class)->install($page);
    $blockCount = Block::query()->count();

    $this->artisan('webblocks:starter-content')
      ->expectsOutputToContain('The page already has blocks.')
      ->assertSuccessful();

    $this->assertSame($blockCount, Block::query()->count());
  }

  #[Test]
  public function the_operator_command_reports_an_unknown_site_handle_instead_of_filling_another(): void
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(CoreCatalogSeeder::class);

    $this->artisan('webblocks:starter-content', ['--site' => 'not-a-site'])
      ->assertFailed();

    $this->assertSame(0, Block::query()->count());
  }

  #[Test]
  public function the_install_command_resolves_and_offers_the_skip_switch(): void
  {
    $command = app(InstallWebBlocksCmsCommand::class);

    $this->assertTrue($command->getDefinition()->hasOption('skip-starter-content'));
  }

  private function installStarterContent(): StarterContentResult
  {
    return app(StarterContentInstaller::class)->install($this->provisionHomePage());
  }

  /**
   * Mirrors how PageController loads a published page's block tree.
   *
   * @return Collection<int, Block>
   */
  private function renderableRootBlocks(Page $page): Collection
  {
    $blocks = Block::query()
      ->where('page_id', $page->id)
      ->whereNull('parent_id')
      ->where('status', 'published')
      ->with($this->publishedBlockRelations())
      ->orderBy('sort_order')
      ->get();

    return app(BlockTranslationResolver::class)->resolveCollection($blocks);
  }

  /**
   * @return array<int|string, mixed>
   */
  private function publishedBlockRelations(): array
  {
    return [
      'blockType',
      'textTranslations',
      'children' => fn ($query) => $query
        ->where('status', 'published')
        ->with($this->publishedBlockRelations())
        ->orderBy('sort_order'),
    ];
  }

  private function provisionHomePage(): Page
  {
    $this->seed(FoundationSiteLocaleSeeder::class);
    $this->seed(CoreCatalogSeeder::class);

    return app(DefaultHomepageProvisioner::class)->provision(Site::query()->firstOrFail());
  }
}
