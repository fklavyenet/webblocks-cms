<?php

namespace App\Support\Sites;

use App\Models\Block;
use App\Models\BlockAsset;
use App\Models\BlockButtonTranslation;
use App\Models\BlockContactFormTranslation;
use App\Models\BlockImageTranslation;
use App\Models\BlockTextTranslation;
use App\Models\ContactMessage;
use App\Models\NavigationItem;
use App\Models\Page;
use App\Models\PageRevision;
use App\Models\PageSlot;
use App\Models\PageTranslation;
use App\Models\Site;
use App\Models\SiteLocale;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use RuntimeException;

class SiteDeleteService
{
    public function __construct(
        private readonly DatabaseManager $db,
        private readonly SiteDomainNormalizer $domainNormalizer,
    ) {}

    public function inspect(Site|string|int $site): SiteDeleteResult
    {
        $resolvedSite = $this->resolveSite($site);
        $counts = $this->deletionCounts($resolvedSite);
        $blockers = $this->blockersFor($resolvedSite, $counts);

        return new SiteDeleteResult(
            site: $resolvedSite->loadMissing('locales'),
            canDelete: $blockers === [],
            deleted: false,
            counts: $counts,
            blockers: $blockers,
            warnings: ['Shared assets and physical files are left intact.'],
        );
    }

    public function delete(Site|string|int $site): SiteDeleteResult
    {
        $siteId = $this->resolveSite($site)->getKey();

        return $this->db->transaction(function () use ($siteId): SiteDeleteResult {
            $site = Site::query()->lockForUpdate()->find($siteId)
                ?? throw new RuntimeException('Site could not be resolved.');

            $counts = $this->deletionCounts($site);
            $blockers = $this->blockersFor($site, $counts);

            if ($blockers !== []) {
                return new SiteDeleteResult(
                    site: $site->loadMissing('locales'),
                    canDelete: false,
                    deleted: false,
                    counts: $counts,
                    blockers: $blockers,
                    warnings: ['Shared assets and physical files are left intact.'],
                );
            }

            $this->deleteSiteScopedContent($site);
            $site->delete();

            return new SiteDeleteResult(
                site: $site->loadMissing('locales'),
                canDelete: true,
                deleted: true,
                counts: $counts,
                blockers: [],
                warnings: ['Shared assets and physical files are left intact.'],
            );
        });
    }

    public function resolveSiteIdentifier(string|int $identifier): ?Site
    {
        if (is_int($identifier) || ctype_digit((string) $identifier)) {
            return Site::query()->find((int) $identifier);
        }

        $normalized = trim((string) $identifier);

        if ($normalized === '') {
            return null;
        }

        $domain = $this->domainNormalizer->normalize($normalized);

        return Site::query()
            ->where('handle', str($normalized)->slug()->toString())
            ->orWhere('name', $normalized)
            ->when($domain !== null, fn ($query) => $query->orWhere('domain', $domain))
            ->first();
    }

    private function resolveSite(Site|string|int $site): Site
    {
        if ($site instanceof Site) {
            return $site;
        }

        return $this->resolveSiteIdentifier($site)
            ?? throw new RuntimeException('Site could not be resolved.');
    }

    private function deletionCounts(Site $site): array
    {
        $pageIds = Page::query()
            ->where('site_id', $site->id)
            ->pluck('id');

        $blockIds = $pageIds->isEmpty()
            ? collect()
            : Block::query()->whereIn('page_id', $pageIds)->pluck('id');

        return [
            'site_locales' => SiteLocale::query()->where('site_id', $site->id)->count(),
            'pages' => $pageIds->count(),
            'page_revisions' => PageRevision::query()->where('site_id', $site->id)->count(),
            'page_translations' => $pageIds->isEmpty() ? 0 : PageTranslation::query()->whereIn('page_id', $pageIds)->count(),
            'page_slots' => $pageIds->isEmpty() ? 0 : PageSlot::query()->whereIn('page_id', $pageIds)->count(),
            'blocks' => $blockIds->count(),
            'block_assets' => $blockIds->isEmpty() ? 0 : BlockAsset::query()->whereIn('block_id', $blockIds)->count(),
            'block_translation_rows' => $this->blockTranslationCount($blockIds),
            'navigation_items' => NavigationItem::query()->where('site_id', $site->id)->count(),
            'contact_messages' => $this->contactMessageCount($pageIds, $blockIds),
        ];
    }

    private function blockersFor(Site $site, array $counts): array
    {
        $blockers = [];

        if ($site->is_primary) {
            $blockers[] = 'Primary site cannot be deleted.';
        }

        if (Site::query()->count() <= 1) {
            $blockers[] = 'The last remaining site cannot be deleted.';
        }

        if (($counts['contact_messages'] ?? 0) > 0) {
            $blockers[] = 'This site has contact messages linked to its pages or blocks and cannot be deleted safely.';
        }

        return $blockers;
    }

    private function blockTranslationCount(Collection $blockIds): int
    {
        if ($blockIds->isEmpty()) {
            return 0;
        }

        return BlockTextTranslation::query()->whereIn('block_id', $blockIds)->count()
            + BlockButtonTranslation::query()->whereIn('block_id', $blockIds)->count()
            + BlockImageTranslation::query()->whereIn('block_id', $blockIds)->count()
            + BlockContactFormTranslation::query()->whereIn('block_id', $blockIds)->count();
    }

    private function contactMessageCount(Collection $pageIds, Collection $blockIds): int
    {
        if ($pageIds->isEmpty() && $blockIds->isEmpty()) {
            return 0;
        }

        return ContactMessage::query()
            ->where(function ($query) use ($pageIds, $blockIds): void {
                if ($pageIds->isNotEmpty()) {
                    $query->whereIn('page_id', $pageIds);
                }

                if ($blockIds->isNotEmpty()) {
                    $method = $pageIds->isNotEmpty() ? 'orWhereIn' : 'whereIn';
                    $query->{$method}('block_id', $blockIds);
                }
            })
            ->count();
    }

    private function deleteSiteScopedContent(Site $site): void
    {
        $pageIds = Page::query()
            ->where('site_id', $site->id)
            ->pluck('id');

        $blockIds = $pageIds->isEmpty()
            ? collect()
            : Block::query()->whereIn('page_id', $pageIds)->pluck('id');

        PageRevision::query()->where('site_id', $site->id)->delete();

        if ($blockIds->isNotEmpty()) {
            BlockAsset::query()->whereIn('block_id', $blockIds)->delete();
            BlockButtonTranslation::query()->whereIn('block_id', $blockIds)->delete();
            BlockContactFormTranslation::query()->whereIn('block_id', $blockIds)->delete();
            BlockImageTranslation::query()->whereIn('block_id', $blockIds)->delete();
            BlockTextTranslation::query()->whereIn('block_id', $blockIds)->delete();
            Block::query()->whereIn('id', $blockIds)->delete();
        }

        if ($pageIds->isNotEmpty()) {
            PageSlot::query()->whereIn('page_id', $pageIds)->delete();
            PageTranslation::query()->whereIn('page_id', $pageIds)->delete();
            Page::query()->whereIn('id', $pageIds)->delete();
        }

        NavigationItem::query()->where('site_id', $site->id)->delete();
        SiteLocale::query()->where('site_id', $site->id)->delete();
    }
}
