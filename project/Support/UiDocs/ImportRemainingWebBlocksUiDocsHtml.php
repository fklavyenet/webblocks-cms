<?php

namespace Project\Support\UiDocs;

use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\Site;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class ImportRemainingWebBlocksUiDocsHtml
{
    private const MANIFEST_PATH = 'storage/project/webblocksui.com/docs-html-remaining.json';

    private const CURATED_KEYS = [
        'docs-architecture',
        'docs-foundation',
        'docs-layout',
        'docs-primitives',
        'docs-icons',
    ];

    public function __construct(
        private readonly SetupWebBlocksUiDocsSite $setup,
        private readonly WebBlocksUiImporter $importer,
        private readonly WebBlocksUiDocsMainHtmlExtractor $extractor,
    ) {}

    public function run(bool $forceHtml = false): array
    {
        $messages = [];
        $messages = [...$messages, ...$this->setup->run()];

        $manifest = $this->loadManifest();
        $site = $this->resolveSite();
        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($manifest['pages'] as $entry) {
            $key = trim((string) ($entry['key'] ?? ''));

            if ($key === '') {
                throw new RuntimeException('Remaining docs HTML manifest contains an entry without a key.');
            }

            if (in_array($key, self::CURATED_KEYS, true)) {
                $messages[] = 'Skipped curated page: '.$key;
                $skipped++;

                continue;
            }

            $page = $this->findPage($site, $key, (string) Arr::get($entry, 'slug', ''));

            if ($page && ! $forceHtml) {
                $messages[] = 'Skipped existing page: '.$key.' ('.$page->publicPath().')';
                $skipped++;

                continue;
            }

            $sourceUrl = trim((string) ($entry['source_url'] ?? ''));
            $html = $this->fetchHtml($sourceUrl);
            $mainHtml = $this->extractor->extract($html, $sourceUrl);
            $payload = $this->payloadFor($entry, $mainHtml);

            $result = $this->importer->runGenerated($key, $payload, [
                'title' => $entry['title'] ?? null,
                'source_url' => $sourceUrl,
            ]);

            $messages = [...$messages, ...$result];

            if ($page) {
                $updated++;
            } else {
                $created++;
            }
        }

        $navigationCount = $this->reconcileManifestNavigation($site, $manifest);
        $sidebarBlockCount = $this->importer->syncSidebarNavigationBlocksForSite($site);

        $messages[] = 'Remaining HTML docs created: '.$created;
        $messages[] = 'Remaining HTML docs updated: '.$updated;
        $messages[] = 'Remaining HTML docs skipped: '.$skipped;
        $messages[] = 'Remaining HTML docs manifest pages: '.count($manifest['pages']);
        $messages[] = 'Navigation items synced: '.$navigationCount;
        $messages[] = 'Sidebar navigation blocks updated: '.$sidebarBlockCount;

        return $messages;
    }

    private function loadManifest(): array
    {
        $path = base_path(self::MANIFEST_PATH);

        if (! is_file($path)) {
            throw new RuntimeException('Remaining docs HTML manifest not found: '.$path);
        }

        $decoded = json_decode((string) file_get_contents($path), true);

        if (! is_array($decoded) || ! is_array($decoded['pages'] ?? null) || ! is_array($decoded['navigation'] ?? null)) {
            throw new RuntimeException('Remaining docs HTML manifest is not valid.');
        }

        return $decoded;
    }

    private function resolveSite(): Site
    {
        $site = Site::primary();

        if (! $site) {
            throw new RuntimeException('Default site could not be resolved for remaining docs HTML import.');
        }

        return $site;
    }

    private function findPage(Site $site, string $key, string $slug): ?Page
    {
        return Page::query()
            ->with('translations')
            ->where('site_id', $site->id)
            ->get()
            ->first(function (Page $page) use ($key, $slug): bool {
                if ($page->setting('project_source') === WebBlocksUiImporter::SOURCE && $page->setting('project_page_key') === $key) {
                    return true;
                }

                if ($slug !== '' && $page->translations->contains('slug', $slug)) {
                    return true;
                }

                return $slug !== ''
                    && $page->setting('project_source') === WebBlocksUiImporter::SOURCE
                    && $page->setting('import_strategy') === 'trusted-main-html'
                    && Str::endsWith((string) $page->setting('current_public_path'), '/'.$slug);
            });
    }

    private function fetchHtml(string $sourceUrl): string
    {
        $response = Http::timeout(30)->accept('text/html')->get($sourceUrl);

        if (! $response->successful()) {
            throw new RuntimeException('Failed to fetch source HTML for '.$sourceUrl.'.');
        }

        $html = trim((string) $response->body());

        if ($html === '') {
            throw new RuntimeException('Source HTML was empty for '.$sourceUrl.'.');
        }

        return $html;
    }

    private function payloadFor(array $entry, string $mainHtml): array
    {
        $key = (string) $entry['key'];
        $slug = (string) $entry['slug'];

        return [
            'site' => [
                'target' => 'default_site',
            ],
            'page' => [
                'key' => $key,
                'status' => 'published',
                'page_type' => 'default',
                'public_shell' => 'docs',
                'source_url' => $entry['source_url'],
                'requested_public_path' => $entry['requested_public_path'],
                'current_public_path' => '/p/'.$slug,
                'settings' => [
                    'source_static_path' => $entry['requested_public_path'],
                    'import_strategy' => 'trusted-main-html',
                ],
                'translations' => [
                    'en' => [
                        'name' => $entry['title'],
                        'slug' => $slug,
                    ],
                ],
                'slots' => [
                    'header' => [
                        'sort_order' => 0,
                        'source_type' => 'shared_slot',
                        'shared_slot_handle' => 'docs-header',
                        'settings' => null,
                        'blocks' => [],
                    ],
                    'sidebar' => [
                        'sort_order' => 1,
                        'source_type' => 'shared_slot',
                        'shared_slot_handle' => 'docs-sidebar',
                        'settings' => null,
                        'blocks' => [],
                    ],
                    'main' => [
                        'sort_order' => 2,
                        'source_type' => 'page',
                        'settings' => null,
                        'blocks' => [[
                            'key' => $key.'-main-html',
                            'type' => 'html',
                            'status' => 'published',
                            'settings' => [
                                'label' => 'Imported static main HTML',
                                'source_url' => $entry['source_url'],
                                'import_strategy' => 'trusted-main-html',
                            ],
                            'translations' => [
                                'en' => [
                                    'title' => 'Imported static main HTML',
                                    'content' => $mainHtml,
                                ],
                            ],
                        ]],
                    ],
                ],
            ],
            'navigation' => [
                'menu_key' => NavigationItem::MENU_DOCS,
                'items' => [],
            ],
        ];
    }

    private function reconcileManifestNavigation(Site $site, array $manifest): int
    {
        $pageRefs = collect(Page::query()
            ->with('translations')
            ->where('site_id', $site->id)
            ->get())
            ->filter(fn (Page $page) => $page->setting('project_source') === WebBlocksUiImporter::SOURCE && filled($page->setting('project_page_key')))
            ->mapWithKeys(fn (Page $page) => [(string) $page->setting('project_page_key') => $page->id])
            ->all();

        $homeId = Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'home'))->value('id');
        $gettingStartedId = Page::query()->where('site_id', $site->id)->whereHas('translations', fn ($query) => $query->where('slug', 'getting-started'))->value('id');

        if (! $homeId || ! $gettingStartedId) {
            throw new RuntimeException('Docs home dependencies are missing for navigation sync.');
        }

        $pageRefs['home'] = (int) $homeId;
        $pageRefs['getting-started'] = (int) $gettingStartedId;

        return $this->importer->syncNavigationForSite($site, [
            'menu_key' => $manifest['navigation']['menu_key'] ?? NavigationItem::MENU_DOCS,
            'items' => $this->filterNavigationItems($manifest['navigation']['items'] ?? [], $pageRefs),
        ], $pageRefs);
    }

    private function filterNavigationItems(array $items, array $pageRefs): array
    {
        $filtered = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $linkType = trim((string) ($item['link_type'] ?? NavigationItem::LINK_CUSTOM_URL));
            $pageRef = trim((string) ($item['page_ref'] ?? ''));

            if ($linkType === NavigationItem::LINK_PAGE && ($pageRef === '' || ! array_key_exists($pageRef, $pageRefs))) {
                continue;
            }

            if (isset($item['children']) && is_array($item['children'])) {
                $item['children'] = $this->filterNavigationItems($item['children'], $pageRefs);

                if ($linkType === NavigationItem::LINK_GROUP && $item['children'] === []) {
                    continue;
                }
            }

            $filtered[] = $item;
        }

        return array_values($filtered);
    }
}
