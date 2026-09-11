<?php

namespace WebBlocks\Cms\Tests\Feature;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalApiDiscoveryController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentResourceController;
use WebBlocks\Cms\Http\Controllers\InternalContentApi\InternalContentSourceController;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Models\BlockType;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Models\SlotType;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class ContentSourceApiTest extends TestCase
{
  protected function defineDatabaseMigrations(): void
  {
    $this->loadMigrationsFrom(dirname(__DIR__, 2).'/database/migrations/fresh');
  }

  #[Test]
  public function discovery_and_preview_expose_only_declared_accessible_fields(): void
  {
    [$heading] = $this->seedBlocks();
    $this->registerSources();
    $controller = app(InternalContentSourceController::class);

    $discovery = $controller->index(Request::create('/webadmin/api/content-sources', 'GET', ['block_id' => $heading->id]))->getData(true);
    $entity = collect($discovery['sources'])->firstWhere('handle', 'catalog::plugin');

    $this->assertSame(['name', 'url'], array_keys($entity['fields']));
    $this->assertSame(['title'], $entity['compatible_binding_targets']);
    $this->assertSame(['seo' => 'SEO Plugin'], $entity['records']);

    $preview = $controller->preview(Request::create('/', 'POST', ['record' => 'seo']), 'catalog::plugin')->getData(true);
    $this->assertSame(['name' => 'SEO Plugin', 'url' => '/plugins/seo'], $preview['record']);
    $this->assertArrayNotHasKey('secret', $preview['record']);

    $collection = $controller->preview(Request::create('/', 'POST', ['limit' => 1]), 'catalog::featured')->getData(true);
    $this->assertSame([['name' => 'SEO Plugin', 'url' => '/plugins/seo']], $collection['records']);
  }

  #[Test]
  public function block_patch_writes_and_clears_validated_bindings_and_collections(): void
  {
    [$heading, $grid, $template] = $this->seedBlocks();
    $this->registerSources();
    $controller = app(InternalContentResourceController::class);

    $response = $controller->updateBlock(Request::create('/', 'PATCH', ['content_bindings' => [
      'title' => ['source' => 'catalog::plugin', 'record' => 'seo', 'field' => 'name'],
    ]]), $heading);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame('catalog::plugin', $heading->fresh()->setting('content_bindings.title.source'));

    $response = $controller->updateBlock(Request::create('/', 'PATCH', ['settings' => ['content_collection' => [
      'source' => 'catalog::featured', 'template_block_id' => $template->id, 'limit' => 20,
      'filter_field' => 'name', 'sort_field' => 'name', 'sort_direction' => 'desc', 'paginate' => true,
    ]]]), $grid);
    $this->assertSame(200, $response->getStatusCode());
    $this->assertSame($template->id, $grid->fresh()->setting('content_collection.template_block_id'));
    $this->assertTrue($grid->fresh()->setting('content_collection.paginate'));

    $invalid = $controller->updateBlock(Request::create('/', 'PATCH', ['content_bindings' => [
      'title' => ['source' => 'catalog::plugin', 'record' => 'seo', 'field' => 'secret'],
    ]]), $heading->fresh());
    $this->assertSame(422, $invalid->getStatusCode());
    $this->assertSame('invalid_content_source_configuration', $invalid->getData(true)['code']);

    $controller->updateBlock(Request::create('/', 'PATCH', ['content_bindings' => null]), $heading->fresh());
    $this->assertNull($heading->fresh()->setting('content_bindings'));
  }

  #[Test]
  public function content_contract_and_openapi_publish_content_source_authoring(): void
  {
    $this->seedBlocks();
    $payload = app(InternalContentResourceController::class)->contentContract()->getData(true);
    $grid = collect($payload['blocks'] ?? $payload['block_contracts'] ?? [])->firstWhere('slug', 'grid');

    $this->assertTrue($grid['supports_content_collection']);
    $this->assertArrayHasKey('content_source_binding_targets', $grid);

    $openapi = app(InternalApiDiscoveryController::class)->openapi()->getData(true);
    $this->assertArrayHasKey('/content-sources', $openapi['paths']);
    $this->assertArrayHasKey('/content-sources/{source}/preview', $openapi['paths']);
  }

  private function registerSources(): void
  {
    $plugin = PluginDefinition::make('catalog')->contentSources([
      ContentSourceDefinition::entity('catalog::plugin')->resolver(ApiPluginSource::class)->fields([
        'name' => 'text', 'url' => 'url',
      ]),
      ContentSourceDefinition::collection('catalog::featured')->resolver(ApiFeaturedSource::class)->fields([
        'name' => 'text', 'url' => 'url',
      ]),
    ]);
    $registry = new PluginRegistry(['catalog' => true]);
    $registry->register($plugin);
    $this->app->instance(PluginRegistry::class, $registry);

    foreach ([ContentSourceRegistry::class, InternalContentSourceController::class, InternalContentResourceController::class] as $service) {
      $this->app->forgetInstance($service);
    }
  }

  /** @return array{Block, Block, Block} */
  private function seedBlocks(): array
  {
    $site = Site::query()->create(['handle' => 'test', 'name' => 'Test', 'is_primary' => true]);
    $locale = Locale::query()->create(['code' => 'en', 'name' => 'English', 'is_default' => true, 'is_enabled' => true]);
    $site->locales()->syncWithoutDetaching([$locale->id => ['is_enabled' => true]]);
    $slot = SlotType::query()->create(['slug' => 'main', 'name' => 'Main', 'status' => 'published', 'sort_order' => 0]);
    $page = Page::query()->create(['site_id' => $site->id, 'slug' => 'home', 'status' => Page::STATUS_DRAFT]);
    PageSlot::query()->create(['page_id' => $page->id, 'slot_type_id' => $slot->id, 'sort_order' => 0]);

    foreach ([['header', false], ['grid', true], ['card', true]] as [$slug, $container]) {
      BlockType::query()->create(['slug' => $slug, 'name' => str($slug)->headline(), 'category' => 'content', 'source_type' => 'static', 'is_system' => false, 'is_container' => $container, 'sort_order' => 0, 'status' => 'published']);
    }

    $make = fn (string $type, ?int $parent = null): Block => Block::query()->create([
      'page_id' => $page->id, 'block_type_id' => BlockType::query()->where('slug', $type)->value('id'), 'type' => $type,
      'source_type' => 'static', 'slot' => 'main', 'slot_type_id' => $slot->id, 'parent_id' => $parent,
      'sort_order' => 0, 'status' => 'published',
    ]);
    $heading = $make('header');
    $grid = $make('grid');
    $template = $make('card', $grid->id);

    return [$heading, $grid->load('children.blockType'), $template];
  }
}

class ApiPluginSource implements ContentSourceResolver
{
  public function resolve(string $recordKey, ContentSourceContext $context): ?array
  {
    return $recordKey === 'seo' ? ['name' => 'SEO Plugin', 'url' => '/plugins/seo', 'secret' => 'hidden'] : null;
  }

  public function options(ContentSourceContext $context): array
  {
    return ['seo' => 'SEO Plugin'];
  }
}

class ApiFeaturedSource implements ContentCollectionSourceResolver
{
  public function resolveCollection(array $settings, ContentSourceContext $context): iterable
  {
    return [['name' => 'SEO Plugin', 'url' => '/plugins/seo', 'secret' => 'hidden']];
  }
}
