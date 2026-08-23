<?php

namespace WebBlocks\Cms\Support\Sites\ExportImport;

use WebBlocks\Cms\Models\Page;

/**
 * The page list the export picker offers, grouped by site.
 *
 * Shared here because the same modal opens from two screens — Export / Import
 * and Sites — and the two showing different pickers is the kind of difference
 * nobody notices until an export from one of them quietly contains something
 * the other would have excluded.
 */
class ExportablePages
{
  /**
   * @return array<int, list<array<string, mixed>>>
   */
  public function grouped(): array
  {
    return Page::query()
      ->whereNull('settings->revision_restore_candidate')
      // Shared-slot source pages are machinery behind a shared slot rather
      // than content anyone chooses, and the exporter skips them anyway.
      ->where('page_type', '!=', Page::TYPE_SHARED_SLOT_SOURCE)
      ->with('translations')
      ->orderBy('site_id')
      ->orderBy('id')
      ->get()
      ->groupBy('site_id')
      ->map(fn ($pages) => $pages->map(fn (Page $page) => [
        'id' => $page->id,
        'title' => $page->defaultTranslation()?->name ?: ('#'.$page->id),
        'path' => $page->defaultTranslation()?->path,
        'status' => $page->status,
        // Archived pages are offered but start unticked: on a site built
        // through staged updates they are the discarded drafts, and they can
        // easily outweigh the live content.
        'checked' => $page->status !== 'archived',
      ])->values()->all())
      ->all();
  }
}
