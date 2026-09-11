<?php

namespace WebBlocks\Cms\Tests\Feature;

use PHPUnit\Framework\Attributes\Test;
use WebBlocks\Cms\Models\Block;
use WebBlocks\Cms\Support\ContentSources\ContentBindingResolver;
use WebBlocks\Cms\Support\ContentSources\ContentCollectionRenderer;
use WebBlocks\Cms\Support\ContentSources\ContentSourceContext;
use WebBlocks\Cms\Support\ContentSources\ContentSourceDefinition;
use WebBlocks\Cms\Support\ContentSources\ContentSourceEditor;
use WebBlocks\Cms\Support\ContentSources\ContentSourceRegistry;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentCollectionSourceResolver;
use WebBlocks\Cms\Support\ContentSources\Contracts\ContentSourceResolver;
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
