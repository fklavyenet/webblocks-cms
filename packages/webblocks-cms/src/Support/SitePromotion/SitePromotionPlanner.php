<?php

namespace WebBlocks\Cms\Support\SitePromotion;

use Illuminate\Support\Facades\Storage;
use RuntimeException;
use WebBlocks\Cms\Models\Locale;
use WebBlocks\Cms\Models\NavigationItem;
use WebBlocks\Cms\Models\Page;
use WebBlocks\Cms\Models\PageAsset;
use WebBlocks\Cms\Models\SharedSlot;
use WebBlocks\Cms\Models\Site;
use WebBlocks\Cms\Support\Pages\PageAssetPathValidator;

class SitePromotionPlanner
{
    public function __construct(
        private readonly SitePromotionPackageInspector $packageInspector,
        private readonly SitePromotionPreservePolicy $preservePolicy,
        private readonly SitePromotionPlanStore $planStore,
        private readonly PageAssetPathValidator $pageAssetPathValidator,
    ) {}

    public function plan(string $archivePath, SitePromotionOptions $options): SitePromotionPlan
    {
        $inspection = $this->packageInspector->inspectStoredArchive($archivePath);
        $targetSite = Site::query()->find($options->targetSiteId);

        if (! $targetSite instanceof Site) {
            throw new RuntimeException('Target site could not be resolved.');
        }

        $payload = $inspection->payload;
        $errors = array_values($inspection->errors);
        $warnings = array_values($inspection->warnings);

        if (($payload['site']['handle'] ?? null) === $targetSite->handle) {
            $warnings[] = 'Source package handle matches the target site handle. Review the dry run carefully before applying.';
        }

        if ($options->isMirror()) {
            $warnings[] = 'Mirror strategy will archive absent target pages, deactivate absent Shared Slots, and remove absent site variables, navigation items, and page asset rows where safe.';
        }

        if ($inspection->includesAssets && ! $options->applyAssets) {
            $warnings[] = 'Package includes media or public asset files, but Apply Assets is disabled. Promotion will keep content rows and skip file copy plus new asset references where applicable.';
        }

        $sourceLocaleCodes = collect($payload['locales'] ?? [])->pluck('code')->map(fn ($code) => Locale::normalizeCode($code))->filter()->values();
        $existingLocaleCodes = Locale::query()->whereIn('code', $sourceLocaleCodes)->pluck('code')->values();
        $missingLocaleCodes = $sourceLocaleCodes->diff($existingLocaleCodes)->values();

        if ($missingLocaleCodes->isNotEmpty()) {
            $warnings[] = 'Dry run found missing install locales that will be created during apply: '.$missingLocaleCodes->implode(', ').'.';
        }

        $invalidPageAssetPaths = collect($payload['page_assets'] ?? [])->map(function (array $row): ?string {
            return $this->pageAssetPathValidator->validate((string) ($row['type'] ?? ''), (string) ($row['path'] ?? ''));
        })->filter()->values();

        foreach ($invalidPageAssetPaths as $message) {
            $errors[] = 'Promotion package contains an invalid page asset path: '.$message;
        }

        $sourcePageMap = $this->sourcePageMap($payload);
        $targetPageMap = $this->targetPageMap($targetSite);
        $sourceSharedSlotMap = collect($payload['shared_slots'] ?? [])->keyBy(fn (array $row) => (string) ($row['handle'] ?? ''));
        $targetSharedSlotMap = SharedSlot::query()->where('site_id', $targetSite->id)->get()->keyBy('handle');
        $sourceSiteVariables = collect($payload['site_variables'] ?? [])->keyBy(fn (array $row) => (string) ($row['key'] ?? ''));
        $targetSiteVariables = $targetSite->siteVariables()->get()->keyBy('key');
        $sourceNavigationSignatures = $this->sourceNavigationSignatures($payload, $sourcePageMap);
        $targetNavigationSignatures = $this->targetNavigationSignatures($targetSite, $targetPageMap);
        $sourcePageAssetSignatures = $this->sourcePageAssetSignatures($payload, $sourcePageMap);
        $targetPageAssetSignatures = $this->targetPageAssetSignatures($targetSite, $targetPageMap);

        $pagesToCreate = array_values(array_diff(array_keys($sourcePageMap), array_keys($targetPageMap)));
        $pagesToUpdate = array_values(array_intersect(array_keys($sourcePageMap), array_keys($targetPageMap)));
        $pagesToArchive = $options->isMirror()
            ? array_values(array_diff(array_keys($targetPageMap), array_keys($sourcePageMap)))
            : [];

        $sharedSlotsToCreate = array_values(array_diff($sourceSharedSlotMap->keys()->all(), $targetSharedSlotMap->keys()->all()));
        $sharedSlotsToUpdate = array_values(array_intersect($sourceSharedSlotMap->keys()->all(), $targetSharedSlotMap->keys()->all()));
        $sharedSlotsToDeactivate = $options->isMirror()
            ? array_values(array_diff($targetSharedSlotMap->keys()->all(), $sourceSharedSlotMap->keys()->all()))
            : [];

        $siteVariablesToCreate = array_values(array_diff($sourceSiteVariables->keys()->all(), $targetSiteVariables->keys()->all()));
        $siteVariablesToUpdate = array_values(array_intersect($sourceSiteVariables->keys()->all(), $targetSiteVariables->keys()->all()));
        $siteVariablesToRemove = $options->isMirror()
            ? array_values(array_diff($targetSiteVariables->keys()->all(), $sourceSiteVariables->keys()->all()))
            : [];

        $navigationToCreate = array_values(array_diff(array_keys($sourceNavigationSignatures), array_keys($targetNavigationSignatures)));
        $navigationToUpdate = array_values(array_intersect(array_keys($sourceNavigationSignatures), array_keys($targetNavigationSignatures)));
        $navigationToRemove = $options->isMirror()
            ? array_values(array_diff(array_keys($targetNavigationSignatures), array_keys($sourceNavigationSignatures)))
            : [];

        $pageAssetsToCreate = array_values(array_diff(array_keys($sourcePageAssetSignatures), array_keys($targetPageAssetSignatures)));
        $pageAssetsToUpdate = array_values(array_intersect(array_keys($sourcePageAssetSignatures), array_keys($targetPageAssetSignatures)));
        $pageAssetsToRemove = $options->isMirror()
            ? array_values(array_diff(array_keys($targetPageAssetSignatures), array_keys($sourcePageAssetSignatures)))
            : [];

        $mediaSummary = [
            'apply_assets' => $options->applyAssets,
            'package_includes_assets' => $inspection->includesAssets,
            'asset_files_to_add' => 0,
            'asset_files_to_overwrite' => 0,
            'page_asset_files_to_add' => 0,
            'page_asset_files_to_overwrite' => 0,
            'files_skipped' => 0,
        ];

        if ($inspection->includesAssets) {
            foreach ($payload['assets'] ?? [] as $asset) {
                $targetExists = is_file(Storage::disk((string) ($asset['disk'] ?? 'public'))->path((string) ($asset['path'] ?? '')));
                $targetExists ? $mediaSummary['asset_files_to_overwrite']++ : $mediaSummary['asset_files_to_add']++;
            }

            foreach ($payload['page_assets'] ?? [] as $pageAsset) {
                $path = ltrim((string) ($pageAsset['path'] ?? ''), '/');
                if ($path === '' || ! str_starts_with($path, 'site/')) {
                    $mediaSummary['files_skipped']++;

                    continue;
                }

                $targetExists = is_file(public_path($path));
                $targetExists ? $mediaSummary['page_asset_files_to_overwrite']++ : $mediaSummary['page_asset_files_to_add']++;
            }
        }

        $plan = new SitePromotionPlan(
            token: $this->planStore->newToken(),
            archiveDisk: $inspection->archiveDisk,
            archivePath: $inspection->archivePath,
            archiveName: $inspection->archiveName,
            sourceSite: $inspection->sourceSite(),
            targetSite: [
                'id' => $targetSite->id,
                'name' => $targetSite->name,
                'handle' => $targetSite->handle,
                'domain' => $targetSite->canonicalDomain(),
            ],
            options: $options->toArray(),
            localeSummary: [
                'compatible' => $existingLocaleCodes->values()->all(),
                'missing' => $missingLocaleCodes->all(),
                'will_create_missing' => $missingLocaleCodes->isNotEmpty(),
            ],
            operations: [
                'pages' => [
                    'create' => $pagesToCreate,
                    'update' => $pagesToUpdate,
                    'archive' => $pagesToArchive,
                ],
                'shared_slots' => [
                    'create' => $sharedSlotsToCreate,
                    'update' => $sharedSlotsToUpdate,
                    'deactivate' => $sharedSlotsToDeactivate,
                ],
                'site_variables' => [
                    'create' => $siteVariablesToCreate,
                    'update' => $siteVariablesToUpdate,
                    'remove' => $siteVariablesToRemove,
                ],
                'navigation' => [
                    'create' => $navigationToCreate,
                    'update' => $navigationToUpdate,
                    'remove' => $navigationToRemove,
                ],
                'page_assets' => [
                    'create' => $pageAssetsToCreate,
                    'update' => $pageAssetsToUpdate,
                    'remove' => $pageAssetsToRemove,
                ],
                'media' => $mediaSummary,
                'domains' => [
                    'preserved' => [$targetSite->canonicalDomain()],
                    'skipped_from_package' => collect($payload['site_domains'] ?? [])->pluck('domain')->filter()->values()->all(),
                ],
            ],
            preservedAreas: $this->preservePolicy->preservedAreas(),
            warnings: $warnings,
            errors: array_values(array_unique($errors)),
            summary: [
                'pages_to_create' => count($pagesToCreate),
                'pages_to_update' => count($pagesToUpdate),
                'pages_to_archive' => count($pagesToArchive),
                'shared_slots_to_create' => count($sharedSlotsToCreate),
                'shared_slots_to_update' => count($sharedSlotsToUpdate),
                'shared_slots_to_deactivate' => count($sharedSlotsToDeactivate),
                'navigation_to_create' => count($navigationToCreate),
                'navigation_to_update' => count($navigationToUpdate),
                'navigation_to_remove' => count($navigationToRemove),
                'site_variables_to_create' => count($siteVariablesToCreate),
                'site_variables_to_update' => count($siteVariablesToUpdate),
                'site_variables_to_remove' => count($siteVariablesToRemove),
                'page_assets_to_create' => count($pageAssetsToCreate),
                'page_assets_to_update' => count($pageAssetsToUpdate),
                'page_assets_to_remove' => count($pageAssetsToRemove),
            ],
            manifest: $inspection->manifest,
        );

        return $this->planStore->save($plan);
    }

    private function sourcePageMap(array $payload): array
    {
        $translations = collect($payload['page_translations'] ?? [])->groupBy('page_id');
        $locales = collect($payload['locales'] ?? [])->keyBy('id');

        $defaultLocaleId = $locales->firstWhere('is_default', true)['id'] ?? null;

        $map = [];

        foreach ($payload['pages'] ?? [] as $page) {
            $group = collect($translations[(int) ($page['id'] ?? 0)] ?? []);
            $defaultTranslation = $group->firstWhere('locale_id', $defaultLocaleId) ?? $group->first();
            $key = trim((string) ($defaultTranslation['path'] ?? ''));

            if ($key === '') {
                $key = 'slug:'.trim((string) ($defaultTranslation['slug'] ?? $page['slug'] ?? $page['id'] ?? ''));
            }

            $map[$key] = $page;
        }

        return $map;
    }

    private function targetPageMap(Site $site): array
    {
        $pages = Page::query()
            ->where('site_id', $site->id)
            ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
            ->with('translations.locale')
            ->get();

        $map = [];

        foreach ($pages as $page) {
            $defaultTranslation = $page->defaultTranslation() ?? $page->translations->first();
            $key = trim((string) ($defaultTranslation?->path ?? ''));

            if ($key === '') {
                $key = 'slug:'.trim((string) ($defaultTranslation?->slug ?? $page->slug ?? $page->id));
            }

            $map[$key] = $page;
        }

        return $map;
    }

    private function sourceNavigationSignatures(array $payload, array $sourcePageMap): array
    {
        $items = collect($payload['navigation_items'] ?? [])->keyBy('id');
        $sourcePageKeys = collect($sourcePageMap)->mapWithKeys(fn (array $page, string $key) => [(int) $page['id'] => $key]);
        $signatures = [];

        $resolver = function (array $item, ?string $parentSignature) use (&$resolver, &$signatures, $items, $sourcePageKeys): void {
            $pageKey = $sourcePageKeys[(int) ($item['page_id'] ?? 0)] ?? null;
            $identity = match ((string) ($item['link_type'] ?? '')) {
                NavigationItem::LINK_PAGE => 'page:'.$pageKey,
                NavigationItem::LINK_CUSTOM_URL => 'url:'.trim((string) ($item['url'] ?? '')),
                default => 'group:'.trim((string) ($item['title'] ?? '')),
            };

            $signature = implode('|', [
                trim((string) ($item['menu_key'] ?? '')),
                (string) ($item['link_type'] ?? ''),
                $identity,
                $parentSignature ?? 'root',
            ]);

            $signatures[$signature] = $item;

            $children = $items->filter(fn (array $candidate) => (int) ($candidate['parent_id'] ?? 0) === (int) ($item['id'] ?? 0))->sortBy('position');

            foreach ($children as $child) {
                $resolver($child, $signature);
            }
        };

        foreach ($items->filter(fn (array $item) => empty($item['parent_id']))->sortBy('position') as $item) {
            $resolver($item, null);
        }

        return $signatures;
    }

    private function targetNavigationSignatures(Site $site, array $targetPageMap): array
    {
        $items = NavigationItem::query()->where('site_id', $site->id)->orderBy('position')->orderBy('id')->get()->keyBy('id');
        $targetPageKeys = collect($targetPageMap)->mapWithKeys(fn (Page $page, string $key) => [$page->id => $key]);
        $signatures = [];

        $resolver = function (NavigationItem $item, ?string $parentSignature) use (&$resolver, &$signatures, $items, $targetPageKeys): void {
            $pageKey = $item->page_id ? ($targetPageKeys[$item->page_id] ?? null) : null;
            $identity = match ($item->link_type) {
                NavigationItem::LINK_PAGE => 'page:'.$pageKey,
                NavigationItem::LINK_CUSTOM_URL => 'url:'.trim((string) $item->url),
                default => 'group:'.trim((string) $item->title),
            };

            $signature = implode('|', [$item->menu_key, $item->link_type, $identity, $parentSignature ?? 'root']);
            $signatures[$signature] = $item;

            foreach ($items->filter(fn (NavigationItem $candidate) => (int) $candidate->parent_id === (int) $item->id)->sortBy('position') as $child) {
                $resolver($child, $signature);
            }
        };

        foreach ($items->filter(fn (NavigationItem $item) => $item->parent_id === null)->sortBy('position') as $item) {
            $resolver($item, null);
        }

        return $signatures;
    }

    private function sourcePageAssetSignatures(array $payload, array $sourcePageMap): array
    {
        $pageKeys = collect($sourcePageMap)->mapWithKeys(fn (array $page, string $key) => [(int) $page['id'] => $key]);
        $signatures = [];

        foreach ($payload['page_assets'] ?? [] as $asset) {
            $pageKey = $pageKeys[(int) ($asset['page_id'] ?? 0)] ?? null;

            if ($pageKey === null) {
                continue;
            }

            $signature = implode('|', [$pageKey, (string) ($asset['type'] ?? ''), (string) ($asset['path'] ?? '')]);
            $signatures[$signature] = $asset;
        }

        return $signatures;
    }

    private function targetPageAssetSignatures(Site $site, array $targetPageMap): array
    {
        $pageIds = collect($targetPageMap)->mapWithKeys(fn (Page $page, string $key) => [$page->id => $key]);
        $signatures = [];

        foreach (PageAsset::query()->whereIn('page_id', $pageIds->keys())->get() as $asset) {
            $pageKey = $pageIds[$asset->page_id] ?? null;

            if ($pageKey === null) {
                continue;
            }

            $signature = implode('|', [$pageKey, $asset->type, $asset->path]);
            $signatures[$signature] = $asset;
        }

        return $signatures;
    }
}
