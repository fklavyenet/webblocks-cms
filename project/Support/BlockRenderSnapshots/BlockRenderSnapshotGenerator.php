<?php

namespace Project\Support\BlockRenderSnapshots;

use App\Models\Asset;
use App\Models\Block;
use App\Models\BlockType;
use App\Models\Locale;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Str;

class BlockRenderSnapshotGenerator
{
    private const SNAPSHOT_ROOT = 'project/block-render-snapshots';

    private int $nextBlockId = 100000;

    public function run(): array
    {
        $generatedAt = now();
        $rootDirectory = storage_path(self::SNAPSHOT_ROOT);
        $publishedBlocksDirectory = $rootDirectory.'/published-blocks';

        $this->prepareOutputDirectory($rootDirectory, $publishedBlocksDirectory);

        $blockTypes = BlockType::query()
            ->where('status', 'published')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $manifestEntries = [];
        $warningCount = 0;
        $renderedCount = 0;
        $previousRequest = app('request');

        DB::beginTransaction();

        try {
            $context = $this->createRenderContext($blockTypes);

            foreach ($blockTypes as $blockType) {
                $entry = $this->renderSnapshotForBlockType($blockType, $context, $publishedBlocksDirectory, $generatedAt);
                $manifestEntries[] = $entry;

                if (($entry['result'] ?? null) === 'rendered') {
                    $renderedCount++;
                }

                if (($entry['warnings'] ?? []) !== []) {
                    $warningCount++;
                }
            }
        } finally {
            if (DB::transactionLevel() > 0) {
                DB::rollBack();
            }

            app()->instance('request', $previousRequest);
            app('url')->setRequest($previousRequest);
        }

        File::put(
            $rootDirectory.'/manifest.json',
            json_encode([
                'generated_at' => $generatedAt->toIso8601String(),
                'output_directory' => 'storage/'.self::SNAPSHOT_ROOT,
                'published_block_type_count' => $blockTypes->count(),
                'rendered_count' => $renderedCount,
                'warning_count' => $warningCount,
                'blocks' => $manifestEntries,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        File::put(
            $rootDirectory.'/index.html',
            $this->buildIndexDocument($manifestEntries, $generatedAt)
        );

        return [
            'output_directory' => 'storage/'.self::SNAPSHOT_ROOT,
            'processed_count' => $blockTypes->count(),
            'rendered_count' => $renderedCount,
            'warning_count' => $warningCount,
        ];
    }

    private function prepareOutputDirectory(string $rootDirectory, string $publishedBlocksDirectory): void
    {
        File::ensureDirectoryExists($publishedBlocksDirectory);
        File::cleanDirectory($publishedBlocksDirectory);
        File::delete($rootDirectory.'/index.html');
        File::delete($rootDirectory.'/manifest.json');
    }

    private function createRenderContext(Collection $blockTypes): array
    {
        $request = Request::create('/p/review-target', 'GET', ['q' => 'snapshot query']);
        app()->instance('request', $request);
        app('url')->setRequest($request);

        $defaultLocale = Locale::query()->where('is_default', true)->first();

        if (! $defaultLocale) {
            $defaultLocale = Locale::query()->create([
                'code' => 'en',
                'name' => 'English',
                'is_default' => true,
                'is_enabled' => true,
            ]);
        }

        $site = Site::query()->create([
            'name' => 'Snapshot Review Site',
            'handle' => 'snapshot-review-site-'.Str::lower(Str::random(6)),
            'display_name' => 'Snapshot Review Site',
            'tagline' => 'Rendered block fixture output',
            'is_primary' => false,
        ]);
        $site->locales()->syncWithoutDetaching([$defaultLocale->id => ['is_enabled' => true]]);
        $site->load('locales');

        $homePage = Page::query()->create([
            'site_id' => $site->id,
            'page_type' => Page::TYPE_DEFAULT,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'docs'],
        ]);
        $homeTranslation = PageTranslation::query()->create([
            'page_id' => $homePage->id,
            'site_id' => $site->id,
            'locale_id' => $defaultLocale->id,
            'name' => 'Home',
            'slug' => 'home',
            'path' => '/',
        ]);

        $reviewPage = Page::query()->create([
            'site_id' => $site->id,
            'page_type' => Page::TYPE_DEFAULT,
            'status' => Page::STATUS_PUBLISHED,
            'settings' => ['public_shell' => 'docs'],
        ]);
        $reviewTranslation = PageTranslation::query()->create([
            'page_id' => $reviewPage->id,
            'site_id' => $site->id,
            'locale_id' => $defaultLocale->id,
            'name' => 'Review Target',
            'slug' => 'review-target',
            'path' => '/p/review-target',
        ]);

        $logoAsset = Asset::query()->create([
            'disk' => 'public',
            'path' => 'assets/snapshots/review-logo.svg',
            'filename' => 'review-logo.svg',
            'original_name' => 'review-logo.svg',
            'extension' => 'svg',
            'mime_type' => 'image/svg+xml',
            'size' => 512,
            'kind' => Asset::KIND_IMAGE,
            'visibility' => 'public',
            'title' => 'Review Logo',
            'alt_text' => 'Review Logo',
            'width' => 120,
            'height' => 120,
        ]);

        $homePage->setRelation('site', $site);
        $homePage->setRelation('translations', collect([$homeTranslation]));
        $homePage->setRelation('currentTranslation', $homeTranslation);

        $reviewPage->setRelation('site', $site);
        $reviewPage->setRelation('translations', collect([$reviewTranslation]));
        $reviewPage->setRelation('currentTranslation', $reviewTranslation);

        $primaryHomeItem = NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'page_id' => $homePage->id,
            'title' => 'Home',
            'link_type' => NavigationItem::LINK_PAGE,
            'icon' => 'home',
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
        ]);
        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_PRIMARY,
            'page_id' => $reviewPage->id,
            'title' => 'Review Target',
            'link_type' => NavigationItem::LINK_PAGE,
            'icon' => 'file-text',
            'position' => 2,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
        ]);

        $docsGroup = NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_DOCS,
            'title' => 'Guides',
            'link_type' => NavigationItem::LINK_GROUP,
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
        ]);
        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_DOCS,
            'parent_id' => $docsGroup->id,
            'page_id' => $reviewPage->id,
            'title' => 'Current Page',
            'link_type' => NavigationItem::LINK_PAGE,
            'icon' => 'book-open',
            'position' => 1,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
        ]);
        NavigationItem::query()->create([
            'site_id' => $site->id,
            'menu_key' => NavigationItem::MENU_DOCS,
            'parent_id' => $docsGroup->id,
            'title' => 'Patterns',
            'url' => '/p/patterns',
            'link_type' => NavigationItem::LINK_CUSTOM_URL,
            'icon' => 'grid',
            'position' => 2,
            'visibility' => NavigationItem::VISIBILITY_VISIBLE,
            'is_system' => false,
        ]);

        $primaryHomeItem->setRelation('page', $homePage);

        return [
            'block_types_by_slug' => $blockTypes->keyBy('slug'),
            'site' => $site,
            'locale' => $defaultLocale,
            'home_page' => $homePage,
            'review_page' => $reviewPage,
            'review_translation' => $reviewTranslation,
            'logo_asset' => $logoAsset,
        ];
    }

    private function renderSnapshotForBlockType(BlockType $blockType, array $context, string $publishedBlocksDirectory, CarbonInterface $generatedAt): array
    {
        $rendererFound = View::exists('pages.partials.blocks.'.$blockType->slug);
        $warnings = [];
        $result = 'rendered';
        $outputFile = 'published-blocks/'.$blockType->slug.'.html';
        $outputPath = $publishedBlocksDirectory.'/'.$blockType->slug.'.html';

        try {
            $fixture = $this->fixtureFor($blockType->slug, $context);
            $block = $fixture['block'];
            $page = $context['review_page'];
            $pageBlocks = $this->flattenBlocks(array_merge([$block], $fixture['page_blocks']));
            $page->setRelation('blocks', $pageBlocks);

            foreach ($pageBlocks as $pageBlock) {
                $this->applyRenderContext($pageBlock, $page, 'main');
            }

            $currentHtml = trim((string) view('pages.partials.block', ['block' => $block])->render());

            if ($currentHtml === '') {
                $warnings[] = 'Rendered output was empty for the supplied review fixture.';
            }

            if (! $rendererFound) {
                $warnings[] = 'No dedicated public renderer partial was found; the command used '.$block->publicRenderView().'.';
            }

            $proposedPreview = str_contains($currentHtml, 'wb-cms-block')
                ? null
                : $this->proposedMarkerPreview($currentHtml, $blockType->slug);

            File::put(
                $outputPath,
                $this->buildSnapshotDocument(
                    blockType: $blockType,
                    generatedAt: $generatedAt,
                    rendererFound: $rendererFound,
                    currentHtml: $currentHtml,
                    proposedPreview: $proposedPreview,
                    warnings: $warnings,
                )
            );
        } catch (\Throwable $exception) {
            $result = 'failed';
            $warnings[] = $exception->getMessage();

            File::put(
                $outputPath,
                $this->buildFailureDocument($blockType, $generatedAt, $rendererFound, $exception->getMessage())
            );
        }

        return [
            'slug' => $blockType->slug,
            'name' => $blockType->name,
            'category' => $blockType->category,
            'status' => $blockType->status,
            'renderer_found' => $rendererFound,
            'output_file' => $outputFile,
            'result' => $result,
            'warnings' => $warnings,
        ];
    }

    private function fixtureFor(string $slug, array $context): array
    {
        $logoAsset = $context['logo_asset'];

        return match ($slug) {
            'content_header' => $this->fixture($this->block($slug, [
                'title' => 'Reviewing Current Block Output',
                'subtitle' => 'Snapshot fixture for the current public renderer contract.',
                'variant' => 'h1',
                'meta' => ['Published catalog', 'Review snapshot'],
                'settings' => ['alignment' => 'left'],
            ])),
            'section' => $this->fixture($this->block($slug, [
                'settings' => ['spacing' => 'lg'],
            ], [
                $this->block('header', ['title' => 'Section heading', 'variant' => 'h2', 'settings' => ['anchor' => 'section-heading']]),
                $this->block('plain_text', ['content' => 'A section groups related content blocks with a semantic wrapper.']),
                $this->block('button_link', ['title' => 'Explore section', 'variant' => 'secondary', 'settings' => ['url' => '/p/review-target']]),
            ])),
            'container' => $this->fixture($this->block($slug, [
                'settings' => ['width' => 'lg'],
            ], [
                $this->block('header', ['title' => 'Contained content', 'variant' => 'h2']),
                $this->block('plain_text', ['content' => 'Containers keep content within a chosen public width.']),
            ])),
            'cluster' => $this->fixture($this->block($slug, [
                'settings' => ['gap' => '4', 'alignment' => 'center'],
            ], [
                $this->block('button_link', ['title' => 'Primary action', 'settings' => ['url' => '/p/review-target']]),
                $this->block('button_link', ['title' => 'Secondary action', 'variant' => 'secondary', 'settings' => ['url' => '/p/review-target#details']]),
            ])),
            'grid' => $this->fixture($this->block($slug, [
                'settings' => ['columns' => '3', 'gap' => '4'],
            ], [
                $this->block('card', [
                    'title' => 'First card',
                    'subtitle' => 'Overview',
                    'content' => 'Cards can sit inside grid layouts as reviewable content units.',
                ]),
                $this->block('stat-card', [
                    'title' => '128',
                    'subtitle' => 'Rendered samples',
                    'content' => 'Counted from the active fixture set.',
                ]),
                $this->block('alert', [
                    'title' => 'Review note',
                    'content' => 'Check the outer wrapper and internal spacing classes.',
                    'settings' => ['variant' => 'info'],
                ]),
            ])),
            'header' => $this->fixture($this->block($slug, [
                'title' => 'Current header output',
                'variant' => 'h2',
                'settings' => ['anchor' => 'current-header-output', 'alignment' => 'center'],
            ])),
            'plain_text' => $this->fixture($this->block($slug, [
                'content' => 'Plain text keeps the output simple and fully escaped.',
                'settings' => ['alignment' => 'left'],
            ])),
            'rich-text' => $this->fixture($this->block($slug, [
                'content' => '<p><strong>Rich Text</strong> keeps safe inline formatting, <a href="/p/review-target">links</a>, and small lists.</p><ul><li>Bold</li><li>Links</li><li>Lists</li></ul>',
            ])),
            'button_link' => $this->fixture($this->block($slug, [
                'title' => 'Read the guide',
                'variant' => 'secondary',
                'settings' => ['url' => '/p/review-target', 'target' => '_self'],
            ])),
            'code' => $this->fixture($this->block($slug, [
                'content' => "ddev artisan test --filter=PageBuilderExperienceTest --stop-on-failure\n# review the smallest relevant surface first",
                'settings' => ['language' => 'bash'],
            ])),
            'card' => $this->fixture($this->block($slug, [
                'title' => 'Snapshot card',
                'subtitle' => 'Review fixture',
                'content' => 'The current public card renderer supports footer child blocks for actions.',
                'settings' => ['variant' => 'promo'],
            ], [
                $this->block('button_link', [
                    'title' => 'Open details',
                    'variant' => 'secondary',
                    'settings' => ['url' => '/p/review-target#card-details'],
                ]),
            ])),
            'stat-card' => $this->fixture($this->block($slug, [
                'subtitle' => 'System health',
                'title' => '99.98%',
                'content' => 'Measured across the review fixture window.',
            ])),
            'table' => $this->fixture($this->block($slug, [
                'title' => 'Validation policy summary',
                'settings' => ['rows' => [
                    ['columns' => ['Step', 'Scope', 'Command']],
                    ['columns' => ['Iteration', 'Focused', 'ddev artisan test --filter=PageBuilderExperienceTest --stop-on-failure']],
                    ['columns' => ['Release gate', 'Full suite', 'ddev artisan test']],
                ]],
            ])),
            'quote' => $this->fixture($this->block($slug, [
                'content' => 'Inspect the renderer output first, then standardize the import contract from what actually ships.',
                'title' => 'WebBlocks CMS review note',
                'subtitle' => 'Snapshot fixture',
                'variant' => 'testimonial',
            ])),
            'link-list' => $this->fixture($this->block($slug, [], [
                $this->block('link-list-item', [
                    'title' => 'Current render output',
                    'subtitle' => 'Primary review path',
                    'content' => 'Inspect the generated HTML for the current renderer contract.',
                    'url' => '/p/review-target',
                ]),
                $this->block('link-list-item', [
                    'title' => 'Proposed import marker',
                    'subtitle' => 'Future-facing note',
                    'content' => 'Compare the current root element with the suggested wb-cms-block marker.',
                    'url' => '/p/review-target#import-marker',
                ]),
            ])),
            'link-list-item' => $this->fixture($this->block($slug, [
                'title' => 'Inspect this block',
                'subtitle' => 'Focused review item',
                'content' => 'A standalone link-list item snapshot shows the exact current anchor structure.',
                'url' => '/p/review-target',
            ])),
            'toc' => $this->fixture(
                $this->block($slug, ['title' => 'On this page']),
                [
                    $this->block('header', ['title' => 'Introduction', 'variant' => 'h2', 'settings' => ['anchor' => 'introduction']]),
                    $this->block('header', ['title' => 'Implementation details', 'variant' => 'h3', 'settings' => ['anchor' => 'implementation-details']]),
                ]
            ),
            'alert' => $this->fixture($this->block($slug, [
                'title' => 'Review carefully',
                'content' => 'This output reflects the current shipped public renderer, not a future import contract.',
                'settings' => ['variant' => 'warning'],
            ])),
            'breadcrumb' => $this->fixture($this->block($slug, [
                'settings' => ['include_current' => true, 'home_label' => 'Docs Home'],
            ])),
            'header-actions' => $this->fixture($this->block($slug, [
                'settings' => ['show_mode_toggle' => true, 'show_accent_toggle' => true, 'show_search' => true],
            ])),
            'sidebar-brand' => $this->fixture($this->block($slug, [
                'title' => 'Docs Review',
                'subtitle' => 'Current public brand output',
                'settings' => ['url' => '/'],
                'asset' => $logoAsset,
            ])),
            'sidebar-navigation' => $this->fixture($this->block($slug, [
                'title' => 'Documentation navigation',
                'settings' => ['menu_key' => NavigationItem::MENU_DOCS, 'show_icons' => true, 'active_matching' => 'path'],
            ])),
            'sidebar-nav-item' => $this->fixture($this->block($slug, [
                'title' => 'Current page',
                'settings' => ['url' => '/p/review-target', 'icon' => 'book-open', 'active_mode' => 'path'],
            ])),
            'sidebar-nav-group' => $this->fixture($this->block($slug, [
                'title' => 'Grouped links',
                'settings' => ['icon' => 'layers', 'initially_open' => false],
            ], [
                $this->block('sidebar-nav-item', [
                    'title' => 'Current page',
                    'settings' => ['url' => '/p/review-target', 'active_mode' => 'path'],
                ]),
                $this->block('sidebar-nav-item', [
                    'title' => 'Patterns',
                    'settings' => ['url' => '/p/patterns'],
                ]),
            ])),
            'sidebar-footer' => $this->fixture($this->block($slug, [
                'title' => 'Need a review pass?',
                'content' => 'Capture the current output first, then compare future import markers against it.',
                'subtitle' => 'Snapshot review footer',
                'settings' => ['variant' => 'success'],
            ])),
            'search-form' => $this->fixture($this->block($slug, [
                'title' => 'Search docs',
                'content' => 'Search the review site',
                'subtitle' => 'Find block output',
                'variant' => 'secondary',
                'settings' => ['show_button' => true],
            ])),
            'html' => $this->fixture($this->block($slug, [
                'content' => '<div class="snapshot-html-block"><strong>Trusted HTML</strong> <span>review snippet</span></div>',
            ])),
            default => $this->fixture($this->block($slug, [
                'title' => Str::headline(str_replace(['-', '_'], ' ', $slug)),
                'content' => 'Generic review fixture content for a published block type without a custom snapshot fixture yet.',
            ])),
        };
    }

    private function fixture(Block $block, array $pageBlocks = []): array
    {
        return [
            'block' => $block,
            'page_blocks' => $pageBlocks,
        ];
    }

    private function block(string $slug, array $attributes = [], array $children = []): Block
    {
        $block = new Block;
        $block->forceFill([
            'id' => $this->nextBlockId++,
            'type' => $slug,
            'status' => 'published',
            'sort_order' => $attributes['sort_order'] ?? 0,
            'title' => $attributes['title'] ?? null,
            'subtitle' => $attributes['subtitle'] ?? null,
            'content' => $attributes['content'] ?? null,
            'url' => $attributes['url'] ?? null,
            'variant' => $attributes['variant'] ?? null,
            'meta' => array_key_exists('meta', $attributes)
                ? (is_array($attributes['meta']) ? json_encode($attributes['meta'], JSON_UNESCAPED_SLASHES) : $attributes['meta'])
                : null,
            'settings' => array_key_exists('settings', $attributes)
                ? (is_array($attributes['settings']) ? json_encode($attributes['settings'], JSON_UNESCAPED_SLASHES) : $attributes['settings'])
                : null,
        ]);

        $blockType = BlockType::query()->where('slug', $slug)->first();

        if ($blockType) {
            $block->block_type_id = $blockType->id;
            $block->setRelation('blockType', $blockType);
        }

        if (($attributes['asset'] ?? null) instanceof Asset) {
            $block->asset_id = $attributes['asset']->id;
            $block->setRelation('asset', $attributes['asset']);
        } else {
            $block->setRelation('asset', null);
        }

        $block->setRelation('blockAssets', collect());
        $block->setRelation('textTranslations', collect());
        $block->setRelation('buttonTranslations', collect());
        $block->setRelation('imageTranslations', collect());
        $block->setRelation('contactFormTranslations', collect());

        foreach ($children as $index => $child) {
            $child->parent_id = $block->id;
            $child->sort_order = $index;
        }

        $block->setRelation('children', collect($children));

        return $block;
    }

    private function flattenBlocks(array $blocks): Collection
    {
        $flattened = collect();

        foreach ($blocks as $block) {
            $flattened->push($block);

            foreach ($block->children as $child) {
                $flattened = $flattened->merge($this->flattenBlocks([$child]));
            }
        }

        return $flattened->unique(fn (Block $block) => $block->id)->values();
    }

    private function applyRenderContext(Block $block, Page $page, string $slotSlug): void
    {
        $block->setRelation('renderPage', $page);
        $block->setRelation('page', $page);
        $block->setAttribute('render_locale_code', $page->currentTranslation?->locale?->code);
        $block->setAttribute('render_slot_slug', $slotSlug);

        foreach ($block->children as $child) {
            $this->applyRenderContext($child, $page, $slotSlug);
        }
    }

    private function buildSnapshotDocument(
        BlockType $blockType,
        CarbonInterface $generatedAt,
        bool $rendererFound,
        string $currentHtml,
        ?string $proposedPreview,
        array $warnings,
    ): string {
        $warningsHtml = $warnings === []
            ? ''
            : '<section><h2>Warnings</h2><ul>'.$this->bulletList($warnings).'</ul></section>';
        $proposedSection = $proposedPreview === null
            ? ''
            : '<section><h2>Proposed import marker preview</h2><p>This preview is not current runtime behavior. It shows one likely future import annotation using <code>wb-cms-block</code> and <code>data-wb-cms-block-type</code>.</p><pre><code>'.e($proposedPreview).'</code></pre></section>';

        return implode("\n", [
            '<!-- block type name: '.e($blockType->name).' -->',
            '<!-- block type slug: '.e($blockType->slug).' -->',
            '<!-- category: '.e($blockType->category ?? 'uncategorized').' -->',
            '<!-- generated timestamp: '.e($generatedAt->toIso8601String()).' -->',
            '<!-- dedicated public renderer partial found: '.($rendererFound ? 'yes' : 'no').' -->',
            '<!doctype html>',
            '<html lang="en">',
            '<head>',
            '    <meta charset="utf-8">',
            '    <meta name="viewport" content="width=device-width, initial-scale=1">',
            '    <title>'.e($blockType->name).' snapshot</title>',
            '    <style>',
            '        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 2rem; color: #111827; background: #f9fafb; }',
            '        main { max-width: 960px; margin: 0 auto; display: grid; gap: 1.5rem; }',
            '        section { background: #fff; border: 1px solid #d1d5db; border-radius: 0.75rem; padding: 1rem 1.25rem; }',
            '        h1, h2 { margin: 0 0 0.75rem; }',
            '        p { line-height: 1.5; }',
            '        code, pre { font-family: ui-monospace, SFMono-Regular, monospace; }',
            '        pre { overflow-x: auto; padding: 0.75rem; background: #111827; color: #f9fafb; border-radius: 0.5rem; }',
            '        .snapshot-render { padding: 1rem; border: 1px dashed #9ca3af; border-radius: 0.75rem; background: #fff; }',
            '        .meta { color: #4b5563; }',
            '    </style>',
            '</head>',
            '<body>',
            '    <main>',
            '        <section>',
            '            <h1>'.e($blockType->name).'</h1>',
            '            <p class="meta">Slug: <code>'.e($blockType->slug).'</code> • Category: <code>'.e($blockType->category ?? 'uncategorized').'</code> • Renderer partial found: <strong>'.($rendererFound ? 'yes' : 'no').'</strong></p>',
            '        </section>',
            '        <section>',
            '            <h2>Current rendered HTML</h2>',
            '            <p>This section contains the current output generated through the live public block renderer.</p>',
            '            <div class="snapshot-render">',
            '                <!-- Begin actual rendered output -->',
                             $currentHtml !== '' ? $currentHtml : '<p><em>No HTML was rendered for the supplied review fixture.</em></p>',
            '                <!-- End actual rendered output -->',
            '            </div>',
            '        </section>',
                     $warningsHtml,
                     $proposedSection,
            '    </main>',
            '</body>',
            '</html>',
        ]);
    }

    private function buildFailureDocument(BlockType $blockType, CarbonInterface $generatedAt, bool $rendererFound, string $message): string
    {
        return implode("\n", [
            '<!-- block type name: '.e($blockType->name).' -->',
            '<!-- block type slug: '.e($blockType->slug).' -->',
            '<!-- category: '.e($blockType->category ?? 'uncategorized').' -->',
            '<!-- generated timestamp: '.e($generatedAt->toIso8601String()).' -->',
            '<!-- dedicated public renderer partial found: '.($rendererFound ? 'yes' : 'no').' -->',
            '<!doctype html>',
            '<html lang="en">',
            '<head><meta charset="utf-8"><title>'.e($blockType->name).' snapshot failed</title></head>',
            '<body>',
            '    <h1>'.e($blockType->name).' snapshot failed</h1>',
            '    <p>The snapshot command could not render this block safely.</p>',
            '    <pre><code>'.e($message).'</code></pre>',
            '</body>',
            '</html>',
        ]);
    }

    private function buildIndexDocument(array $entries, CarbonInterface $generatedAt): string
    {
        $groups = collect($entries)
            ->groupBy(fn (array $entry) => $entry['category'] ?: 'uncategorized')
            ->sortKeys();

        $sections = $groups->map(function (Collection $group, string $category): string {
            $items = $group
                ->sortBy(fn (array $entry) => $entry['name'])
                ->map(function (array $entry): string {
                    $status = $entry['renderer_found'] ? 'renderer found' : 'renderer missing';
                    $warningHtml = ($entry['warnings'] ?? []) === []
                        ? ''
                        : '<div class="warnings">'.implode('', array_map(fn (string $warning) => '<div>'.e($warning).'</div>', $entry['warnings'])).'</div>';

                    return '<li><a href="'.e($entry['output_file']).'">'.e($entry['name']).'</a> '
                        .'<span class="badge">'.e($entry['slug']).'</span> '
                        .'<span class="badge">'.e($status).'</span>'
                        .$warningHtml
                        .'</li>';
                })
                ->implode('');

            return '<section><h2>'.e(Str::headline($category)).'</h2><ul>'.$items.'</ul></section>';
        })->implode('');

        return implode("\n", [
            '<!doctype html>',
            '<html lang="en">',
            '<head>',
            '    <meta charset="utf-8">',
            '    <meta name="viewport" content="width=device-width, initial-scale=1">',
            '    <title>Block render snapshots</title>',
            '    <style>',
            '        body { font-family: ui-sans-serif, system-ui, sans-serif; margin: 2rem; color: #111827; background: #f9fafb; }',
            '        main { max-width: 960px; margin: 0 auto; display: grid; gap: 1rem; }',
            '        section { background: #fff; border: 1px solid #d1d5db; border-radius: 0.75rem; padding: 1rem 1.25rem; }',
            '        ul { margin: 0; padding-left: 1.25rem; }',
            '        li { margin: 0.5rem 0; }',
            '        .badge { display: inline-block; margin-left: 0.5rem; padding: 0.1rem 0.45rem; border-radius: 999px; background: #e5e7eb; color: #374151; font-size: 0.85rem; }',
            '        .warnings { margin-top: 0.35rem; color: #b45309; }',
            '    </style>',
            '</head>',
            '<body>',
            '    <main>',
            '        <section>',
            '            <h1>Published block render snapshots</h1>',
            '            <p>Generated at '.e($generatedAt->toIso8601String()).'. These files capture the current public renderer output for each published block type and add a non-runtime proposed import marker preview where needed.</p>',
            '        </section>',
                     $sections,
            '    </main>',
            '</body>',
            '</html>',
        ]);
    }

    private function proposedMarkerPreview(string $currentHtml, string $slug): ?string
    {
        if ($currentHtml === '') {
            return null;
        }

        if (! preg_match('/<([a-zA-Z][a-zA-Z0-9:-]*)([^>]*)>/', $currentHtml, $matches, PREG_OFFSET_CAPTURE)) {
            return null;
        }

        $fullMatch = $matches[0][0];
        $fullOffset = $matches[0][1];
        $tagName = $matches[1][0];
        $attributes = $matches[2][0];

        if (str_contains($attributes, 'data-wb-cms-block-type=')) {
            return $currentHtml;
        }

        if (preg_match('/\sclass="([^"]*)"/', $attributes, $classMatch) === 1) {
            $newClass = trim($classMatch[1].' wb-cms-block');
            $attributes = preg_replace('/\sclass="([^"]*)"/', ' class="'.e($newClass).'"', $attributes, 1) ?? $attributes;
        } else {
            $attributes .= ' class="wb-cms-block"';
        }

        $attributes .= ' data-wb-cms-block-type="'.e($slug).'"';

        $replacement = '<'.$tagName.$attributes.'>';

        return substr($currentHtml, 0, $fullOffset)
            .$replacement
            .substr($currentHtml, $fullOffset + strlen($fullMatch));
    }

    private function bulletList(array $items): string
    {
        return collect($items)
            ->map(fn (string $item) => '<li>'.e($item).'</li>')
            ->implode('');
    }
}
