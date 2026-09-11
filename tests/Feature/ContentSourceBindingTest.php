<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\ContentBindingResolver;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionQuery;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionResult;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceEditor;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRuntime;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceAccessPolicy;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\QueryableContentCollectionSourceResolver;
use WebBlocks\Cms\Support\Plugins\PluginDefinition;
use WebBlocks\Cms\Support\Plugins\PluginRegistry;
use WebBlocks\Cms\Tests\TestCase;

class ContentSourceBindingTest extends TestCase
{
  #[Test]
  public function an_enabled_plugin_source_can_feed_existing_core_block_fields(): void
  {
    $this->registerCatalogSource(enabled: true);

    $heading = $this->boundBlock('header', 'title', 'name', 'Editorial fallback');
    $richText = $this->boundBlock('rich-text', 'content', 'description', '<p>Editorial fallback</p>');

    $this->assertSame('WebBlocks SEO', $heading->boundPublicValue('title', $heading->title));
    $this->assertSame('<p>SEO metadata managed from one place.</p>', $richText->boundPublicValue('content', $richText->content));
    $this->assertStringContainsString('WebBlocks SEO', view($heading->publicRenderView(), ['block' => $heading])->render());
    $this->assertStringContainsString('SEO metadata managed from one place.', view($richText->publicRenderView(), ['block' => $richText])->render());
  }

  #[Test]
  public function a_missing_or_disabled_source_keeps_editorial_fallback_content(): void
  {
    $this->registerCatalogSource(enabled: false);

    $heading = $this->boundBlock('header', 'title', 'name', 'Editorial fallback');

    $this->assertSame('Editorial fallback', $heading->boundPublicValue('title', $heading->title));
    $this->assertStringContainsString('Editorial fallback', view($heading->publicRenderView(), ['block' => $heading])->render());
  }

  #[Test]
  public function a_collection_repeats_an_existing_slide_template_and_keeps_editorial_slides(): void
  {
    $this->registerCatalogSource(enabled: true);

    $slider = new Block(['type' => 'slider']);
    $slider->id = 10;
    $slider->settings = [
      'content_collection' => [
        'source' => 'plugin-catalog::featured-plugins',
        'template_block_id' => 12,
        'limit' => 2,
      ],
    ];

    $editorialSlide = new Block(['type' => 'slide']);
    $editorialSlide->id = 11;
    $editorialSlide->setRelation('children', collect());

    $templateSlide = new Block(['type' => 'slide']);
    $templateSlide->id = 12;
    $heading = $this->boundBlock('header', 'title', 'name', 'Plugin fallback');
    $heading->settings = [
      'content_bindings' => [
        'title' => [
          'source' => 'plugin-catalog::featured-plugins',
          'record' => '@item',
          'field' => 'name',
        ],
      ],
    ];
    $templateSlide->setRelation('children', collect([$heading]));
    $slider->setRelation('children', collect([$editorialSlide, $templateSlide]));

    $slides = app(ContentCollectionRenderer::class)->sliderSlides($slider);

    $this->assertCount(3, $slides);
    $this->assertSame($editorialSlide, $slides[0]);
    $this->assertSame('WebBlocks SEO', $slides[1]->children[0]->boundPublicValue('title', 'fallback'));
    $this->assertSame('WebBlocks Forms', $slides[2]->children[0]->boundPublicValue('title', 'fallback'));
  }

  #[Test]
  public function a_grid_can_filter_sort_and_repeat_any_existing_child_template(): void
  {
    $this->registerCatalogSource(enabled: true);

    $grid = new Block(['type' => 'grid']);
    $grid->id = 20;
    $grid->settings = [
      'content_collection' => [
        'source' => 'plugin-catalog::featured-plugins',
        'template_block_id' => 22,
        'limit' => 10,
        'filter_field' => 'category',
        'filter_value' => 'marketing',
        'sort_field' => 'name',
        'sort_direction' => 'desc',
      ],
    ];

    $editorialCard = new Block(['type' => 'card']);
    $editorialCard->id = 21;
    $editorialCard->setRelation('children', collect());
    $templateCard = new Block(['type' => 'card']);
    $templateCard->id = 22;
    $heading = $this->boundBlock('header', 'title', 'name', 'Plugin fallback');
    $heading->settings = ['content_bindings' => ['title' => [
      'source' => 'plugin-catalog::featured-plugins',
      'record' => '@item',
      'field' => 'name',
    ]]];
    $templateCard->setRelation('children', collect([$heading]));
    $grid->setRelation('children', collect([$editorialCard, $templateCard]));

    $children = app(ContentCollectionRenderer::class)->children($grid);

    $this->assertCount(2, $children);
    $this->assertSame($editorialCard, $children[0]);
    $this->assertSame('WebBlocks SEO', $children[1]->children[0]->boundPublicValue('title', 'fallback'));
  }

  #[Test]
  public function image_and_link_fields_can_use_the_same_collection_item(): void
  {
    $this->registerCatalogSource(enabled: true);

    $image = $this->boundBlock('image', 'image_source', 'image_url', '');
    $image->settings = ['content_bindings' => [
      'image_source' => [
        'source' => 'plugin-catalog::featured-plugins',
        'record' => '@item',
        'field' => 'image_url',
      ],
      'url' => [
        'source' => 'plugin-catalog::featured-plugins',
        'record' => '@item',
        'field' => 'download_url',
      ],
    ]];
    $image->setAttribute('content_source_item', [
      'image_url' => 'https://cdn.example.test/plugin.png',
      'download_url' => '/plugins/seo',
    ]);
    $this->assertSame('https://cdn.example.test/plugin.png', $image->boundPublicValue('image_source'));
    $this->assertSame('/plugins/seo', $image->boundPublicValue('url'));
    $this->assertSame(['title' => ['text'], 'url' => ['url']], app(ContentSourceEditor::class)->targets(new Block(['type' => 'button_link'])));
  }

  #[Test]
  public function a_collection_can_be_previewed_and_paginated_without_plugin_specific_code(): void
  {
    $this->registerCatalogSource(enabled: true);
    $this->get('/?wb_collection_30_page=2');

    $stack = new Block(['type' => 'stack']);
    $stack->id = 30;
    $stack->settings = ['content_collection' => [
      'source' => 'plugin-catalog::featured-plugins',
      'template_block_id' => 31,
      'limit' => 10,
      'paginate' => true,
      'per_page' => 1,
    ]];
    $template = new Block(['type' => 'header']);
    $template->id = 31;
    $template->settings = ['content_bindings' => ['title' => [
      'source' => 'plugin-catalog::featured-plugins',
      'record' => '@item',
      'field' => 'name',
    ]]];
    $template->setRelation('children', collect());
    $stack->setRelation('children', collect([$template]));

    $children = app(ContentCollectionRenderer::class)->children($stack);
    $preview = app(ContentSourceEditor::class)
      ->collectionPreview($stack, 'plugin-catalog::featured-plugins');

    $this->assertCount(1, $children);
    $this->assertSame('WebBlocks Forms', $children[0]->boundPublicValue('title'));
    $this->assertSame(2, $stack->getAttribute('content_source_pagination')['current_page']);
    $this->assertCount(2, $preview);
  }

  #[Test]
  public function access_policies_and_cache_are_enforced_before_entity_resolution(): void
  {
    CountingEntitySource::$calls = 0;
    $plugin = PluginDefinition::make('secure-content')->contentSources([
      ContentSourceDefinition::entity('secure-content::allowed')
        ->resolver(CountingEntitySource::class)->cacheFor(60)->fields(['name' => 'text']),
      ContentSourceDefinition::entity('secure-content::denied')
        ->resolver(CountingEntitySource::class)->accessPolicy(DenyContentSourcePolicy::class)->fields(['name' => 'text']),
    ]);
    $this->registerPlugin($plugin, 'secure-content');

    $allowed = $this->sourceBoundBlock('secure-content::allowed');
    $denied = $this->sourceBoundBlock('secure-content::denied');

    $this->assertSame('Resolved once', $allowed->boundPublicValue('title', 'fallback'));
    $this->assertSame('Resolved once', $allowed->boundPublicValue('title', 'fallback'));
    $this->assertSame('fallback', $denied->boundPublicValue('title', 'fallback'));
    $this->assertSame(1, CountingEntitySource::$calls);

    app(ContentSourceRuntime::class)->invalidate('secure-content::allowed');
    $this->assertSame('Resolved once', $allowed->boundPublicValue('title', 'fallback'));
    $this->assertSame(2, CountingEntitySource::$calls);
  }

  #[Test]
  public function queryable_sources_receive_filter_sort_limit_and_page_without_loading_the_full_collection(): void
  {
    QueryableEventsSource::$lastQuery = null;
    $plugin = PluginDefinition::make('events')->contentSources([
      ContentSourceDefinition::collection('events::upcoming')
        ->resolver(QueryableEventsSource::class)->fields(['title' => 'text']),
    ]);
    $this->registerPlugin($plugin, 'events');
    $this->get('/?wb_collection_40_page=3');

    $grid = new Block(['type' => 'grid']);
    $grid->id = 40;
    $grid->settings = ['content_collection' => [
      'source' => 'events::upcoming', 'template_block_id' => 41, 'limit' => 100,
      'filter_field' => 'category', 'filter_value' => 'workshop',
      'sort_field' => 'starts_at', 'sort_direction' => 'desc',
      'paginate' => true, 'per_page' => 10,
    ]];
    $template = new Block(['type' => 'header']);
    $template->id = 41;
    $template->settings = ['content_bindings' => ['title' => [
      'source' => 'events::upcoming', 'record' => '@item', 'field' => 'title',
    ]]];
    $template->setRelation('children', collect());
    $grid->setRelation('children', collect([$template]));

    $children = app(ContentCollectionRenderer::class)->children($grid);

    $this->assertSame('Server-paged event', $children->first()->boundPublicValue('title'));
    $this->assertSame(50, QueryableEventsSource::$lastQuery?->limit);
    $this->assertSame('category', QueryableEventsSource::$lastQuery?->filterField);
    $this->assertSame('workshop', QueryableEventsSource::$lastQuery?->filterValue);
    $this->assertSame('starts_at', QueryableEventsSource::$lastQuery?->sortField);
    $this->assertSame('desc', QueryableEventsSource::$lastQuery?->sortDirection);
    $this->assertSame(3, QueryableEventsSource::$lastQuery?->page);

    $preview = app(ContentSourceEditor::class)->collectionPreview($grid, 'events::upcoming');
    $this->assertCount(1, $preview);
    $this->assertSame(1, QueryableEventsSource::$lastQuery?->page);
    $this->assertSame(3, QueryableEventsSource::$lastQuery?->limit);
  }

  #[Test]
  public function collection_resolver_errors_can_hide_the_template(): void
  {
    $plugin = PluginDefinition::make('broken')->contentSources([
      ContentSourceDefinition::collection('broken::items')
        ->resolver(ThrowingCollectionSource::class)->fields(['title' => 'text']),
    ]);
    $this->registerPlugin($plugin, 'broken');

    $grid = new Block(['type' => 'grid']);
    $grid->id = 45;
    $grid->settings = ['content_collection' => [
      'source' => 'broken::items', 'template_block_id' => 46, 'error_behavior' => 'hide_template',
    ]];
    $template = new Block(['type' => 'card']);
    $template->id = 46;
    $template->setRelation('children', collect());
    $grid->setRelation('children', collect([$template]));

    $this->assertCount(0, app(ContentCollectionRenderer::class)->children($grid));
  }

  #[Test]
  public function empty_collections_and_removed_sources_have_safe_editor_outcomes(): void
  {
    $this->registerCatalogSource(enabled: true);
    $grid = new Block(['type' => 'grid']);
    $grid->id = 50;
    $grid->settings = ['content_collection' => [
      'source' => 'plugin-catalog::featured-plugins', 'template_block_id' => 51,
      'filter_field' => 'category', 'filter_value' => 'missing', 'empty_behavior' => 'keep_template',
    ]];
    $template = new Block(['type' => 'card']);
    $template->id = 51;
    $template->setRelation('children', collect());
    $grid->setRelation('children', collect([$template]));

    $this->assertSame($template, app(ContentCollectionRenderer::class)->children($grid)->first());

    $orphan = new Block(['type' => 'header']);
    $orphan->settings = ['content_bindings' => ['title' => [
      'source' => 'removed::source', 'record' => 'one', 'field' => 'title',
    ]]];
    $this->assertSame([[
      'key' => 'content_source_warning_missing_source',
      'params' => ['target' => 'title', 'source' => 'removed::source'],
    ]], app(ContentSourceEditor::class)->warnings($orphan));
  }

  private function registerPlugin(PluginDefinition $plugin, string $handle): void
  {
    $registry = new PluginRegistry([$handle => true]);
    $registry->register($plugin);
    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(ContentSourceRegistry::class);
    $this->app->forgetInstance(ContentBindingResolver::class);
    $this->app->forgetInstance(ContentCollectionRenderer::class);
  }

  private function sourceBoundBlock(string $source): Block
  {
    $block = new Block(['type' => 'header', 'title' => 'fallback']);
    $block->settings = ['content_bindings' => ['title' => [
      'source' => $source, 'record' => 'one', 'field' => 'name',
    ]]];

    return $block;
  }

  private function registerCatalogSource(bool $enabled): void
  {
    $plugin = PluginDefinition::make('plugin-catalog')
      ->label('Plugin Catalog')
      ->version('1.0.0')
      ->contentSources([
        ContentSourceDefinition::entity('plugin-catalog::plugin')
          ->label('Plugin')
          ->resolver(FakePluginCatalogSource::class)
          ->fields([
            'name' => ['type' => 'text', 'label' => 'Name'],
            'description' => ['type' => 'rich_text', 'label' => 'Description'],
            'download_url' => ['type' => 'url', 'label' => 'Download URL'],
          ]),
        ContentSourceDefinition::collection('plugin-catalog::featured-plugins')
          ->label('Featured plugins')
          ->resolver(FakeFeaturedPluginsSource::class)
          ->fields([
            'name' => ['type' => 'text', 'label' => 'Plugin name'],
            'description' => ['type' => 'rich_text', 'label' => 'Description'],
            'category' => ['type' => 'text', 'label' => 'Category'],
            'image_url' => ['type' => 'media', 'label' => 'Image'],
            'download_url' => ['type' => 'url', 'label' => 'Download URL'],
          ]),
      ]);

    $registry = new PluginRegistry(['plugin-catalog' => $enabled]);
    $registry->register($plugin);

    $this->app->instance(PluginRegistry::class, $registry);
    $this->app->forgetInstance(ContentSourceRegistry::class);
    $this->app->forgetInstance(ContentBindingResolver::class);
    $this->app->forgetInstance(ContentCollectionRenderer::class);
  }

  private function boundBlock(string $type, string $target, string $field, string $fallback): Block
  {
    $block = new Block;
    $block->type = $type;
    $block->{$target} = $fallback;
    $block->settings = [
      'content_bindings' => [
        $target => [
          'source' => 'plugin-catalog::plugin',
          'record' => 'webblocks-seo',
          'field' => $field,
        ],
      ],
    ];

    return $block;
  }
}

class DenyContentSourcePolicy implements ContentSourceAccessPolicy
{
  public function allows(ContentSourceContext $context): bool
  {
    return false;
  }
}

class CountingEntitySource implements ContentSourceResolver
{
  public static int $calls = 0;

  public function resolve(string $recordKey, ContentSourceContext $context): ?array
  {
    self::$calls++;

    return ['name' => 'Resolved once'];
  }

  public function options(ContentSourceContext $context): array
  {
    return ['one' => 'One'];
  }
}

class QueryableEventsSource implements QueryableContentCollectionSourceResolver
{
  public static ?ContentCollectionQuery $lastQuery = null;

  public function resolveCollection(array $settings, ContentSourceContext $context): iterable
  {
    throw new \RuntimeException('The legacy collection method must not run.');
  }

  public function queryCollection(ContentCollectionQuery $query, array $settings, ContentSourceContext $context): ContentCollectionResult
  {
    self::$lastQuery = $query;

    return new ContentCollectionResult([['title' => 'Server-paged event']], 25, 3, 10);
  }
}

class FakeFeaturedPluginsSource implements ContentCollectionSourceResolver
{
  public function resolveCollection(array $settings, ContentSourceContext $context): iterable
  {
    return [
      ['name' => 'WebBlocks SEO', 'description' => '<p>SEO metadata.</p>', 'category' => 'marketing'],
      ['name' => 'WebBlocks Forms', 'description' => '<p>Public forms.</p>', 'category' => 'forms'],
    ];
  }
}

class ThrowingCollectionSource implements ContentCollectionSourceResolver
{
  public function resolveCollection(array $settings, ContentSourceContext $context): iterable
  {
    throw new \RuntimeException('Unavailable');
  }
}

class FakePluginCatalogSource implements ContentSourceResolver
{
  public function resolve(string $recordKey, ContentSourceContext $context): ?array
  {
    if ($recordKey !== 'webblocks-seo') {
      return null;
    }

    return [
      'name' => 'WebBlocks SEO',
      'description' => '<p>SEO metadata managed from one place.</p>',
      'download_url' => '/plugins/webblocks-seo/download',
    ];
  }

  public function options(ContentSourceContext $context): array
  {
    return ['webblocks-seo' => 'WebBlocks SEO'];
  }
}
