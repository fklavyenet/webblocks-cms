<?php

namespace Project\Support\UiDocs;

use App\Models\Page;
use App\Models\PageTranslation;
use App\Models\Site;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RepairWebBlocksUiDocsEnvironment
{
    public function __construct(private readonly SetupWebBlocksUiDocsSite $setup) {}

    public function run(): array
    {
        return DB::transaction(function (): array {
            $messages = collect($this->setup->run());
            $deleted = $this->deleteAccidentalArtifactPages();

            if ($deleted->isEmpty()) {
                $messages->push('No accidental debug/test pages required cleanup.');
            } else {
                foreach ($deleted as $pageSummary) {
                    $messages->push('Deleted accidental artifact: '.$pageSummary);
                }
            }

            return $messages->all();
        });
    }

    public function accidentalArtifactPages(): Collection
    {
        $defaultLocaleId = Page::defaultLocaleId();

        return Page::query()
            ->with(['site', 'translations', 'slots', 'blocks'])
            ->get()
            ->filter(function (Page $page) use ($defaultLocaleId): bool {
                $translation = $page->translations->firstWhere('locale_id', $defaultLocaleId) ?? $page->translations->first();
                $title = strtolower(trim((string) ($translation?->name ?? '')));
                $path = strtolower(trim((string) ($translation?->path ?? '')));

                if ($title === 'about nested toc debug' || $path === '/p/about-nested-toc-debug') {
                    return true;
                }

                return $title === ''
                    && $path === ''
                    && $page->slots->isEmpty()
                    && $page->blocks->isEmpty();
            })
            ->values();
    }

    private function deleteAccidentalArtifactPages(): Collection
    {
        return $this->accidentalArtifactPages()
            ->map(function (Page $page): string {
                $label = $this->pageSummary($page);
                $page->delete();

                return $label;
            })
            ->values();
    }

    private function pageSummary(Page $page): string
    {
        $translation = $page->translations->firstWhere('locale_id', Page::defaultLocaleId()) ?? $page->translations->first();
        $title = trim((string) ($translation?->name ?? '')) ?: '(untitled)';
        $path = trim((string) ($translation?->path ?? '')) ?: 'no-path';
        $site = $page->site?->handle ?? 'unknown-site';

        return sprintf('%s [%s] on site %s (page_id=%d)', $title, $path, $site, $page->id);
    }
}
